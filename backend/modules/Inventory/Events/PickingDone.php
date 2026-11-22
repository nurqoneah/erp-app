<?php

namespace Modules\Inventory\Events;

use Modules\Inventory\Models\Picking;

/**
 * Dipicu setelah transfer divalidasi. Modul lain (Penjualan, Pembelian) mendengarkan
 * event ini untuk memperbarui jumlah terkirim / diterima.
 */
class PickingDone
{
    public function __construct(public readonly Picking $picking) {}
}
