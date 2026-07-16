<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class BladeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Blade directive for checking permissions in views
        // Usage: @permission('Menu Name', 'is_create') or @permission('Menu Name')
        Blade::if('permission', function ($menuName, $action = 'is_read') {
            // Only Super Admin role bypasses IAM; is_super_admin flag alone must not unlock Settings.
            if (auth()->check() && strtolower((string) (auth()->user()?->role?->name ?? '')) === 'super admin') {
                return true;
            }

            $permissions = session('permissions', []);

            if (empty($permissions)) {
                return false;
            }

            return isset($permissions[$menuName][$action]) && $permissions[$menuName][$action] == 1;
        });
    }
}
