<?php

namespace App\Core\Modules\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Status modul di database (tabel `modules`).
 */
class InstalledModule extends Model
{
    protected $table = 'modules';

    public const STATE_INSTALLED = 'installed';

    public const STATE_UNINSTALLED = 'uninstalled';

    protected $fillable = ['name', 'state', 'installed_version', 'installed_at'];

    protected function casts(): array
    {
        return ['installed_at' => 'datetime'];
    }
}
