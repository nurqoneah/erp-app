<?php

namespace Modules\Inventory\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;

/**
 * Perpindahan satu produk dari lokasi asal ke tujuan (bagian dari transfer atau penyesuaian stok).
 */
class StockMove extends Model
{
    use HasAudit;

    protected $fillable = [
        'picking_id', 'reference', 'product_id', 'quantity', 'source_location_id', 'dest_location_id',
        'state', 'done_at', 'origin_line_type', 'origin_line_id',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'done_at' => 'datetime'];
    }

    public function picking(): BelongsTo
    {
        return $this->belongsTo(Picking::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    public function destLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'dest_location_id');
    }
}
