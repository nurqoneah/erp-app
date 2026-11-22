<?php

namespace Tests\Feature\Sales;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contacts\Models\Partner;
use Modules\Inventory\Models\Picking;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\InventoryService;
use Modules\Product\Models\Product;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Partner $customer;

    private Product $kopi;

    private Product $jasa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
        $this->installModules('Sales');
        $this->customer = Partner::factory()->create(['name' => 'Toko Andalas']);
        $this->kopi = Product::factory()->create(['name' => 'Kopi Bubuk', 'type' => Product::TYPE_STORABLE]);
        $this->jasa = Product::factory()->create(['name' => 'Instalasi', 'type' => Product::TYPE_SERVICE]);
        app(InventoryService::class)->adjust(Warehouse::query()->first()->stockLocation, [$this->kopi->id => 20]);
    }

    private function createQuotation(): int
    {
        return $this->postJson('/api/sales/orders', [
            'partner_id' => $this->customer->id,
            'order_date' => '2026-11-24',
            'lines' => [
                ['product_id' => $this->kopi->id, 'quantity' => 5, 'unit_price' => 50000, 'tax_rate' => 11],
                ['product_id' => $this->jasa->id, 'quantity' => 1, 'unit_price' => 100000, 'discount' => 10],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.number', 'SO/'.now()->year.'/0001')
            ->assertJsonPath('data.state', 'draft')
            ->assertJsonPath('data.amount_untaxed', '340000.00')
            ->assertJsonPath('data.amount_tax', '27500.00')
            ->assertJsonPath('data.amount_total', '367500.00')
            ->json('data.id');
    }

    public function test_full_flow_quotation_to_paid_invoice(): void
    {
        $id = $this->createQuotation();

        // Konfirmasi → pesanan + pengiriman hanya untuk barang (bukan jasa).
        $this->postJson("/api/sales/orders/{$id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.state', 'confirmed')
            ->assertJsonPath('data.delivery_status', 'pending')
            ->assertJsonPath('data.invoice_status', 'to_invoice')
            ->assertJsonCount(1, 'data.pickings');

        $picking = Picking::query()->where('origin_type', 'sales.order')->firstOrFail();
        $this->assertSame('delivery', $picking->type);
        $this->assertCount(1, $picking->moves);

        // Kirim barang → jumlah terkirim di pesanan ikut berubah.
        $this->postJson("/api/inventory/transfers/{$picking->id}/validate")->assertOk();
        $this->getJson("/api/sales/orders/{$id}")
            ->assertJsonPath('data.delivery_status', 'done')
            ->assertJsonPath('data.lines.0.qty_delivered', '5.0000');
        $this->assertSame(15.0, app(InventoryService::class)->onHand([$this->kopi->id])[$this->kopi->id]);

        // Buat faktur → semua baris difakturkan.
        $invoiceId = $this->postJson("/api/sales/orders/{$id}/invoice")->assertCreated()->json('invoice_id');
        $this->getJson("/api/invoicing/invoices/{$invoiceId}")
            ->assertJsonPath('data.origin', 'SO/'.now()->year.'/0001')
            ->assertJsonPath('data.amount_total', '367500.00');
        $this->getJson("/api/sales/orders/{$id}")
            ->assertJsonPath('data.invoice_status', 'invoiced')
            ->assertJsonPath('data.lines.1.qty_invoiced', '1.0000');
        $this->postJson("/api/sales/orders/{$id}/invoice")->assertUnprocessable();

        // Posting & bayar.
        $this->postJson("/api/invoicing/invoices/{$invoiceId}/post")->assertOk();
        $this->postJson("/api/invoicing/invoices/{$invoiceId}/payments", ['amount' => 367500, 'date' => '2026-11-25', 'method' => 'bank'])
            ->assertJsonPath('data.payment_state', 'paid');
        $this->getJson("/api/sales/orders/{$id}")->assertJsonPath('data.invoices.0.payment_state', 'paid');
    }

    public function test_cancelling_invoice_makes_order_invoiceable_again(): void
    {
        $id = $this->createQuotation();
        $this->postJson("/api/sales/orders/{$id}/confirm");
        $invoiceId = $this->postJson("/api/sales/orders/{$id}/invoice")->json('invoice_id');

        // Ubah jumlah di faktur draf: hanya sebagian yang difakturkan.
        $invoice = $this->getJson("/api/invoicing/invoices/{$invoiceId}")->json('data');
        $lines = collect($invoice['lines'])->map(fn ($l) => [...$l, 'quantity' => $l['product_id'] === $this->kopi->id ? 2 : $l['quantity']])->all();
        $this->putJson("/api/invoicing/invoices/{$invoiceId}", ['partner_id' => $this->customer->id, 'lines' => $lines])->assertOk();
        $this->getJson("/api/sales/orders/{$id}")
            ->assertJsonPath('data.lines.0.qty_invoiced', '2.0000')
            ->assertJsonPath('data.invoice_status', 'to_invoice');

        $this->postJson("/api/invoicing/invoices/{$invoiceId}/cancel")->assertOk();
        $this->getJson("/api/sales/orders/{$id}")->assertJsonPath('data.lines.0.qty_invoiced', '0.0000');
    }

    public function test_cancel_rules(): void
    {
        $id = $this->createQuotation();
        $this->postJson("/api/sales/orders/{$id}/confirm");

        // Batal sebelum dikirim: pengiriman draf ikut dibatalkan.
        $this->postJson("/api/sales/orders/{$id}/cancel")->assertOk()->assertJsonPath('data.state', 'cancelled');
        $this->assertSame('cancelled', Picking::query()->where('origin_id', $id)->value('state'));

        // Kembali jadi penawaran, konfirmasi lagi, kirim → tidak bisa dibatalkan.
        $this->postJson("/api/sales/orders/{$id}/set-to-draft")->assertOk()->assertJsonPath('data.state', 'draft');
        $this->postJson("/api/sales/orders/{$id}/confirm")->assertOk();
        $picking = Picking::query()->where('origin_id', $id)->where('state', 'draft')->firstOrFail();
        $this->postJson("/api/inventory/transfers/{$picking->id}/validate")->assertOk();

        $this->postJson("/api/sales/orders/{$id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pesanan yang barangnya sudah dikirim tidak bisa dibatalkan.');
    }

    public function test_confirmed_order_is_locked_and_draft_can_be_deleted(): void
    {
        $id = $this->createQuotation();
        $payload = ['partner_id' => $this->customer->id, 'lines' => [['product_id' => $this->kopi->id, 'quantity' => 1, 'unit_price' => 1]]];

        $this->putJson("/api/sales/orders/{$id}", $payload)->assertOk()->assertJsonPath('data.amount_total', '1.00');
        $this->postJson("/api/sales/orders/{$id}/confirm");
        $this->putJson("/api/sales/orders/{$id}", $payload)->assertUnprocessable();
        $this->deleteJson("/api/sales/orders/{$id}")->assertUnprocessable();

        $draft = $this->createQuotationNumber2();
        $this->deleteJson("/api/sales/orders/{$draft}")->assertOk();
    }

    private function createQuotationNumber2(): int
    {
        return $this->postJson('/api/sales/orders', [
            'partner_id' => $this->customer->id,
            'lines' => [['product_id' => $this->jasa->id, 'quantity' => 1, 'unit_price' => 1]],
        ])->assertCreated()->json('data.id');
    }

    public function test_service_only_order_has_no_delivery(): void
    {
        $id = $this->createQuotationNumber2();

        $this->postJson("/api/sales/orders/{$id}/confirm")
            ->assertJsonPath('data.delivery_status', 'nothing')
            ->assertJsonCount(0, 'data.pickings');
    }

    public function test_product_not_for_sale_is_rejected(): void
    {
        $internal = Product::factory()->create(['can_be_sold' => false, 'name' => 'Bahan Baku']);

        $this->postJson('/api/sales/orders', [
            'partner_id' => $this->customer->id,
            'lines' => [['product_id' => $internal->id, 'quantity' => 1, 'unit_price' => 1]],
        ])->assertUnprocessable()->assertJsonPath('message', 'Bahan Baku tidak ditandai bisa dijual.');
    }

    public function test_list_filters_by_state_and_search(): void
    {
        $quotation = $this->createQuotation();
        $order = $this->createQuotationNumber2();
        $this->postJson("/api/sales/orders/{$order}/confirm");

        $this->getJson('/api/sales/orders?state=draft')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $quotation);
        $this->getJson('/api/sales/orders?state=confirmed')->assertJsonPath('data.0.id', $order);
        $this->getJson('/api/sales/orders?search=andalas')->assertJsonCount(2, 'data');
    }

    public function test_uninstalling_inventory_removes_sales_too(): void
    {
        $this->createQuotation();

        $this->postJson('/api/modules/Inventory/uninstall', ['confirm' => true])
            ->assertOk()
            ->assertJsonPath('uninstalled', ['Sales', 'Inventory']);
        $this->getJson('/api/sales/orders')->assertNotFound();
    }
}
