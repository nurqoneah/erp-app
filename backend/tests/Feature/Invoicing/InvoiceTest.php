<?php

namespace Tests\Feature\Invoicing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contacts\Models\Partner;
use Modules\Product\Models\Product;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Partner $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
        $this->installModules('Invoicing');
        $this->customer = Partner::factory()->create(['name' => 'Toko Andalas']);
        $this->product = Product::factory()->create(['name' => 'Kopi Bubuk']);
    }

    private function createInvoice(array $overrides = []): int
    {
        return $this->postJson('/api/invoicing/invoices', [
            'type' => 'out_invoice',
            'partner_id' => $this->customer->id,
            'invoice_date' => '2026-11-20',
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => 3, 'unit_price' => 33000, 'discount' => 10, 'tax_rate' => 11],
                ['description' => 'Ongkos kirim', 'quantity' => 1, 'unit_price' => 15000],
            ],
            ...$overrides,
        ])->assertCreated()->json('data.id');
    }

    public function test_create_draft_computes_totals_and_has_no_number(): void
    {
        $id = $this->createInvoice();

        $this->getJson("/api/invoicing/invoices/{$id}")
            ->assertOk()
            ->assertJsonPath('data.number', null)
            ->assertJsonPath('data.display_name', 'Faktur draf')
            ->assertJsonPath('data.lines.0.description', 'Kopi Bubuk')
            ->assertJsonPath('data.lines.0.subtotal', '89100.00')
            ->assertJsonPath('data.amount_untaxed', '104100.00')
            ->assertJsonPath('data.amount_tax', '9801.00')
            ->assertJsonPath('data.amount_total', '113901.00');
    }

    public function test_post_assigns_number_and_locks_editing(): void
    {
        $id = $this->createInvoice();

        $this->postJson("/api/invoicing/invoices/{$id}/post")
            ->assertOk()
            ->assertJsonPath('data.state', 'posted')
            ->assertJsonPath('data.number', 'INV/2026/0001')
            ->assertJsonPath('data.due_date', '2026-11-20');

        $this->putJson("/api/invoicing/invoices/{$id}", [
            'partner_id' => $this->customer->id,
            'lines' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 1]],
        ])->assertUnprocessable();
        $this->deleteJson("/api/invoicing/invoices/{$id}")->assertUnprocessable();

        // Kembali ke draf lalu posting ulang: nomor tetap sama.
        $this->postJson("/api/invoicing/invoices/{$id}/reset-to-draft")->assertOk()->assertJsonPath('data.state', 'draft');
        $this->postJson("/api/invoicing/invoices/{$id}/post")->assertJsonPath('data.number', 'INV/2026/0001');
    }

    public function test_vendor_bill_uses_its_own_sequence(): void
    {
        $vendor = Partner::factory()->vendor()->create();
        $id = $this->createInvoice(['type' => 'in_invoice', 'partner_id' => $vendor->id, 'vendor_reference' => 'FV-889']);

        $this->postJson("/api/invoicing/invoices/{$id}/post")->assertJsonPath('data.number', 'BILL/2026/0001');
        $this->getJson('/api/invoicing/invoices?type=in_invoice')->assertJsonCount(1, 'data');
        $this->getJson('/api/invoicing/invoices?type=out_invoice')->assertJsonCount(0, 'data');
    }

    public function test_partial_and_full_payment(): void
    {
        $id = $this->createInvoice();
        $this->postJson("/api/invoicing/invoices/{$id}/payments", ['amount' => 1000, 'date' => '2026-11-21', 'method' => 'cash'])
            ->assertUnprocessable(); // belum diposting

        $this->postJson("/api/invoicing/invoices/{$id}/post");
        $this->postJson("/api/invoicing/invoices/{$id}/payments", ['amount' => 50000, 'date' => '2026-11-21', 'method' => 'bank'])
            ->assertOk()
            ->assertJsonPath('data.payment_state', 'partial')
            ->assertJsonPath('data.amount_due', '63901.00');

        $this->postJson("/api/invoicing/invoices/{$id}/payments", ['amount' => 70000, 'date' => '2026-11-22', 'method' => 'cash'])
            ->assertUnprocessable();

        $this->postJson("/api/invoicing/invoices/{$id}/payments", ['amount' => 63901, 'date' => '2026-11-22', 'method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.payment_state', 'paid')
            ->assertJsonCount(2, 'data.payments');

        $this->postJson("/api/invoicing/invoices/{$id}/cancel")->assertUnprocessable();
    }

    public function test_cancel_and_delete_draft(): void
    {
        $draft = $this->createInvoice();
        $this->deleteJson("/api/invoicing/invoices/{$draft}")->assertOk();
        $this->assertDatabaseMissing('invoices', ['id' => $draft]);

        $posted = $this->createInvoice();
        $this->postJson("/api/invoicing/invoices/{$posted}/post");
        $this->postJson("/api/invoicing/invoices/{$posted}/cancel")->assertOk()->assertJsonPath('data.state', 'cancelled');
    }

    public function test_validation_messages(): void
    {
        $this->postJson('/api/invoicing/invoices', [
            'type' => 'out_invoice',
            'partner_id' => $this->customer->id,
            'lines' => [['quantity' => 0, 'unit_price' => -1]],
        ])->assertJsonValidationErrors(['lines.0.description', 'lines.0.quantity', 'lines.0.unit_price']);
    }

    public function test_payment_requires_payment_permission(): void
    {
        $id = $this->createInvoice();
        $this->postJson("/api/invoicing/invoices/{$id}/post");

        $this->actingWithPermissions('invoicing.invoice.view', 'invoicing.invoice.manage');
        $this->postJson("/api/invoicing/invoices/{$id}/payments", ['amount' => 1, 'date' => '2026-11-21', 'method' => 'cash'])
            ->assertForbidden();
    }
}
