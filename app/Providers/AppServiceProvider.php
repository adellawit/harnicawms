<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\LlmProviderManager;
use App\Services\Shop\ShopContextService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LlmProviderManager::class);

        $this->app->bind(LlmProviderInterface::class, function ($app) {
            return $app->make(LlmProviderManager::class)->current();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Paginator::defaultView('pagination.bootstrap-compact');

        View::composer(['layouts.customer', 'customer.auth.login'], function ($view) {
            $companyName = (string) config('shop.default_company_name', config('app.name'));

            if (auth('customer')->check()) {
                try {
                    $ctx = new ShopContextService(auth('customer')->user());
                    $companyName = $ctx->companyName();
                } catch (\Throwable) {
                    // keep defaults
                }
            }

            $view->with([
                'shopCompanyName' => $companyName,
            ]);
        });

        // Blade directive for checking permissions in views
        // Usage: @permission('Menu Name', 'is_create') or @permission('Menu Name')
        // Reads from session('permissions') which is built during login from iam_has_accesses table
        Blade::if('permission', function ($menuName, $action = 'is_read') {
            if (auth()->check() && auth()->user()?->is_super_admin) {
                return true;
            }

            $permissions = session('permissions', []);

            if (empty($permissions)) {
                return false;
            }

            return isset($permissions[$menuName][$action]) && $permissions[$menuName][$action] == 1;
        });

        // Register migration paths from sub-folders
        $this->loadMigrationsFrom([
            database_path('migrations/auth'),
            database_path('migrations/human_resources'),
            database_path('migrations/master_data'),
            database_path('migrations/public'),
            database_path('migrations/product'),
            database_path('migrations/purchase_order'),
            database_path('migrations/transaction'),
            database_path('migrations/operational'),
            database_path('migrations/configuration'),
            database_path('migrations/customer'),
            database_path('migrations/manufacturing'),
            database_path('migrations/distribution'),
            database_path('migrations/partner'),
        ]);

        // Force HTTPS for production domain and secure connections
        // This ensures URLs are generated with HTTPS scheme when behind a proxy/tunnel

        // Strategy: Use multiple conditions to ensure HTTPS is forced
        // 1. Check APP_URL configuration
        $appUrl = config('app.url', '');
        $isHttpsInConfig = str_starts_with($appUrl, 'https://');

        // 2. Check environment
        $isProductionEnv = App::environment(['production', 'staging']);

        // 3. Check request (if available)
        $isSecureRequest = false;
        $isProductionDomain = false;
        $currentHost = null;

        try {
            if (request()) {
                $req = request();
                $host = $req->getHost();
                $currentHost = $host;

                // Check if production domain - ALWAYS force HTTPS for gateway.wit.id
                $isProductionDomain = str_contains($host, 'gateway.wit.id');

                // Check if request is secure
                $isSecureRequest = $req->isSecure() ||
                    $req->header('X-Forwarded-Proto') === 'https' ||
                    $req->server('HTTP_X_FORWARDED_PROTO') === 'https' ||
                    $req->server('HTTPS') === 'on' ||
                    $req->server('HTTP_X_FORWARDED_SSL') === 'on';
            }
        } catch (\Exception $e) {
            // Request not available yet, continue with other checks
        }

        // Force HTTPS if any condition is met
        // This is critical for tunnel/proxy setups to prevent Mixed Content errors
        if ($isHttpsInConfig || $isProductionEnv || $isSecureRequest || $isProductionDomain) {
            URL::forceScheme('https');

            // Also update APP_URL config to use HTTPS for asset() helper
            // This ensures asset() helper also generates HTTPS URLs
            if ($currentHost && $isProductionDomain) {
                // Use current host with HTTPS
                $newAppUrl = 'https://' . $currentHost;
                Config::set('app.url', $newAppUrl);

                // Also update filesystems public disk URL
                Config::set('filesystems.disks.public.url', $newAppUrl . '/storage');
            } elseif ($isProductionEnv && !$isHttpsInConfig) {
                // If in production but APP_URL is not HTTPS, try to convert it
                if (str_starts_with($appUrl, 'http://')) {
                    $httpsUrl = str_replace('http://', 'https://', $appUrl);
                    Config::set('app.url', $httpsUrl);
                    Config::set('filesystems.disks.public.url', $httpsUrl . '/storage');
                }
            } elseif ($isHttpsInConfig) {
                // If APP_URL is already HTTPS, ensure filesystems also use it
                Config::set('filesystems.disks.public.url', $appUrl . '/storage');
            }
        }
    }
}
