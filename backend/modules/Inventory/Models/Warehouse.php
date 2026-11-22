<?php

namespace Modules\Inventory\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasAudit;

    protected $fillable = ['name', 'code', 'is_active', 'view_location_id', 'stock_location_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Lokasi stok utama gudang, mis. "WH/Stok". */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function viewLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'view_location_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(StockLocation::class);
    }
}
