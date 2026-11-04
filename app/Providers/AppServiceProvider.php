<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('manage-settings', fn ($user) => $user->active && $user->role === 'admin');
        Gate::define('approve-payroll', fn ($user) => $user->active && $user->role === 'admin');
    }
}
