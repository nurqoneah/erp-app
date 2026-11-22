<?php

namespace Modules\Product\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Satuan ukur. Konversi antar satuan hanya boleh dalam kategori yang sama.
 */
class Uom extends Model
{
    use HasAudit;

    public const TYPE_REFERENCE = 'reference';

    public const TYPE_BIGGER = 'bigger';

    public const TYPE_SMALLER = 'smaller';

    public const TYPES = [self::TYPE_REFERENCE, self::TYPE_BIGGER, self::TYPE_SMALLER];

    protected $fillable = ['category_id', 'name', 'type', 'ratio', 'rounding', 'is_active'];

    protected function casts(): array
    {
        return [
            'ratio' => 'decimal:6',
            'rounding' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(UomCategory::class, 'category_id');
    }

    /** Berapa satuan acuan dalam 1 satuan ini. */
    public function factor(): float
    {
        return match ($this->type) {
            self::TYPE_BIGGER => (float) $this->ratio,
            self::TYPE_SMALLER => 1 / (float) $this->ratio,
            default => 1.0,
        };
    }

    /** Konversi kuantitas dari satuan ini ke satuan $to (harus satu kategori). */
    public function convert(float $quantity, self $to): float
    {
        if ($this->category_id !== $to->category_id) {
            throw new InvalidArgumentException("Satuan {$this->name} dan {$to->name} beda kategori.");
        }

        return $quantity * $this->factor() / $to->factor();
    }
}
