<?php

namespace App\Core\Modules;

use App\Core\Extensions\ModelExtensions;
use App\Core\Modules\Models\InstalledModule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Base\Models\Sequence;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Manajemen "Apps" ala Odoo: menemukan modul di folder modules/, lalu
 * install (jalankan migration + data awal), upgrade, dan uninstall
 * (hapus tabel, data, permission, dan penomoran modul secara permanen).
 */
class ModuleManager
{
    public const STATE_INSTALLED = 'installed';

    public const STATE_UNINSTALLED = 'uninstalled';

    /** Terpasang, tapi versi di module.json lebih baru atau ada migration baru. */
    public const STATE_TO_UPGRADE = 'to_upgrade';

    /** Modul inti yang otomatis menjadi dependensi semua modul. */
    public const CORE_MODULE = 'Base';

    /** @var Collection<string, Module>|null */
    private ?Collection $modules = null;

    /** @var array<string, InstalledModule>|null */
    private ?array $records = null;

    private ?ModuleMigrator $migrator = null;

    /** @var array<string, true> provider modul yang sudah didaftarkan */
    private array $bootedProviders = [];

    public function __construct(
        private readonly string $modulesPath,
        private readonly Application $app,
    ) {}

    // ------------------------------------------------------------------
    // Membaca modul & status
    // ------------------------------------------------------------------

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
            ?? $this->all()->first(fn (Module $m) => $m->slug === $name || strcasecmp($m->name, $name) === 0);
    }

    public function get(string $name): Module
    {
        return $this->find($name) ?? throw new RuntimeException("Modul [{$name}] tidak ditemukan.");
    }

    public function isInstalled(string $name): bool
    {
        $module = $this->find($name);

        return $module !== null
            && ($this->records()[$module->name]->state ?? null) === self::STATE_INSTALLED;
    }

    /**
     * Nama modul yang terpasang.
     *
     * @return list<string>
     */
    public function installedNames(): array
    {
        return $this->all()->keys()->filter(fn (string $name) => $this->isInstalled($name))->values()->all();
    }

    /**
     * @return Collection<string, Module>
     */
    public function installed(): Collection
    {
        return $this->all()->filter(fn (Module $m) => $this->isInstalled($m->name));
    }

    /**
     * Status untuk ditampilkan: installed | uninstalled | to_upgrade.
     */
    public function state(Module $module): string
    {
        if (! $this->isInstalled($module->name)) {
            return self::STATE_UNINSTALLED;
        }

        return $this->needsUpgrade($module) ? self::STATE_TO_UPGRADE : self::STATE_INSTALLED;
    }

    public function installedVersion(Module $module): ?string
    {
        return $this->records()[$module->name]->installed_version ?? null;
    }

    public function needsUpgrade(Module $module): bool
    {
        return $this->isInstalled($module->name)
            && ($this->installedVersion($module) !== $module->version || $this->pendingMigrations($module) !== []);
    }

    /**
     * Modul terpasang yang (langsung atau tidak langsung) bergantung pada $module.
     *
     * @return list<Module> urutan aman untuk di-uninstall (yang paling bergantung dulu)
     */
    public function installedDependents(Module $module): array
    {
        $result = [];
        $visit = function (Module $target) use (&$visit, &$result) {
            foreach ($this->installed() as $candidate) {
                if (in_array($target->name, $this->dependenciesOf($candidate), true) && ! isset($result[$candidate->name])) {
                    $visit($candidate);
                    $result[$candidate->name] = $candidate;
                }
            }
        };
        $visit($module);

        return array_values($result);
    }

    /**
     * Dependensi langsung, termasuk modul inti (Base) secara implisit.
     *
     * @return list<string>
     */
    public function dependenciesOf(Module $module): array
    {
        $depends = $module->depends;
        if ($module->name !== self::CORE_MODULE && ! in_array(self::CORE_MODULE, $depends, true)) {
            array_unshift($depends, self::CORE_MODULE);
        }

        return $depends;
    }

    /**
     * Urutan modul yang harus terpasang (dependensi dulu, lalu modul itu sendiri).
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
        foreach ($this->dependenciesOf($module) as $dependency) {
            foreach ($this->resolveDependencies($this->get($dependency), [...$stack, $module->name]) as $dep) {
                $ordered[$dep->name] = $dep;
            }
        }
        $ordered[$module->name] = $module;

        return array_values($ordered);
    }

    // ------------------------------------------------------------------
    // Install / upgrade / uninstall
    // ------------------------------------------------------------------

    /**
     * Daftarkan modul baru di disk dan pastikan modul wajib terpasang.
     */
    public function sync(): void
    {
        foreach ($this->all() as $module) {
            InstalledModule::query()->firstOrCreate(['name' => $module->name]);
        }
        InstalledModule::query()->whereNotIn('name', $this->all()->keys())->delete();
        $this->flush();

        foreach ($this->all()->filter(fn (Module $m) => $m->required) as $module) {
            $this->install($module->name);
        }
    }

    /**
     * Install modul beserta dependensinya.
     *
     * @return list<string> modul yang baru dipasang
     */
    public function install(string $name): array
    {
        $installed = [];

        DB::transaction(function () use ($name, &$installed) {
            foreach ($this->resolveDependencies($this->get($name)) as $module) {
                if (! $this->isInstalled($module->name)) {
                    $this->setUp($module);
                    $installed[] = $module->name;
                }
            }
        });

        return $installed;
    }

    /**
     * Jalankan migration baru dan perbarui permission / penomoran / data awal modul terpasang.
     */
    public function upgrade(string $name): void
    {
        $module = $this->get($name);
        if (! $this->isInstalled($module->name)) {
            throw new RuntimeException("Modul [{$module->label}] belum terpasang.");
        }

        DB::transaction(fn () => $this->setUp($module));
    }

    /**
     * Modul yang akan ikut dihapus bila $name di-uninstall (termasuk $name sendiri, paling akhir).
     *
     * @return list<Module>
     */
    public function uninstallPlan(string $name): array
    {
        $module = $this->get($name);

        if ($module->required) {
            throw new RuntimeException("Modul [{$module->label}] wajib dan tidak bisa di-uninstall.");
        }
        if (! $this->isInstalled($module->name)) {
            throw new RuntimeException("Modul [{$module->label}] belum terpasang.");
        }

        return [...$this->installedDependents($module), $module];
    }

    /**
     * Uninstall modul beserta modul yang bergantung padanya. Data dihapus PERMANEN.
     *
     * @return list<string> modul yang di-uninstall
     */
    public function uninstall(string $name): array
    {
        $plan = $this->uninstallPlan($name);

        // PostgreSQL mendukung DDL di dalam transaksi: kalau satu langkah gagal, semua dibatalkan.
        DB::transaction(function () use ($plan) {
            foreach ($plan as $module) {
                $this->tearDown($module);
            }
        });

        return array_map(fn (Module $m) => $m->name, $plan);
    }

    public function flush(): void
    {
        $this->records = null;
    }

    /**
     * Daftarkan service provider modul yang terpasang (dipanggil saat aplikasi boot).
     */
    public function bootInstalledProviders(): void
    {
        foreach ($this->installed() as $module) {
            $this->registerProviders($module);
        }
    }

    /**
     * @return list<string>
     */
    public function pendingMigrations(Module $module): array
    {
        if (! is_dir($module->migrationsPath()) || ! $this->migrator()->repositoryExists()) {
            return [];
        }

        return $this->migrator()->pending($module->migrationsPath());
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function setUp(Module $module): void
    {
        if (is_dir($module->migrationsPath())) {
            $this->migrator()->run([$module->migrationsPath()]);
        }

        foreach (array_keys($module->permissions()) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($module->sequences() as $code => $config) {
            Sequence::query()->firstOrCreate(['code' => $code], [
                'name' => $config['name'] ?? $code,
                'prefix' => $config['prefix'] ?? '',
                'suffix' => $config['suffix'] ?? '',
                'padding' => $config['padding'] ?? 4,
                'reset' => $config['reset'] ?? Sequence::RESET_YEARLY,
                'next_number' => 1,
                'module' => $module->name,
            ]);
        }

        // Provider didaftarkan sebelum seeder, supaya perluasan model sudah aktif.
        $this->registerProviders($module);

        foreach ($module->seeders() as $seeder) {
            $this->app->make($seeder)->run();
        }

        $record = InstalledModule::query()->firstOrNew(['name' => $module->name]);
        $wasInstalled = $record->state === self::STATE_INSTALLED;
        $record->fill([
            'state' => self::STATE_INSTALLED,
            'installed_version' => $module->version,
            'installed_at' => $wasInstalled ? $record->installed_at : now(),
        ])->save();

        $this->flush();
    }

    private function tearDown(Module $module): void
    {
        // Hapus permission milik modul (relasi ke role & user ikut terhapus oleh foreign key).
        $permissions = array_keys($module->permissions());
        if ($permissions) {
            Permission::query()->whereIn('name', $permissions)->where('guard_name', 'web')->delete();
            $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        if (Schema::hasTable('sequences')) {
            Sequence::query()->where('module', $module->name)->delete();
        }

        if (is_dir($module->migrationsPath())) {
            $this->migrator()->rollbackPath($module->migrationsPath());
        }

        $this->app->make(ModelExtensions::class)->forgetModule($module->name);

        InstalledModule::query()->where('name', $module->name)->update([
            'state' => self::STATE_UNINSTALLED,
            'installed_version' => null,
            'installed_at' => null,
        ]);

        $this->flush();
    }

    private function registerProviders(Module $module): void
    {
        foreach ($module->providers as $provider) {
            if (! isset($this->bootedProviders[$provider])) {
                $this->bootedProviders[$provider] = true;
                $this->app->register($provider);
            }
        }
    }

    /**
     * @return array<string, InstalledModule>
     */
    private function records(): array
    {
        if ($this->records === null) {
            $this->records = Schema::hasTable('modules')
                ? InstalledModule::query()->get()->keyBy('name')->all()
                : [];
        }

        return $this->records;
    }

    private function migrator(): ModuleMigrator
    {
        if ($this->migrator === null) {
            $this->migrator = new ModuleMigrator(
                $this->app['migration.repository'],
                $this->app['db'],
                $this->app['files'],
                $this->app['events'],
            );

            if (! $this->migrator->repositoryExists()) {
                $this->migrator->getRepository()->createRepository();
            }
        }

        return $this->migrator;
    }
}
