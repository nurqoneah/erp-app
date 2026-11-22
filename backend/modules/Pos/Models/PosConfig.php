<?php

namespace Modules\Pos\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Inventory\Models\Warehouse;

/**
 * Satu "Point of Sale" (mesin kasir / toko), seperti pos.config di Odoo.
 */
class PosConfig extends Model
{
    use HasAudit;

    protected $fillable = [
        'name', 'warehouse_id', 'receipt_header', 'receipt_footer',
        'set_maximum_difference', 'amount_authorized_diff', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'set_maximum_difference' => 'boolean',
            'amount_authorized_diff' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function paymentMethods(): BelongsToMany
    {
        return $this->belongsToMany(PaymentMethod::class, 'pos_config_payment_method', 'pos_config_id', 'pos_payment_method_id')
            ->orderBy('sequence')->orderBy('pos_payment_methods.id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PosSession::class, 'config_id');
    }

    /** Sesi yang belum ditutup (maksimal satu per POS). */
    public function currentSession(): HasOne
    {
        return $this->hasOne(PosSession::class, 'config_id')->where('state', '!=', PosSession::STATE_CLOSED)->latestOfMany();
    }

    /** Sesi terakhir yang sudah ditutup (kas akhirnya jadi saran kas awal sesi berikutnya). */
    public function lastClosedSession(): HasOne
    {
        return $this->hasOne(PosSession::class, 'config_id')->where('state', PosSession::STATE_CLOSED)->latestOfMany('closed_at');
    }

    /** Kontrol kas aktif bila POS punya metode pembayaran tunai. */
    public function cashControl(): bool
    {
        return $this->paymentMethods->contains(fn (PaymentMethod $m) => $m->isCash());
    }
}
