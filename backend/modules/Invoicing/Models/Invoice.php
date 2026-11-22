<?php

namespace Modules\Invoicing\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Contacts\Models\Partner;

/**
 * Faktur pelanggan (out_invoice) atau tagihan pemasok (in_invoice).
 */
class Invoice extends Model
{
    use HasAudit;

    public const TYPE_CUSTOMER = 'out_invoice';

    public const TYPE_VENDOR = 'in_invoice';

    public const TYPES = [self::TYPE_CUSTOMER, self::TYPE_VENDOR];

    public const STATE_DRAFT = 'draft';

    public const STATE_POSTED = 'posted';

    public const STATE_CANCELLED = 'cancelled';

    public const PAYMENT_NOT_PAID = 'not_paid';

    public const PAYMENT_PARTIAL = 'partial';

    public const PAYMENT_PAID = 'paid';

    protected $fillable = [
        'type', 'number', 'partner_id', 'invoice_date', 'due_date', 'vendor_reference', 'state', 'payment_state',
        'origin', 'origin_type', 'origin_id', 'amount_untaxed', 'amount_tax', 'amount_total', 'amount_paid', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'amount_untaxed' => 'decimal:2',
            'amount_tax' => 'decimal:2',
            'amount_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sequence')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('date')->orderBy('id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class)->withTrashed();
    }

    public function amountDue(): float
    {
        return round((float) $this->amount_total - (float) $this->amount_paid, 2);
    }

    public function isCustomer(): bool
    {
        return $this->type === self::TYPE_CUSTOMER;
    }

    public function label(): string
    {
        return $this->number ?? ($this->isCustomer() ? 'Faktur draf' : 'Tagihan draf');
    }
}
