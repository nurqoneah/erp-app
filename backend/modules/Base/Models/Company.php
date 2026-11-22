<?php

namespace Modules\Base\Models;

use App\Core\Models\Concerns\HasAudit;
use Illuminate\Database\Eloquent\Model;

/**
 * Profil perusahaan. Untuk saat ini aplikasi single-company: selalu satu baris.
 */
class Company extends Model
{
    use HasAudit;

    protected $fillable = [
        'name', 'legal_name', 'tax_id', 'email', 'phone', 'website',
        'street', 'city', 'state', 'zip', 'country', 'currency',
    ];

    public static function current(): self
    {
        return static::query()->orderBy('id')->firstOrCreate([], ['name' => config('app.name')]);
    }
}
