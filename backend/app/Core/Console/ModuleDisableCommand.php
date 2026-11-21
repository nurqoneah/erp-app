<?php

namespace App\Core\Console;

use App\Core\Modules\ModuleManager;
use Illuminate\Console\Command;
use RuntimeException;

class ModuleDisableCommand extends Command
{
    protected $signature = 'module:disable {module : Nama modul, mis. Sales}';

    protected $description = 'Nonaktifkan modul (gagal jika masih dipakai modul lain)';

    public function handle(ModuleManager $modules): int
    {
        try {
            $modules->disable($this->argument('module'));
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Modul dinonaktifkan.');

        return self::SUCCESS;
    }
}
