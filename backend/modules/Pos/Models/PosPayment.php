<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosPayment extends Model
{
    protected $fillable = ['order_id', 'payment_method_id', 'amount', 'is_change', 'payment_date'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_change' => 'boolean', 'payment_date' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
