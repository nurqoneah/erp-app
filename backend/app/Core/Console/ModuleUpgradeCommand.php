<?php

namespace App\Core\Console;

use App\Core\Modules\ModuleManager;
use Illuminate\Console\Command;
use RuntimeException;

class ModuleUpgradeCommand extends Command
{
    protected $signature = 'module:upgrade {module?* : Nama modul; kosongkan untuk semua yang perlu upgrade}';

    protected $description = 'Upgrade modul terpasang: jalankan migration baru & perbarui permission/penomoran/data awal';

    public function handle(ModuleManager $modules): int
    {
        $names = $this->argument('module') ?: $modules->installed()
            ->filter(fn ($m) => $modules->needsUpgrade($m))
            ->keys()
            ->all();

        if (! $names) {
            $this->components->info('Semua modul sudah versi terbaru.');

            return self::SUCCESS;
        }

        try {
            foreach ($names as $name) {
                $modules->upgrade($name);
                $this->components->info("Di-upgrade: {$name}");
            }
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
