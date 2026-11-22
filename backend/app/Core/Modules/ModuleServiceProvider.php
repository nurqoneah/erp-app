<?php

namespace App\Core\Modules;

use App\Core\Console\ModuleDisableCommand;
use App\Core\Console\ModuleEnableCommand;
use App\Core\Console\ModuleListCommand;
use App\Core\Console\ModuleSyncCommand;
use App\Core\Http\Middleware\EnsureModuleEnabled;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Memuat migration dan route dari setiap modul.
 *
 * Migration dan route semua modul selalu dimuat agar skema database konsisten;
 * akses ke route modul yang nonaktif ditolak oleh middleware `module:{Nama}`.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class, fn () => new ModuleManager(base_path('modules')));
    }

    public function boot(ModuleManager $modules): void
    {
        Route::aliasMiddleware('module', EnsureModuleEnabled::class);

        // $table->auditColumns(): kolom created_by / updated_by (lihat HasAudit).
        Blueprint::macro('auditColumns', function () {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        foreach ($modules->all() as $module) {
            if (is_dir($module->migrationsPath())) {
                $this->loadMigrationsFrom($module->migrationsPath());
            }

            if (! $this->app->routesAreCached() && is_file($module->routesFile())) {
                Route::middleware(['api', 'module:'.$module->name])
                    ->prefix(trim('api/'.$module->routePrefix, '/'))
                    ->name($module->slug.'.')
                    ->group($module->routesFile());
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleSyncCommand::class,
                ModuleListCommand::class,
                ModuleEnableCommand::class,
                ModuleDisableCommand::class,
            ]);
        }
    }
}
