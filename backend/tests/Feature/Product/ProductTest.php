<?php

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Models\Uom;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
        $this->enableModules('Product');
    }

    private function uom(string $name): Uom
    {
        return Uom::query()->where('name', $name)->firstOrFail();
    }

    public function test_enabling_product_seeds_default_uoms_and_category(): void
    {
        $data = collect($this->getJson('/api/product/uoms')->assertOk()->json('data'))->keyBy('name');

        $this->assertEquals(['Unit', 'Lusin', 'Kodi', 'Rim'], collect($data['Unit']['uoms'])->pluck('name')->all());
        $this->assertSame('Semua', ProductCategory::query()->value('complete_name'));
    }

    public function test_create_product_defaults_purchase_uom(): void
    {
        $category = ProductCategory::query()->firstOrFail();

        $this->postJson('/api/product/products', [
            'name' => 'Kopi Susu',
            'sku' => 'KOPI-01',
            'barcode' => '8991234567890',
            'type' => 'consumable',
            'category_id' => $category->id,
            'uom_id' => $this->uom('Unit')->id,
            'sale_price' => 18000,
            'cost' => 7000,
        ])->assertCreated()
            ->assertJsonPath('data.purchase_uom.name', 'Unit')
            ->assertJsonPath('data.sale_price', '18000.00')
            ->assertJsonPath('data.category.complete_name', 'Semua');
    }

    public function test_purchase_uom_must_share_category(): void
    {
        $this->postJson('/api/product/products', [
            'name' => 'Beras',
            'type' => 'storable',
            'category_id' => ProductCategory::query()->value('id'),
            'uom_id' => $this->uom('kg')->id,
            'purchase_uom_id' => $this->uom('Lusin')->id,
            'sale_price' => 15000,
            'cost' => 12000,
        ])->assertJsonValidationErrors('purchase_uom_id');
    }

    public function test_sku_and_barcode_unique_ignoring_deleted(): void
    {
        $old = Product::factory()->create(['sku' => 'A-1', 'barcode' => '111']);
        $payload = [
            'name' => 'Baru', 'sku' => 'A-1', 'barcode' => '111', 'type' => 'storable',
            'category_id' => $old->category_id, 'uom_id' => $old->uom_id, 'sale_price' => 1, 'cost' => 1,
        ];

        $this->postJson('/api/product/products', $payload)->assertJsonValidationErrors(['sku', 'barcode']);

        $this->deleteJson("/api/product/products/{$old->id}")->assertOk();
        $this->postJson('/api/product/products', $payload)->assertCreated();
    }

    public function test_filter_by_category_includes_subcategories(): void
    {
        $root = ProductCategory::query()->firstOrFail();
        $food = ProductCategory::query()->create(['name' => 'Makanan', 'parent_id' => $root->id]);
        $snack = ProductCategory::query()->create(['name' => 'Snack', 'parent_id' => $food->id]);
        $other = ProductCategory::query()->create(['name' => 'Lainnya', 'parent_id' => $root->id]);
        Product::factory()->create(['name' => 'Keripik', 'category_id' => $snack->id]);
        Product::factory()->create(['name' => 'Sabun', 'category_id' => $other->id]);

        $this->getJson("/api/product/products?category_id={$food->id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Keripik');
    }

    public function test_category_complete_name_cascades_and_prevents_cycles(): void
    {
        $root = ProductCategory::query()->firstOrFail();
        $food = ProductCategory::query()->create(['name' => 'Makanan', 'parent_id' => $root->id]);
        $snack = ProductCategory::query()->create(['name' => 'Snack', 'parent_id' => $food->id]);

        $this->putJson("/api/product/categories/{$food->id}", ['name' => 'Pangan', 'parent_id' => $root->id])->assertOk();
        $this->assertSame('Semua / Pangan / Snack', $snack->fresh()->complete_name);

        $this->putJson("/api/product/categories/{$food->id}", ['name' => 'Pangan', 'parent_id' => $snack->id])
            ->assertJsonValidationErrors('parent_id');

        $this->deleteJson("/api/product/categories/{$food->id}")->assertUnprocessable();
    }

    public function test_view_only_user_cannot_modify(): void
    {
        $this->actingWithPermissions('product.product.view');

        $this->getJson('/api/product/products')->assertOk();
        $this->postJson('/api/product/products', [])->assertForbidden();
    }
}
