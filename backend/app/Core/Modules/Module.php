<?php

namespace App\Core\Modules;

/**
 * Representasi satu modul ERP, dibaca dari modules/{Nama}/module.json.
 */
final class Module
{
    /**
     * @param  list<string>  $depends
     * @param  list<array{label: string, path: string, permission?: string|null}>  $menu
     * @param  list<class-string>  $seeders
     * @param  list<class-string<ModuleProvider>>  $providers  dijalankan hanya bila modul terpasang
     */
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly string $label,
        public readonly string $description,
        public readonly string $version,
        public readonly array $depends,
        public readonly bool $required,
        public readonly int $sequence,
        public readonly ?string $icon,
        public readonly string $routePrefix,
        public readonly array $menu,
        public readonly string $path,
        public readonly array $seeders = [],
        public readonly array $providers = [],
        public readonly string $author = '',
        public readonly string $category = '',
    ) {}

    public static function fromManifest(string $path): self
    {
        $manifest = json_decode((string) file_get_contents($path.'/module.json'), true, flags: JSON_THROW_ON_ERROR);
        $name = $manifest['name'] ?? basename($path);
        $slug = $manifest['slug'] ?? strtolower($name);

        return new self(
            name: $name,
            slug: $slug,
            label: $manifest['label'] ?? $name,
            description: $manifest['description'] ?? '',
            version: $manifest['version'] ?? '0.1.0',
            depends: array_values($manifest['depends'] ?? []),
            required: (bool) ($manifest['required'] ?? false),
            sequence: (int) ($manifest['sequence'] ?? 100),
            icon: $manifest['icon'] ?? null,
            routePrefix: $manifest['route_prefix'] ?? $slug,
            menu: array_values($manifest['menu'] ?? []),
            path: $path,
            seeders: array_values($manifest['seeders'] ?? []),
            providers: array_values($manifest['providers'] ?? []),
            author: $manifest['author'] ?? '',
            category: $manifest['category'] ?? '',
        );
    }

    public function migrationsPath(): string
    {
        return $this->path.'/Database/Migrations';
    }

    public function routesFile(): string
    {
        return $this->path.'/routes/api.php';
    }

    /**
     * Penomoran dokumen yang dideklarasikan modul (sequences.php):
     * ['sales.order' => ['name' => 'Pesanan Penjualan', 'prefix' => 'SO/{YYYY}/', 'padding' => 4, 'reset' => 'yearly']].
     *
     * @return array<string, array<string, mixed>>
     */
    public function sequences(): array
    {
        $file = $this->path.'/sequences.php';

        return is_file($file) ? require $file : [];
    }

    /**
     * Class seeder data awal modul (module.json "seeders"); dijalankan saat modul diaktifkan.
     * Seeder harus idempoten (aman dijalankan berulang).
     *
     * @return list<class-string>
     */
    public function seeders(): array
    {
        return $this->seeders;
    }

    /**
     * Daftar permission modul: ['contacts.partner.view' => 'Lihat kontak', ...].
     *
     * @return array<string, string>
     */
    public function permissions(): array
    {
        $file = $this->path.'/permissions.php';

        return is_file($file) ? require $file : [];
    }
}
