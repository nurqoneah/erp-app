<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public const SUPER_ADMIN_ROLE = 'super-admin';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Super admin boleh melakukan apa saja (seperti user Administrator di Odoo).
        Gate::before(fn (User $user) => $user->hasRole(self::SUPER_ADMIN_ROLE) ? true : null);
    }
}
