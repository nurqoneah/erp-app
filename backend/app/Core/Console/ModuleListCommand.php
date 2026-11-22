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
        $labels = [
            ModuleManager::STATE_INSTALLED => 'terpasang',
            ModuleManager::STATE_UNINSTALLED => '-',
            ModuleManager::STATE_TO_UPGRADE => 'perlu upgrade',
        ];

        $this->table(
            ['Modul', 'Label', 'Versi', 'Terpasang', 'Dependensi', 'Status'],
            $modules->all()->map(fn (Module $m) => [
                $m->name,
                $m->label,
                $m->version,
                $modules->installedVersion($m) ?? '-',
                implode(', ', $m->depends) ?: '-',
                $labels[$modules->state($m)],
            ])->values()->all(),
        );

        return self::SUCCESS;
    }
}
