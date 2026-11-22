<?php

namespace Modules\Product\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UomCategory extends Model
{
    use HasAudit;

    protected $fillable = ['name'];

    public function uoms(): HasMany
    {
        return $this->hasMany(Uom::class, 'category_id');
    }
}
