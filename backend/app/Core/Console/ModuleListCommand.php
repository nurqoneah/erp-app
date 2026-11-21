<?php

namespace App\Core\Console;

use App\Core\Modules\Module;
use App\Core\Modules\ModuleManager;
use Illuminate\Console\Command;

class ModuleListCommand extends Command
{
    protected $signature = 'module:list';

    protected $description = 'Tampilkan semua modul dan statusnya';

    public function handle(ModuleManager $modules): int
    {
        $this->table(
            ['Modul', 'Label', 'Versi', 'Dependensi', 'Status'],
            $modules->all()->map(fn (Module $m) => [
                $m->name,
                $m->label,
                $m->version,
                implode(', ', $m->depends) ?: '-',
                $modules->isEnabled($m->name) ? 'aktif' : 'nonaktif',
            ])->values()->all(),
        );

        return self::SUCCESS;
    }
}
