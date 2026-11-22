<?php

namespace Modules\Inventory\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Contacts\Models\Partner;

/**
 * Transfer barang: penerimaan (receipt), pengiriman (delivery), atau internal.
 */
class Picking extends Model
{
    use HasAudit;

    protected $table = 'stock_pickings';

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_DELIVERY = 'delivery';

    public const TYPE_INTERNAL = 'internal';

    public const TYPES = [self::TYPE_RECEIPT, self::TYPE_DELIVERY, self::TYPE_INTERNAL];

    public const STATE_DRAFT = 'draft';

    public const STATE_DONE = 'done';

    public const STATE_CANCELLED = 'cancelled';

    /** Kode penomoran (sequences.php) per tipe transfer. */
    public const SEQUENCES = [
        self::TYPE_RECEIPT => 'inventory.receipt',
        self::TYPE_DELIVERY => 'inventory.delivery',
        self::TYPE_INTERNAL => 'inventory.internal',
    ];

    protected $fillable = [
        'reference', 'type', 'state', 'warehouse_id', 'partner_id', 'source_location_id', 'dest_location_id',
        'scheduled_date', 'done_at', 'origin', 'origin_type', 'origin_id', 'notes',
    ];

    protected function casts(): array
    {
        return ['scheduled_date' => 'date:Y-m-d', 'done_at' => 'datetime'];
    }

    public function moves(): HasMany
    {
        return $this->hasMany(StockMove::class)->orderBy('id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    public function destLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'dest_location_id');
    }

    public function isDraft(): bool
    {
        return $this->state === self::STATE_DRAFT;
    }
}
