<?php

namespace Modules\Inventory\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lokasi stok. Hanya lokasi `internal` yang menyimpan stok milik perusahaan;
 * lokasi `supplier`, `customer`, dan `inventory` adalah lokasi virtual (asal/tujuan barang).
 */
class StockLocation extends Model
{
    use HasAudit;

    public const USAGE_VIEW = 'view';

    public const USAGE_INTERNAL = 'internal';

    public const USAGE_SUPPLIER = 'supplier';

    public const USAGE_CUSTOMER = 'customer';

    /** Lokasi virtual untuk penyesuaian stok (stock opname). */
    public const USAGE_INVENTORY = 'inventory';

    protected $fillable = ['name', 'parent_id', 'usage', 'warehouse_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $location) {
            $location->complete_name = $location->parent
                ? $location->parent->complete_name.'/'.$location->name
                : $location->name;
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function isInternal(): bool
    {
        return $this->usage === self::USAGE_INTERNAL;
    }
}
