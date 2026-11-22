<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Product\Models\ProductCategory;
use Modules\Product\Models\Uom;
use Modules\Product\Models\UomCategory;

/**
 * Data awal modul Produk: satuan umum dan kategori "Semua". Idempoten.
 */
class ProductSeeder extends Seeder
{
    public const UOMS = [
        'Unit' => [['Unit', 'reference', 1], ['Lusin', 'bigger', 12], ['Kodi', 'bigger', 20], ['Rim', 'bigger', 500]],
        'Berat' => [['kg', 'reference', 1], ['g', 'smaller', 1000], ['ons', 'smaller', 10], ['ton', 'bigger', 1000]],
        'Volume' => [['L', 'reference', 1], ['mL', 'smaller', 1000]],
        'Panjang' => [['m', 'reference', 1], ['cm', 'smaller', 100], ['km', 'bigger', 1000]],
        'Waktu Kerja' => [['Jam', 'reference', 1], ['Hari', 'bigger', 8]],
    ];

    public function run(): void
    {
        foreach (self::UOMS as $categoryName => $uoms) {
            $category = UomCategory::query()->firstOrCreate(['name' => $categoryName]);
            foreach ($uoms as [$name, $type, $ratio]) {
                Uom::query()->firstOrCreate(
                    ['category_id' => $category->id, 'name' => $name],
                    ['type' => $type, 'ratio' => $ratio],
                );
            }
        }

        if (! ProductCategory::query()->exists()) {
            ProductCategory::query()->create(['name' => 'Semua']);
        }
    }
}
