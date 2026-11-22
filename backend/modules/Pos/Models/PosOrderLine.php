<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Product\Models\Product;

class PosOrderLine extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'description', 'quantity', 'unit_price', 'discount', 'tax_rate',
        'subtotal', 'tax_amount', 'total', 'refunded_line_id',
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
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** Baris retur yang merujuk baris ini. */
    public function refundLines(): HasMany
    {
        return $this->hasMany(self::class, 'refunded_line_id');
    }
}
