<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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
        // Older MySQL on WAMP limits index keys to 1000 bytes; 191 utf8mb4 chars fit.
        Schema::defaultStringLength(191);

        // Each web request sets its own language in SetLocale.

        Paginator::defaultView('components.pagination');

        Gate::define('officer', fn (User $user) => in_array($user->role, Roles::OFFICERS, true));
        Gate::define('view-membership', fn (User $user) => in_array($user->role, Roles::MEMBERSHIP_VIEW, true));
        Gate::define('manage-membership', fn (User $user) => in_array($user->role, Roles::MEMBERSHIP, true));
        Gate::define('view-finance', fn (User $user) => in_array($user->role, Roles::FINANCE_VIEW, true));
        Gate::define('manage-finance', fn (User $user) => in_array($user->role, Roles::FINANCE, true));
        Gate::define('manage-events', fn (User $user) => in_array($user->role, Roles::EVENTS, true));
        Gate::define('manage-system', fn (User $user) => $user->isSystemAdmin());
    }
}
