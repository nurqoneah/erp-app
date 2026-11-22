<?php

namespace Tests\Feature\Pos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contacts\Models\Partner;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\InventoryService;
use Modules\Pos\Models\PaymentMethod;
use Modules\Pos\Models\PosConfig;
use Modules\Pos\Models\PosSession;
use Modules\Product\Models\Product;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private PosConfig $config;

    private Product $kopi;

    private Product $roti;

    private int $cash;

    private int $qris;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
        $this->installModules('Pos');
        $this->config = PosConfig::query()->firstOrFail();
        $this->kopi = Product::factory()->create(['name' => 'Kopi Susu', 'type' => Product::TYPE_STORABLE, 'sale_price' => 18000, 'barcode' => '899001']);
        $this->roti = Product::factory()->create(['name' => 'Roti Bakar', 'type' => Product::TYPE_CONSUMABLE, 'sale_price' => 15000]);
        $this->cash = PaymentMethod::query()->where('type', 'cash')->value('id');
        $this->qris = PaymentMethod::query()->where('name', 'QRIS')->value('id');
        app(InventoryService::class)->adjust(Warehouse::query()->first()->stockLocation, [$this->kopi->id => 10]);
    }

    private function openSession(float $cash = 200000): int
    {
        $id = $this->postJson("/api/pos/configs/{$this->config->id}/open")
            ->assertOk()->assertJsonPath('data.state', 'opening_control')->json('data.id');
        $this->postJson("/api/pos/sessions/{$id}/opening", ['cash' => $cash, 'notes' => 'Shift pagi'])
            ->assertOk()->assertJsonPath('data.state', 'opened')->assertJsonPath('data.name', 'POS/00001');

        return $id;
    }

    private function sell(int $session, array $lines, array $payments, array $extra = [])
    {
        return $this->postJson("/api/pos/sessions/{$session}/orders", [
            'lines' => $lines,
            'payments' => $payments,
            ...$extra,
        ]);
    }

    private function onHand(Product $product): float
    {
        return app(InventoryService::class)->onHand([$product->id])[$product->id] ?? 0.0;
    }

    public function test_only_one_open_session_per_pos(): void
    {
        $first = $this->openSession();

        $this->postJson("/api/pos/configs/{$this->config->id}/open")->assertOk()->assertJsonPath('data.id', $first);
        $this->postJson("/api/pos/sessions/{$first}/opening", ['cash' => 0])->assertUnprocessable();
    }

    public function test_cash_sale_with_change_reduces_stock_immediately(): void
    {
        $session = $this->openSession();

        $this->sell($session, [
            ['product_id' => $this->kopi->id, 'quantity' => 2, 'unit_price' => 18000],
            ['product_id' => $this->roti->id, 'quantity' => 1, 'unit_price' => 15000, 'discount' => 10],
        ], [['payment_method_id' => $this->cash, 'amount' => 100000]])
            ->assertCreated()
            ->assertJsonPath('data.name', 'POS/'.now()->format('Ym').'/00001')
            ->assertJsonPath('data.state', 'paid')
            ->assertJsonPath('data.amount_total', '49500.00')
            ->assertJsonPath('data.amount_return', '50500.00')
            ->assertJsonPath('data.payments.1.is_change', true)
            ->assertJsonPath('data.payments.1.amount', '-50500.00');

        $this->assertSame(8.0, $this->onHand($this->kopi));
    }

    public function test_stock_may_go_negative_like_odoo(): void
    {
        $session = $this->openSession();

        $this->sell($session, [['product_id' => $this->kopi->id, 'quantity' => 12, 'unit_price' => 18000]], [
            ['payment_method_id' => $this->qris, 'amount' => 216000],
        ])->assertCreated();

        $this->assertSame(-2.0, $this->onHand($this->kopi));
    }

    public function test_payment_rules(): void
    {
        $session = $this->openSession();
        $line = [['product_id' => $this->kopi->id, 'quantity' => 1, 'unit_price' => 18000]];

        $this->sell($session, $line, [['payment_method_id' => $this->cash, 'amount' => 10000]])
            ->assertUnprocessable()->assertJsonPath('message', 'Pembayaran kurang 8.000,00.');
        $this->sell($session, $line, [['payment_method_id' => $this->qris, 'amount' => 20000]])
            ->assertUnprocessable()->assertJsonPath('message', 'Kembalian hanya bisa diberikan untuk pembayaran tunai.');

        // Split: QRIS 10.000 + tunai 10.000 → kembalian 2.000 dari tunai.
        $this->sell($session, $line, [
            ['payment_method_id' => $this->qris, 'amount' => 10000],
            ['payment_method_id' => $this->cash, 'amount' => 10000],
        ])->assertCreated()->assertJsonPath('data.amount_return', '2000.00');
    }

    public function test_invoiced_order_requires_customer_and_is_paid_invoice(): void
    {
        $session = $this->openSession();
        $customer = Partner::factory()->create(['name' => 'PT Pelanggan']);
        $line = [['product_id' => $this->kopi->id, 'quantity' => 1, 'unit_price' => 18000]];

        $this->sell($session, $line, [['payment_method_id' => $this->qris, 'amount' => 18000]], ['to_invoice' => true])
            ->assertUnprocessable()->assertJsonPath('message', 'Pilih pelanggan untuk membuat faktur.');

        $order = $this->sell($session, $line, [['payment_method_id' => $this->cash, 'amount' => 20000]], [
            'to_invoice' => true, 'partner_id' => $customer->id,
        ])->assertCreated()->assertJsonPath('data.state', 'done')->json('data');

        $this->getJson("/api/invoicing/invoices/{$order['invoice']['id']}")
            ->assertJsonPath('data.state', 'posted')
            ->assertJsonPath('data.payment_state', 'paid')
            ->assertJsonPath('data.amount_total', '18000.00')
            ->assertJsonPath('data.origin', $order['name']);
    }

    public function test_refund_returns_stock_and_limits_quantity(): void
    {
        $session = $this->openSession();
        $order = $this->sell($session, [['product_id' => $this->kopi->id, 'quantity' => 3, 'unit_price' => 18000]], [
            ['payment_method_id' => $this->cash, 'amount' => 54000],
        ])->json('data');
        $lineId = $order['lines'][0]['id'];

        $this->sell($session, [['product_id' => $this->kopi->id, 'quantity' => -4, 'unit_price' => 18000, 'refunded_line_id' => $lineId]], [
            ['payment_method_id' => $this->cash, 'amount' => -72000],
        ])->assertUnprocessable()->assertJsonPath('message', 'Jumlah retur Kopi Susu maksimal 3.');

        // Harga retur selalu mengikuti pesanan asal, walau klien mengirim harga lain.
        $this->sell($session, [['product_id' => $this->kopi->id, 'quantity' => -2, 'unit_price' => 1, 'refunded_line_id' => $lineId]], [
            ['payment_method_id' => $this->cash, 'amount' => -36000],
        ])->assertCreated()
            ->assertJsonPath('data.is_refund', true)
            ->assertJsonPath('data.amount_total', '-36000.00')
            ->assertJsonPath('data.refunded_order.name', $order['name']);

        $this->assertSame(9.0, $this->onHand($this->kopi));
        $this->getJson("/api/pos/orders/{$order['id']}")->assertJsonPath('data.lines.0.refundable_quantity', 1);
    }

    public function test_cash_moves_and_closing_with_difference(): void
    {
        $session = $this->openSession(200000);
        $this->sell($session, [['product_id' => $this->kopi->id, 'quantity' => 1, 'unit_price' => 18000]], [
            ['payment_method_id' => $this->cash, 'amount' => 50000],
        ]);
        $this->sell($session, [['product_id' => $this->roti->id, 'quantity' => 2, 'unit_price' => 15000]], [
            ['payment_method_id' => $this->qris, 'amount' => 30000],
        ]);
        $this->postJson("/api/pos/sessions/{$session}/cash-moves", ['type' => 'out', 'amount' => 25000, 'reason' => 'Beli es batu'])->assertCreated();
        $this->postJson("/api/pos/sessions/{$session}/cash-moves", ['type' => 'in', 'amount' => 5000, 'reason' => 'Tambah receh'])->assertCreated();

        $summary = $this->getJson("/api/pos/sessions/{$session}")->assertOk()->json('summary');
        $this->assertSame(['count' => 2, 'amount' => 48000], $summary['orders']);
        $this->assertSame(198000, (int) $summary['cash']['expected']); // 200.000 + 18.000 - 25.000 + 5.000
        $this->assertSame(30000, (int) collect($summary['non_cash'])->firstWhere('name', 'QRIS')['amount']);

        $this->postJson("/api/pos/sessions/{$session}/close", ['counted_cash' => 197500, 'notes' => 'Kurang 500'])
            ->assertOk()
            ->assertJsonPath('data.state', 'closed')
            ->assertJsonPath('data.cash_register_difference', '-500.00');

        $this->getJson("/api/pos/orders?session_id={$session}&state=done")->assertJsonCount(2, 'data');
        $this->sell($session, [['product_id' => $this->kopi->id, 'quantity' => 1, 'unit_price' => 18000]], [
            ['payment_method_id' => $this->cash, 'amount' => 18000],
        ])->assertUnprocessable();

        // Sesi berikutnya menyarankan kas awal = kas terhitung sesi sebelumnya.
        $next = $this->postJson("/api/pos/configs/{$this->config->id}/open")->json('data.id');
        $this->getJson("/api/pos/sessions/{$next}")->assertJsonPath('suggested_opening_cash', 197500);
    }

    public function test_maximum_difference_needs_manager(): void
    {
        $this->config->update(['set_maximum_difference' => true, 'amount_authorized_diff' => 1000]);
        $session = $this->openSession(100000);

        $cashier = $this->actingWithPermissions('pos.session.use', 'pos.session.view');
        $this->postJson("/api/pos/sessions/{$session}/close", ['counted_cash' => 95000])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Selisih kas -5.000,00 melebihi batas 1.000,00. Hitung ulang atau minta manajer POS menutup sesi.');
        $this->postJson("/api/pos/sessions/{$session}/close", ['counted_cash' => 99500])->assertOk();
        $this->assertSame(PosSession::STATE_CLOSED, PosSession::query()->find($session)->state);
        $this->assertNotNull($cashier);
    }

    public function test_load_returns_only_pos_products(): void
    {
        Product::factory()->create(['name' => 'Bahan Baku', 'available_in_pos' => false]);
        $session = $this->openSession();

        $names = collect($this->getJson("/api/pos/sessions/{$session}/load")->assertOk()->json('products'))->pluck('name');
        $this->assertTrue($names->contains('Kopi Susu'));
        $this->assertFalse($names->contains('Bahan Baku'));
    }

    public function test_cashier_without_permission_cannot_use_pos(): void
    {
        $this->actingWithPermissions('pos.order.view');

        $this->postJson("/api/pos/configs/{$this->config->id}/open")->assertForbidden();
    }
}
