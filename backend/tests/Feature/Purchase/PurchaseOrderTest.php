<?php

namespace Tests\Feature\Purchase;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contacts\Models\Partner;
use Modules\Inventory\Models\Picking;
use Modules\Inventory\Services\InventoryService;
use Modules\Product\Models\Product;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Partner $vendor;

    private Product $beras;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
        $this->installModules('Purchase');
        $this->vendor = Partner::factory()->vendor()->create(['name' => 'CV Tani Makmur']);
        $this->beras = Product::factory()->create(['name' => 'Beras 5kg', 'type' => Product::TYPE_STORABLE]);
    }

    private function createRfq(): int
    {
        return $this->postJson('/api/purchase/orders', [
            'partner_id' => $this->vendor->id,
            'expected_date' => now()->addWeek()->toDateString(),
            'lines' => [['product_id' => $this->beras->id, 'quantity' => 40, 'unit_price' => 60000, 'tax_rate' => 11]],
        ])->assertCreated()
            ->assertJsonPath('data.number', 'PO/'.now()->year.'/0001')
            ->assertJsonPath('data.amount_total', '2664000.00')
            ->json('data.id');
    }

    public function test_full_flow_rfq_to_received_and_billed(): void
    {
        $id = $this->createRfq();

        $this->postJson("/api/purchase/orders/{$id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.receipt_status', 'pending')
            ->assertJsonPath('data.bill_status', 'to_bill');

        $receipt = Picking::query()->where('origin_type', 'purchase.order')->firstOrFail();
        $this->assertSame('receipt', $receipt->type);
        $this->assertSame($this->vendor->id, $receipt->partner_id);

        $this->postJson("/api/inventory/transfers/{$receipt->id}/validate")->assertOk();
        $this->assertSame(40.0, app(InventoryService::class)->onHand([$this->beras->id])[$this->beras->id]);
        $this->getJson("/api/purchase/orders/{$id}")
            ->assertJsonPath('data.receipt_status', 'done')
            ->assertJsonPath('data.lines.0.qty_received', '40.0000');

        $billId = $this->postJson("/api/purchase/orders/{$id}/bill")->assertCreated()->json('bill_id');
        $this->getJson("/api/invoicing/invoices/{$billId}")
            ->assertJsonPath('data.type', 'in_invoice')
            ->assertJsonPath('data.amount_total', '2664000.00');
        $this->postJson("/api/invoicing/invoices/{$billId}/post")->assertJsonPath('data.number', 'BILL/'.now()->year.'/0001');

        $this->getJson("/api/purchase/orders/{$id}")
            ->assertJsonPath('data.bill_status', 'billed')
            ->assertJsonPath('data.bills.0.display_name', 'BILL/'.now()->year.'/0001');
    }

    public function test_cannot_cancel_after_receipt(): void
    {
        $id = $this->createRfq();
        $this->postJson("/api/purchase/orders/{$id}/confirm");
        $receipt = Picking::query()->where('origin_id', $id)->firstOrFail();
        $this->postJson("/api/inventory/transfers/{$receipt->id}/validate");

        $this->postJson("/api/purchase/orders/{$id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pesanan yang barangnya sudah diterima tidak bisa dibatalkan.');
    }

    public function test_product_not_purchasable_is_rejected(): void
    {
        $product = Product::factory()->create(['can_be_purchased' => false, 'name' => 'Produk Jadi']);

        $this->postJson('/api/purchase/orders', [
            'partner_id' => $this->vendor->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1]],
        ])->assertUnprocessable()->assertJsonPath('message', 'Produk Jadi tidak ditandai bisa dibeli.');
    }

    public function test_sales_and_purchase_share_inventory(): void
    {
        $this->installModules('Sales');
        $customer = Partner::factory()->create();

        $po = $this->createRfq();
        $this->postJson("/api/purchase/orders/{$po}/confirm");
        $this->postJson('/api/inventory/transfers/'.Picking::query()->where('origin_type', 'purchase.order')->value('id').'/validate');

        $so = $this->postJson('/api/sales/orders', [
            'partner_id' => $customer->id,
            'lines' => [['product_id' => $this->beras->id, 'quantity' => 15, 'unit_price' => 75000]],
        ])->json('data.id');
        $this->postJson("/api/sales/orders/{$so}/confirm");
        $this->postJson('/api/inventory/transfers/'.Picking::query()->where('origin_type', 'sales.order')->value('id').'/validate')->assertOk();

        $this->assertSame(25.0, app(InventoryService::class)->onHand([$this->beras->id])[$this->beras->id]);
        // Event pengiriman penjualan tidak mengubah pesanan pembelian.
        $this->getJson("/api/purchase/orders/{$po}")->assertJsonPath('data.lines.0.qty_received', '40.0000');
    }
}
