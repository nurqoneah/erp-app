<?php

namespace Modules\Contacts\Models;

use App\Core\Extensions\Extensible;
use App\Core\Models\Concerns\HasAudit;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kontak (partner): perusahaan atau perorangan; bisa pelanggan dan/atau pemasok.
 */
#[UseFactory(PartnerFactory::class)]
class Partner extends Model
{
    use Extensible, HasAudit, HasFactory, SoftDeletes;

    public const TYPE_COMPANY = 'company';

    public const TYPE_PERSON = 'person';

    protected $fillable = [
        'type', 'name', 'parent_id', 'ref', 'job_title', 'email', 'phone', 'mobile', 'website', 'tax_id',
        'street', 'city', 'state', 'zip', 'country', 'is_customer', 'is_vendor', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_customer' => 'boolean',
            'is_vendor' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isCompany(): bool
    {
        return $this->type === self::TYPE_COMPANY;
    }
}
