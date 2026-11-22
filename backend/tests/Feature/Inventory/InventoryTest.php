<?php

namespace Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Contacts\Models\Partner;
use Modules\Inventory\Events\PickingDone;
use Modules\Inventory\Models\Picking;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\InventoryService;
use Modules\Product\Models\Product;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Product $beras;

    private Product $gelas;

    private StockLocation $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
        $this->installModules('Inventory');
        $this->beras = Product::factory()->create(['name' => 'Beras', 'type' => Product::TYPE_STORABLE]);
        $this->gelas = Product::factory()->create(['name' => 'Gelas Plastik', 'type' => Product::TYPE_CONSUMABLE]);
        $this->stock = Warehouse::query()->firstOrFail()->stockLocation;
    }

    private function onHand(Product $product): float
    {
        return app(InventoryService::class)->onHand([$product->id])[$product->id] ?? 0.0;
    }

    public function test_receipt_increases_stock_and_delivery_decreases_it(): void
    {
        Event::fake([PickingDone::class]);
        $vendor = Partner::factory()->vendor()->create();

        $receipt = $this->postJson('/api/inventory/transfers', [
            'type' => 'receipt',
            'partner_id' => $vendor->id,
            'lines' => [['product_id' => $this->beras->id, 'quantity' => 10], ['product_id' => $this->gelas->id, 'quantity' => 50]],
        ])->assertCreated()
            ->assertJsonPath('data.reference', 'WH/IN/00001')
            ->assertJsonPath('data.source_location.complete_name', 'Mitra/Pemasok')
            ->assertJsonPath('data.dest_location.complete_name', 'WH/Stok')
            ->json('data.id');

        $this->postJson("/api/inventory/transfers/{$receipt}/validate")->assertOk()->assertJsonPath('data.state', 'done');
        Event::assertDispatched(PickingDone::class);

        $this->assertSame(10.0, $this->onHand($this->beras));
        $this->assertSame(0.0, $this->onHand($this->gelas), 'barang habis pakai tidak dilacak stoknya');

        $delivery = $this->postJson('/api/inventory/transfers', [
            'type' => 'delivery',
            'lines' => [['product_id' => $this->beras->id, 'quantity' => 4]],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/inventory/transfers/{$delivery}/validate")->assertOk();

        $this->assertSame(6.0, $this->onHand($this->beras));
        $this->getJson("/api/inventory/stock/summary?product_ids[]={$this->beras->id}")
            ->assertJsonPath("data.{$this->beras->id}", 6);
    }

    public function test_cannot_deliver_more_than_available(): void
    {
        app(InventoryService::class)->adjust($this->stock, [$this->beras->id => 3]);

        $delivery = $this->postJson('/api/inventory/transfers', [
            'type' => 'delivery',
            'lines' => [['product_id' => $this->beras->id, 'quantity' => 5]],
        ])->json('data.id');

        $this->postJson("/api/inventory/transfers/{$delivery}/validate")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Stok tidak cukup di lokasi asal: Beras (tersedia 3, dibutuhkan 5).');

        $this->assertSame(3.0, $this->onHand($this->beras));
        $this->assertSame('draft', Picking::query()->find($delivery)->state);
    }

    public function test_done_or_cancelled_transfer_cannot_be_changed(): void
    {
        $id = $this->postJson('/api/inventory/transfers', [
            'type' => 'internal',
            'lines' => [['product_id' => $this->beras->id, 'quantity' => 1]],
        ])->json('data.id');

        $this->postJson("/api/inventory/transfers/{$id}/cancel")->assertOk()->assertJsonPath('data.state', 'cancelled');
        $this->postJson("/api/inventory/transfers/{$id}/validate")->assertUnprocessable();
        $this->putJson("/api/inventory/transfers/{$id}", ['lines' => [['product_id' => $this->beras->id, 'quantity' => 2]]])
            ->assertUnprocessable();
    }

    public function test_internal_transfer_moves_stock_between_locations(): void
    {
        $rack = $this->postJson('/api/inventory/locations', ['name' => 'Rak A', 'parent_id' => $this->stock->id])
            ->assertCreated()->assertJsonPath('data.complete_name', 'WH/Stok/Rak A')->json('data.id');
        app(InventoryService::class)->adjust($this->stock, [$this->beras->id => 8]);

        $id = $this->postJson('/api/inventory/transfers', [
            'type' => 'internal',
            'dest_location_id' => $rack,
            'lines' => [['product_id' => $this->beras->id, 'quantity' => 5]],
        ])->json('data.id');
        $this->postJson("/api/inventory/transfers/{$id}/validate")->assertOk();

        $stock = collect($this->getJson('/api/inventory/stock')->assertOk()->json('data'))
            ->mapWithKeys(fn ($row) => [$row['location']['complete_name'] => (float) $row['quantity']]);
        $this->assertEquals(['WH/Stok' => 3.0, 'WH/Stok/Rak A' => 5.0], $stock->sortKeys()->all());
        $this->assertSame(8.0, $this->onHand($this->beras));
    }

    public function test_stock_adjustment_sets_counted_quantity(): void
    {
        $this->postJson('/api/inventory/adjustments', [
            'location_id' => $this->stock->id,
            'reason' => 'Opname',
            'lines' => [['product_id' => $this->beras->id, 'quantity' => 12]],
        ])->assertOk()->assertJsonPath('changed', 1);

        $this->postJson('/api/inventory/adjustments', [
            'location_id' => $this->stock->id,
            'lines' => [['product_id' => $this->beras->id, 'quantity' => 9.5]],
        ])->assertOk();

        $this->assertSame(9.5, $this->onHand($this->beras));
        $this->getJson('/api/inventory/adjustments')
            ->assertJsonPath('data.0.change', '-2.5000')
            ->assertJsonPath('data.1.change', '12.0000');

        $this->postJson('/api/inventory/adjustments', [
            'location_id' => $this->stock->id,
            'lines' => [['product_id' => $this->gelas->id, 'quantity' => 1]],
        ])->assertUnprocessable();
    }

    public function test_services_cannot_be_transferred(): void
    {
        $service = Product::factory()->create(['type' => Product::TYPE_SERVICE, 'name' => 'Ongkir']);

        $this->postJson('/api/inventory/transfers', [
            'type' => 'delivery',
            'lines' => [['product_id' => $service->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonPath('message', 'Ongkir adalah jasa dan tidak bisa dipindahkan.');
    }

    public function test_new_warehouse_gets_its_own_stock_location(): void
    {
        $this->postJson('/api/inventory/warehouses', ['name' => 'Gudang Padang', 'code' => 'pdg'])->assertCreated();

        $warehouses = collect($this->getJson('/api/inventory/warehouses')->assertOk()->json('data'))->keyBy('code');
        $this->assertSame('PDG/Stok', $warehouses['PDG']['locations'][0]['complete_name']);

        $this->putJson("/api/inventory/warehouses/{$warehouses['PDG']['id']}", ['name' => 'Gudang Padang', 'is_active' => false])->assertOk();
        $this->putJson("/api/inventory/warehouses/{$warehouses['WH']['id']}", ['name' => 'Utama', 'is_active' => false])
            ->assertUnprocessable();
    }

    public function test_view_only_user_cannot_validate(): void
    {
        $id = $this->postJson('/api/inventory/transfers', [
            'type' => 'internal', 'lines' => [['product_id' => $this->beras->id, 'quantity' => 1]],
        ])->json('data.id');

        $this->actingWithPermissions('inventory.transfer.view');
        $this->getJson("/api/inventory/transfers/{$id}")->assertOk();
        $this->postJson("/api/inventory/transfers/{$id}/validate")->assertForbidden();
    }
}
