<?php

namespace App\Core\Console;

use App\Core\Modules\Module;
use App\Core\Modules\ModuleManager;
use Illuminate\Console\Command;
use RuntimeException;

class ModuleUninstallCommand extends Command
{
    protected $signature = 'module:uninstall {module : Nama modul, mis. Sales} {--force : Lewati konfirmasi}';

    protected $description = 'Uninstall modul (dan modul yang bergantung padanya). Data dihapus PERMANEN.';

    public function handle(ModuleManager $modules): int
    {
        try {
            $plan = $modules->uninstallPlan($this->argument('module'));
            $labels = implode(', ', array_map(fn (Module $m) => $m->label, $plan));

            $this->components->warn("Modul berikut akan di-uninstall dan SEMUA datanya dihapus permanen: {$labels}");
            if (! $this->option('force') && ! $this->confirm('Lanjutkan?')) {
                return self::FAILURE;
            }

            $removed = $modules->uninstall($this->argument('module'));
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Di-uninstall: '.implode(', ', $removed));

        return self::SUCCESS;
    }
}
