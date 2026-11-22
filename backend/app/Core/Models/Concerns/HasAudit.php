<?php

namespace App\Core\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Mengisi `created_by` / `updated_by` otomatis dari user yang login.
 * Kolomnya dibuat dengan `$table->auditColumns()` di migration.
 */
trait HasAudit
{
    public static function bootHasAudit(): void
    {
        static::creating(function ($model) {
            $id = Auth::id();
            $model->created_by ??= $id;
            $model->updated_by ??= $id;
        });

        static::updating(function ($model) {
            if ($id = Auth::id()) {
                $model->updated_by = $id;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
