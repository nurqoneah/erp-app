<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Models\Uom;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $uom = Uom::query()->where('type', Uom::TYPE_REFERENCE)->first()
            ?? Uom::query()->firstOrFail();

        return [
            'name' => ucfirst(fake()->words(2, true)),
            'sku' => strtoupper(fake()->unique()->bothify('PRD-####')),
            'type' => Product::TYPE_STORABLE,
            'category_id' => ProductCategory::query()->value('id') ?? ProductCategory::query()->create(['name' => 'Semua'])->id,
            'uom_id' => $uom->id,
            'purchase_uom_id' => $uom->id,
            'sale_price' => fake()->numberBetween(5, 500) * 1000,
            'cost' => fake()->numberBetween(1, 300) * 1000,
        ];
    }
}
