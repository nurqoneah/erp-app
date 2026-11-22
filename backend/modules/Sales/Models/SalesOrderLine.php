<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;

class SalesOrderLine extends Model
{
    protected $fillable = [
        'order_id', 'sequence', 'product_id', 'description', 'quantity', 'unit_price', 'discount', 'tax_rate',
        'subtotal', 'tax_amount', 'total', 'qty_delivered', 'qty_invoiced',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'qty_delivered' => 'decimal:4',
            'qty_invoiced' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** Kebijakan faktur: berdasarkan jumlah dipesan. */
    public function qtyToInvoice(): float
    {
        return round((float) $this->quantity - (float) $this->qty_invoiced, 4);
    }
}
