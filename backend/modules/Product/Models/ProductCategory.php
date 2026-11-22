<?php

namespace Modules\Product\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori produk bertingkat, mis. "Semua / Makanan / Minuman".
 */
class ProductCategory extends Model
{
    use HasAudit;

    protected $fillable = ['name', 'parent_id'];

    protected static function booted(): void
    {
        static::saving(function (self $category) {
            $category->complete_name = $category->parent
                ? $category->parent->complete_name.' / '.$category->name
                : $category->name;
        });

        // Perbarui nama lengkap turunan bila nama / induk berubah.
        static::saved(function (self $category) {
            if ($category->wasChanged('complete_name')) {
                $category->children()->each(fn (self $child) => $child->save());
            }
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

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /** Apakah $other adalah kategori ini sendiri atau turunannya. */
    public function isSelfOrDescendant(?self $other): bool
    {
        while ($other) {
            if ($other->id === $this->id) {
                return true;
            }
            $other = $other->parent;
        }

        return false;
    }
}
