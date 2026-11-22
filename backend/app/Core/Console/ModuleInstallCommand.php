<?php

namespace App\Core\Console;

use App\Core\Modules\ModuleManager;
use Illuminate\Console\Command;
use RuntimeException;

class ModuleInstallCommand extends Command
{
    protected $signature = 'module:install {module* : Nama modul, mis. Sales}';

    protected $description = 'Install modul beserta dependensinya (jalankan migration & data awal)';

    public function handle(ModuleManager $modules): int
    {
        try {
            foreach ($this->argument('module') as $name) {
                $installed = $modules->install($name);
                $this->components->info($installed
                    ? 'Terpasang: '.implode(', ', $installed)
                    : "Modul [{$name}] sudah terpasang.");
            }
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
