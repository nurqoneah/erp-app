<?php

namespace App\Core\Console;

use App\Core\Modules\ModuleManager;
use Illuminate\Console\Command;

class ModuleSyncCommand extends Command
{
    protected $signature = 'module:sync';

    protected $description = 'Daftarkan modul baru di folder modules/ dan pasang modul wajib';

    public function handle(ModuleManager $modules): int
    {
        $modules->sync();
        $this->components->info('Modul tersinkron: '.$modules->all()->keys()->implode(', '));

        return self::SUCCESS;
    }
}
