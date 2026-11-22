<?php

namespace Modules\Pos\Models;

use App\Core\Models\Concerns\HasAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sesi kasir: Kontrol Pembukaan → Berjalan → (Kontrol Penutupan) → Ditutup.
 */
class PosSession extends Model
{
    use HasAudit;

    public const STATE_OPENING_CONTROL = 'opening_control';

    public const STATE_OPENED = 'opened';

    public const STATE_CLOSING_CONTROL = 'closing_control';

    public const STATE_CLOSED = 'closed';

    protected $fillable = [
        'name', 'config_id', 'user_id', 'state', 'opened_at', 'closed_at',
        'cash_register_balance_start', 'cash_register_balance_end', 'cash_register_balance_end_real',
        'cash_register_difference', 'opening_notes', 'closing_notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'cash_register_balance_start' => 'decimal:2',
            'cash_register_balance_end' => 'decimal:2',
            'cash_register_balance_end_real' => 'decimal:2',
            'cash_register_difference' => 'decimal:2',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(PosConfig::class, 'config_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'session_id');
    }

    public function cashMoves(): HasMany
    {
        return $this->hasMany(CashMove::class, 'session_id')->orderBy('id');
    }

    public function isOpened(): bool
    {
        return $this->state === self::STATE_OPENED;
    }
}
