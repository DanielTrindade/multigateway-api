<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
         // Define gates para cada role
         Gate::define('is-admin', function(User $user) {
            return $user->hasRole('ADMIN');
        });

        Gate::define('is-manager', function(User $user) {
            return $user->hasRole('MANAGER');
        });

        Gate::define('is-finance', function(User $user) {
            return $user->hasRole('FINANCE');
        });

        Gate::define('manage-users', function(User $user) {
            return $user->hasAnyRole(['ADMIN', 'MANAGER']);
        });

        Gate::define('manage-products', function(User $user) {
            return $user->hasAnyRole(['ADMIN', 'MANAGER', 'FINANCE']);
        });

        Gate::define('process-refunds', function(User $user) {
            return $user->hasAnyRole(['ADMIN', 'FINANCE']);
        });
    }
}
