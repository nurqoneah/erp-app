<?php

namespace Modules\Pos\Models;

use App\Core\Models\Concerns\HasAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Contacts\Models\Partner;
use Modules\Invoicing\Models\Invoice;

/**
 * Pesanan POS: Baru → Dibayar → Diposting (saat sesi ditutup atau difakturkan).
 */
class PosOrder extends Model
{
    use HasAudit;

    public const ORIGIN = 'pos.order';

    public const STATE_DRAFT = 'draft';

    public const STATE_PAID = 'paid';

    public const STATE_DONE = 'done';

    public const STATE_CANCEL = 'cancel';

    protected $fillable = [
        'name', 'session_id', 'config_id', 'user_id', 'partner_id', 'date_order', 'state',
        'amount_untaxed', 'amount_tax', 'amount_total', 'amount_paid', 'amount_return',
        'to_invoice', 'invoice_id', 'is_refund', 'refunded_order_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_order' => 'datetime',
            'amount_untaxed' => 'decimal:2',
            'amount_tax' => 'decimal:2',
            'amount_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_return' => 'decimal:2',
            'to_invoice' => 'boolean',
            'is_refund' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PosOrderLine::class, 'order_id')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'order_id')->orderBy('id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(PosConfig::class, 'config_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class)->withTrashed();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function refundedOrder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'refunded_order_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'refunded_order_id');
    }
}
