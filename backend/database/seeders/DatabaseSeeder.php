<?php

namespace Database\Seeders;

use App\Core\Modules\ModuleManager;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(ModuleManager $modules): void
    {
        $modules->sync();

        $role = Role::findOrCreate(AppServiceProvider::SUPER_ADMIN_ROLE, 'web');

        $admin = User::query()->firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            ['name' => 'Administrator', 'password' => env('ADMIN_PASSWORD', 'password')],
        );
        $admin->assignRole($role);
    }
}
