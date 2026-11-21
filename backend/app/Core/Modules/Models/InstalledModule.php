<?php

namespace App\Core\Modules\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Status modul di database (tabel `modules`).
 */
class InstalledModule extends Model
{
    protected $table = 'modules';

    protected $fillable = ['name', 'version', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
