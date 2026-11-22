<?php

namespace Modules\Product\Models;

use App\Core\Models\Concerns\HasAudit;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    use HasAudit, HasFactory, SoftDeletes;

    /** Barang yang stoknya dilacak. */
    public const TYPE_STORABLE = 'storable';

    /** Barang habis pakai, stok tidak dilacak. */
    public const TYPE_CONSUMABLE = 'consumable';

    public const TYPE_SERVICE = 'service';

    public const TYPES = [self::TYPE_STORABLE, self::TYPE_CONSUMABLE, self::TYPE_SERVICE];

    protected $fillable = [
        'name', 'sku', 'barcode', 'type', 'category_id', 'uom_id', 'purchase_uom_id', 'sale_price', 'cost',
        'can_be_sold', 'can_be_purchased', 'available_in_pos', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'can_be_sold' => 'boolean',
            'can_be_purchased' => 'boolean',
            'available_in_pos' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function purchaseUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'purchase_uom_id');
    }
}
