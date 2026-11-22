<?php

namespace Modules\Invoicing\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    use HasAudit;

    public const METHODS = ['cash', 'bank', 'other'];

    protected $fillable = ['invoice_id', 'date', 'amount', 'method', 'reference'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'amount' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
