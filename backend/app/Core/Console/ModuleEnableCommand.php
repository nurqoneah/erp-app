<?php

namespace App\Core\Console;

use App\Core\Modules\ModuleManager;
use Illuminate\Console\Command;
use RuntimeException;

class ModuleEnableCommand extends Command
{
    protected $signature = 'module:enable {module* : Nama modul, mis. Sales}';

    protected $description = 'Aktifkan modul beserta dependensinya';

    public function handle(ModuleManager $modules): int
    {
        try {
            foreach ($this->argument('module') as $name) {
                $activated = $modules->enable($name);
                $this->components->info($activated
                    ? 'Diaktifkan: '.implode(', ', $activated)
                    : "Modul [{$name}] sudah aktif.");
            }
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
