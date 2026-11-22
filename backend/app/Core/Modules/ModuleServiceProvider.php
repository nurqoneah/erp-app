<?php

namespace App\Core\Modules;

use App\Core\Console\ModuleInstallCommand;
use App\Core\Console\ModuleListCommand;
use App\Core\Console\ModuleSyncCommand;
use App\Core\Console\ModuleUninstallCommand;
use App\Core\Console\ModuleUpgradeCommand;
use App\Core\Extensions\ModelExtensions;
use App\Core\Http\Middleware\EnsureModuleInstalled;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Kernel modul:
 * - route semua modul didaftarkan, tapi ditolak (404) oleh middleware `module:{Nama}` bila belum terpasang;
 * - migration modul TIDAK ikut `php artisan migrate`; dijalankan saat modul di-install / upgrade;
 * - service provider modul hanya didaftarkan bila modul terpasang.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class, fn ($app) => new ModuleManager(base_path('modules'), $app));
        $this->app->singleton(ModelExtensions::class);
    }

    public function boot(ModuleManager $modules): void
    {
        Route::aliasMiddleware('module', EnsureModuleInstalled::class);

        // $table->auditColumns(): kolom created_by / updated_by (lihat HasAudit).
        Blueprint::macro('auditColumns', function () {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        if (! $this->app->routesAreCached()) {
            foreach ($modules->all() as $module) {
                if (is_file($module->routesFile())) {
                    Route::middleware(['api', 'module:'.$module->name])
                        ->prefix(trim('api/'.$module->routePrefix, '/'))
                        ->name($module->slug.'.')
                        ->group($module->routesFile());
                }
            }
        }

        try {
            $modules->bootInstalledProviders();
        } catch (Throwable $e) {
            // Database belum siap (mis. saat `composer install`): lewati perluasan modul.
            report($e);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleSyncCommand::class,
                ModuleListCommand::class,
                ModuleInstallCommand::class,
                ModuleUninstallCommand::class,
                ModuleUpgradeCommand::class,
            ]);
        }
    }
}
