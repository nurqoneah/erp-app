<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\InventoryService;

/**
 * Lokasi virtual (pemasok, pelanggan, penyesuaian) dan gudang utama. Idempoten.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $partners = $this->location('Mitra', StockLocation::USAGE_VIEW);
        $this->location('Pemasok', StockLocation::USAGE_SUPPLIER, $partners);
        $this->location('Pelanggan', StockLocation::USAGE_CUSTOMER, $partners);
        $virtual = $this->location('Virtual', StockLocation::USAGE_VIEW);
        $this->location('Penyesuaian Stok', StockLocation::USAGE_INVENTORY, $virtual);

        if (! Warehouse::query()->exists()) {
            app(InventoryService::class)->createWarehouse('Gudang Utama', 'WH');
        }
    }

    private function location(string $name, string $usage, ?StockLocation $parent = null): StockLocation
    {
        return StockLocation::query()->firstOrCreate(
            ['name' => $name, 'parent_id' => $parent?->id, 'warehouse_id' => null],
            ['usage' => $usage],
        );
    }
}
