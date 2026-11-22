<?php

namespace Tests;

use App\Core\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function admin(): User
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        Sanctum::actingAs($admin);

        return $admin;
    }

    /** Login sebagai user biasa dengan permission tertentu. */
    protected function actingWithPermissions(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function installModules(string ...$modules): void
    {
        foreach ($modules as $module) {
            app(ModuleManager::class)->install($module);
        }
    }
}
