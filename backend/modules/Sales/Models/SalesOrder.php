<?php

namespace Modules\Sales\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Contacts\Models\Partner;
use Modules\Product\Models\Product;

/**
 * Penawaran (draft) yang menjadi pesanan penjualan setelah dikonfirmasi.
 */
class SalesOrder extends Model
{
    use HasAudit;

    /** Kunci asal yang dipakai di transfer & faktur (origin_type / origin_line_type). */
    public const ORIGIN = 'sales.order';

    public const LINE_ORIGIN = 'sales.order.line';

    public const STATE_DRAFT = 'draft';

    public const STATE_CONFIRMED = 'confirmed';

    public const STATE_CANCELLED = 'cancelled';

    protected $fillable = [
        'number', 'partner_id', 'order_date', 'validity_date', 'state', 'confirmed_at',
        'amount_untaxed', 'amount_tax', 'amount_total', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date:Y-m-d',
            'validity_date' => 'date:Y-m-d',
            'confirmed_at' => 'datetime',
            'amount_untaxed' => 'decimal:2',
            'amount_tax' => 'decimal:2',
            'amount_total' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class, 'order_id')->orderBy('sequence')->orderBy('id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class)->withTrashed();
    }

    /** nothing | pending | done */
    public function deliveryStatus(): string
    {
        if ($this->state !== self::STATE_CONFIRMED) {
            return 'nothing';
        }
        $goods = $this->lines->filter(fn (SalesOrderLine $l) => $l->product->type !== Product::TYPE_SERVICE);
        if ($goods->isEmpty()) {
            return 'nothing';
        }

        return $goods->every(fn (SalesOrderLine $l) => (float) $l->qty_delivered + 1e-9 >= (float) $l->quantity) ? 'done' : 'pending';
    }

    /** nothing | to_invoice | invoiced */
    public function invoiceStatus(): string
    {
        if ($this->state !== self::STATE_CONFIRMED) {
            return 'nothing';
        }

        return $this->lines->contains(fn (SalesOrderLine $l) => $l->qtyToInvoice() > 0) ? 'to_invoice' : 'invoiced';
    }
}
