<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Administrador tiene acceso total por defecto
        Gate::before(function (User $user) {
            return $user->isAdmin() ? true : null;
        });

        // Directivas Blade para roles
        Blade::if('role', function (string $role) {
            return auth()->check() && auth()->user()->hasRole($role);
        });

        Blade::if('hasanyrole', function (array|string $roles) {
            return auth()->check() && auth()->user()->hasRole((array) $roles);
        });
    }
}
