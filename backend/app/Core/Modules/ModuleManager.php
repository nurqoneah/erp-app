<?php

namespace App\Core\Modules;

use App\Core\Modules\Models\InstalledModule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menemukan modul di folder modules/, menyimpan status aktif di tabel `modules`,
 * dan menjaga dependensi antar modul (mirip "Apps" di Odoo).
 */
class ModuleManager
{
    private const CACHE_KEY = 'erp.modules.enabled';

    /** @var Collection<string, Module>|null */
    private ?Collection $modules = null;

    public function __construct(private readonly string $modulesPath) {}

    /**
     * Semua modul yang ada di disk, diurutkan berdasarkan `sequence`.
     *
     * @return Collection<string, Module>
     */
    public function all(): Collection
    {
        if ($this->modules === null) {
            $this->modules = collect(glob($this->modulesPath.'/*/module.json') ?: [])
                ->map(fn (string $file) => Module::fromManifest(dirname($file)))
                ->sortBy([['sequence', 'asc'], ['name', 'asc']])
                ->keyBy('name');
        }

        return $this->modules;
    }

    public function find(string $name): ?Module
    {
        return $this->all()->get($name)
            ?? $this->all()->first(fn (Module $m) => $m->slug === $name);
    }

    public function get(string $name): Module
    {
        return $this->find($name) ?? throw new RuntimeException("Modul [{$name}] tidak ditemukan.");
    }

    /**
     * Nama-nama modul yang aktif.
     *
     * @return list<string>
     */
    public function enabledNames(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('modules')) {
                return [];
            }

            return InstalledModule::query()->where('enabled', true)->pluck('name')->all();
        });
    }

    public function isEnabled(string $name): bool
    {
        $module = $this->find($name);

        return $module !== null && in_array($module->name, $this->enabledNames(), true);
    }

    /**
     * @return Collection<string, Module>
     */
    public function enabled(): Collection
    {
        return $this->all()->filter(fn (Module $m) => $this->isEnabled($m->name));
    }

    /**
     * Sinkronkan modul di disk ke database: daftarkan modul baru, aktifkan modul wajib,
     * dan buat permission yang dideklarasikan modul.
     */
    public function sync(): void
    {
        DB::transaction(function () {
            foreach ($this->all() as $module) {
                $record = InstalledModule::query()->firstOrNew(['name' => $module->name]);
                $record->version = $module->version;
                if (! $record->exists) {
                    $record->enabled = $module->required;
                }
                if ($module->required) {
                    $record->enabled = true;
                }
                $record->save();

                $this->syncPermissions($module);
            }

            InstalledModule::query()->whereNotIn('name', $this->all()->keys())->delete();
        });

        $this->flush();
    }

    /**
     * Aktifkan modul beserta semua dependensinya (rekursif).
     *
     * @return list<string> modul yang baru diaktifkan
     */
    public function enable(string $name): array
    {
        $activated = [];

        DB::transaction(function () use ($name, &$activated) {
            foreach ($this->resolveDependencies($this->get($name)) as $module) {
                $record = InstalledModule::query()->firstOrNew(['name' => $module->name]);
                if (! $record->enabled) {
                    $record->fill(['enabled' => true, 'version' => $module->version])->save();
                    $activated[] = $module->name;
                }
                $this->syncPermissions($module);
            }
        });

        $this->flush();

        return $activated;
    }

    public function disable(string $name): void
    {
        $module = $this->get($name);

        if ($module->required) {
            throw new RuntimeException("Modul [{$module->label}] wajib dan tidak bisa dinonaktifkan.");
        }

        $dependents = $this->enabled()
            ->filter(fn (Module $m) => in_array($module->name, $m->depends, true))
            ->map(fn (Module $m) => $m->label);

        if ($dependents->isNotEmpty()) {
            throw new RuntimeException(
                "Modul [{$module->label}] masih dipakai oleh: ".$dependents->implode(', ').'.'
            );
        }

        InstalledModule::query()->where('name', $module->name)->update(['enabled' => false]);
        $this->flush();
    }

    /**
     * Urutan modul yang harus aktif (dependensi dulu, lalu modul itu sendiri).
     *
     * @param  list<string>  $stack
     * @return list<Module>
     */
    public function resolveDependencies(Module $module, array $stack = []): array
    {
        if (in_array($module->name, $stack, true)) {
            throw new RuntimeException('Dependensi melingkar: '.implode(' -> ', [...$stack, $module->name]));
        }

        $ordered = [];
        foreach ($module->depends as $dependency) {
            foreach ($this->resolveDependencies($this->get($dependency), [...$stack, $module->name]) as $dep) {
                $ordered[$dep->name] = $dep;
            }
        }
        $ordered[$module->name] = $module;

        return array_values($ordered);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function syncPermissions(Module $module): void
    {
        foreach (array_keys($module->permissions()) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
