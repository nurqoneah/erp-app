<?php

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Modules\Product\Models\Uom;
use Modules\Product\Models\UomCategory;
use Tests\TestCase;

class UomTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
        $this->installModules('Product');
    }

    public function test_conversion_within_category(): void
    {
        $lusin = Uom::query()->where('name', 'Lusin')->firstOrFail();
        $unit = Uom::query()->where('name', 'Unit')->firstOrFail();
        $g = Uom::query()->where('name', 'g')->firstOrFail();
        $ton = Uom::query()->where('name', 'ton')->firstOrFail();

        $this->assertEqualsWithDelta(24.0, $lusin->convert(2, $unit), 1e-9);
        $this->assertEqualsWithDelta(1_000_000.0, $ton->convert(1, $g), 1e-6);

        $this->expectException(\InvalidArgumentException::class);
        $unit->convert(1, $g);
    }

    public function test_create_category_with_reference_and_add_uoms(): void
    {
        $categoryId = $this->postJson('/api/product/uom-categories', ['name' => 'Kemasan', 'reference_name' => 'Pak'])
            ->assertCreated()->json('data.id');

        $this->postJson('/api/product/uoms', [
            'category_id' => $categoryId, 'name' => 'Karton', 'type' => 'bigger', 'ratio' => 24,
        ])->assertCreated();

        $this->postJson('/api/product/uoms', [
            'category_id' => $categoryId, 'name' => 'Pak 2', 'type' => 'reference', 'ratio' => 1,
        ])->assertJsonValidationErrors('type');

        $this->postJson('/api/product/uoms', [
            'category_id' => $categoryId, 'name' => 'Karton', 'type' => 'bigger', 'ratio' => 12,
        ])->assertJsonValidationErrors('name');
    }

    public function test_cannot_move_used_uom_to_other_category(): void
    {
        $unit = Uom::query()->where('name', 'Unit')->firstOrFail();
        Product::factory()->create(['uom_id' => $unit->id, 'purchase_uom_id' => $unit->id]);
        $weight = UomCategory::query()->where('name', 'Berat')->firstOrFail();

        $this->putJson("/api/product/uoms/{$unit->id}", [
            'category_id' => $weight->id, 'name' => 'Unit', 'type' => 'reference', 'ratio' => 1,
        ])->assertUnprocessable();
    }
}
