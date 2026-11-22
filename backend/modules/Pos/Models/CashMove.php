<?php

namespace Modules\Pos\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kas masuk (amount > 0) / kas keluar (amount < 0) selama sesi. */
class CashMove extends Model
{
    use HasAudit;

    protected $table = 'pos_cash_moves';

    protected $fillable = ['session_id', 'amount', 'reason'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }
}
