<?php

namespace App\Core\Extensions;

/**
 * Model yang bisa ditambah field oleh modul lain (lihat ModuleProvider::extendModel()).
 */
trait Extensible
{
    public function initializeExtensible(): void
    {
        $fields = app(ModelExtensions::class)->fields(static::class);

        if ($fields) {
            $this->mergeFillable(array_keys($fields));
            $this->mergeCasts(array_filter(array_map(fn (array $f) => $f['cast'], $fields)));
        }
    }
}
