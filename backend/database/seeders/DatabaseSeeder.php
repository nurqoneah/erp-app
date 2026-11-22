<?php

namespace Database\Seeders;

use App\Core\Modules\ModuleManager;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(ModuleManager $modules): void
    {
        // Mendaftarkan modul + menjalankan seeder modul aktif (Base: perusahaan & role super-admin).
        $modules->sync();

        $admin = User::query()->firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            ['name' => 'Administrator', 'password' => env('ADMIN_PASSWORD', 'password')],
        );
        $admin->assignRole(AppServiceProvider::SUPER_ADMIN_ROLE);
    }
}
