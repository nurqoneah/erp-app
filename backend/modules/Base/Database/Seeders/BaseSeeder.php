<?php

namespace Modules\Base\Database\Seeders;

use App\Providers\AppServiceProvider;
use Illuminate\Database\Seeder;
use Modules\Base\Models\Company;
use Spatie\Permission\Models\Role;

class BaseSeeder extends Seeder
{
    public function run(): void
    {
        Company::current();
        Role::findOrCreate(AppServiceProvider::SUPER_ADMIN_ROLE, 'web');
    }
}
