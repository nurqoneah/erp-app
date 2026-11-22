<?php

namespace Modules\Pos\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\Warehouse;
use Modules\Pos\Models\PaymentMethod;
use Modules\Pos\Models\PosConfig;

/**
 * Metode pembayaran umum di Indonesia + satu POS "Toko" (seperti data awal Odoo). Idempoten.
 */
class PosSeeder extends Seeder
{
    public function run(): void
    {
        $methods = collect([
            ['Tunai', PaymentMethod::TYPE_CASH, 1],
            ['Kartu Debit/Kredit', PaymentMethod::TYPE_BANK, 2],
            ['QRIS', PaymentMethod::TYPE_BANK, 3],
            ['Transfer Bank', PaymentMethod::TYPE_BANK, 4],
        ])->map(fn ($m) => PaymentMethod::query()->firstOrCreate(['name' => $m[0]], ['type' => $m[1], 'sequence' => $m[2]]));

        if (! PosConfig::query()->exists() && $warehouse = Warehouse::query()->orderBy('id')->first()) {
            $config = PosConfig::query()->create([
                'name' => 'Toko',
                'warehouse_id' => $warehouse->id,
                'receipt_footer' => 'Terima kasih atas kunjungan Anda!',
            ]);
            $config->paymentMethods()->sync($methods->pluck('id'));
        }
    }
}
