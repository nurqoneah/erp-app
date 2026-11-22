<?php

namespace App\Core\Modules;

use Illuminate\Database\Migrations\Migrator;

/**
 * Migrator yang bisa menjalankan / membatalkan migration satu folder modul saja.
 */
class ModuleMigrator extends Migrator
{
    /**
     * Migration di folder ini yang belum dijalankan.
     *
     * @return list<string>
     */
    public function pending(string $path): array
    {
        $ran = $this->repository->getRan();

        return array_values(array_diff(array_keys($this->getMigrationFiles([$path])), $ran));
    }

    /**
     * Jalankan `down()` semua migration di folder ini yang sudah dijalankan, urutan terbalik.
     *
     * @return list<string> migration yang dibatalkan
     */
    public function rollbackPath(string $path): array
    {
        $ran = $this->repository->getRan();
        $rolledBack = [];

        foreach (array_reverse($this->getMigrationFiles([$path]), true) as $name => $file) {
            if (in_array($name, $ran, true)) {
                $this->runDown($file, (object) ['migration' => $name], false);
                $rolledBack[] = $name;
            }
        }

        return $rolledBack;
    }
}
