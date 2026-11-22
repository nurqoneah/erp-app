<?php

namespace Modules\Purchase\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Contacts\Models\Partner;
use Modules\Product\Models\Product;

/**
 * Permintaan penawaran (RFQ, draft) yang menjadi pesanan pembelian setelah dikonfirmasi.
 */
class PurchaseOrder extends Model
{
    use HasAudit;

    /** Kunci asal yang dipakai di transfer & tagihan (origin_type / origin_line_type). */
    public const ORIGIN = 'purchase.order';

    public const LINE_ORIGIN = 'purchase.order.line';

    public const STATE_DRAFT = 'draft';

    public const STATE_CONFIRMED = 'confirmed';

    public const STATE_CANCELLED = 'cancelled';

    protected $fillable = [
        'number', 'partner_id', 'order_date', 'expected_date', 'state', 'confirmed_at',
        'amount_untaxed', 'amount_tax', 'amount_total', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date:Y-m-d',
            'expected_date' => 'date:Y-m-d',
            'confirmed_at' => 'datetime',
            'amount_untaxed' => 'decimal:2',
            'amount_tax' => 'decimal:2',
            'amount_total' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'order_id')->orderBy('sequence')->orderBy('id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class)->withTrashed();
    }

    /** nothing | pending | done */
    public function receiptStatus(): string
    {
        if ($this->state !== self::STATE_CONFIRMED) {
            return 'nothing';
        }
        $goods = $this->lines->filter(fn (PurchaseOrderLine $l) => $l->product->type !== Product::TYPE_SERVICE);
        if ($goods->isEmpty()) {
            return 'nothing';
        }

        return $goods->every(fn (PurchaseOrderLine $l) => (float) $l->qty_received + 1e-9 >= (float) $l->quantity) ? 'done' : 'pending';
    }

    /** nothing | to_bill | billed */
    public function billStatus(): string
    {
        if ($this->state !== self::STATE_CONFIRMED) {
            return 'nothing';
        }

        return $this->lines->contains(fn (PurchaseOrderLine $l) => $l->qtyToBill() > 0) ? 'to_bill' : 'billed';
    }
}
