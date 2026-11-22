<?php

namespace Modules\Invoicing\Events;

use Modules\Invoicing\Models\Invoice;

/**
 * Dipicu setiap kali faktur dibuat, diubah, diposting, atau dibatalkan,
 * agar modul asal (Penjualan, Pembelian) bisa menghitung ulang jumlah yang sudah difakturkan.
 */
class InvoiceChanged
{
    public function __construct(public readonly Invoice $invoice) {}
}
