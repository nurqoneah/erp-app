<?php

namespace App\Core\Modules;

use App\Core\Extensions\ModelExtension;
use App\Core\Extensions\ModelExtensions;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider sebuah modul (module.json "providers").
 * Hanya didaftarkan bila modulnya terpasang, jadi aman untuk memperluas modul lain.
 *
 *     class MembershipProvider extends ModuleProvider
 *     {
 *         protected string $module = 'Membership';
 *
 *         public function boot(): void
 *         {
 *             $this->extendModel(Partner::class)->field('member_number', ['nullable', 'string'], label: 'nomor member');
 *         }
 *     }
 */
abstract class ModuleProvider extends ServiceProvider
{
    /** Nama modul pemilik provider ini (sama dengan "name" di module.json). */
    protected string $module;

    /**
     * Tambah field ke model milik modul lain (mirip `_inherit` di Odoo).
     * Kolomnya dibuat lewat migration modul ini.
     *
     * @param  class-string  $model
     */
    protected function extendModel(string $model): ModelExtension
    {
        return new ModelExtension($this->app->make(ModelExtensions::class), $model, $this->module);
    }
}
