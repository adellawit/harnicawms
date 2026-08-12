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
use App\Services\Shop\ShopCartService;
use App\Services\Shop\ShopContextService;
use App\Services\Shipping\AgentShippingEstimator;
use App\Services\Theme\AppThemeService;

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

        View::composer(['layouts.customer', 'layouts.agent-order', 'layouts.agent-pos', 'customer.auth.login'], function ($view) {
            $companyName = (string) config('shop.default_company_name', config('app.name'));
            $companyAddress = null;
            $companyPhone = null;
            $companyEmail = null;

            if (auth('customer')->check()) {
                try {
                    $ctx = new ShopContextService(auth('customer')->user());
                    $companyName = $ctx->companyName();
                    $company = $ctx->company();
                    if ($company) {
                        $companyAddress = collect([$company->address, $company->city, $company->province])
                            ->filter(fn ($part) => filled($part))
                            ->implode(', ') ?: null;
                        $companyPhone = $company->phone;
                        $companyEmail = $company->email;
                    }
                } catch (\Throwable) {
                    // keep defaults
                }
            }

            $view->with([
                'shopCompanyName' => $companyName,
                'shopCompanyAddress' => $companyAddress,
                'shopCompanyPhone' => $companyPhone,
                'shopCompanyEmail' => $companyEmail,
            ]);
        });

        View::composer(['layouts.agent-order', 'layouts.agent-pos'], function ($view) {
            $emptyCart = ['price_list_id' => null, 'items' => []];
            $emptySummary = [
                'item_count' => 0,
                'subtotal' => 0,
                'tax_amount' => 0,
                'tax_rate' => 0,
                'tax_enabled' => false,
                'total' => 0,
                'shipping_estimate' => null,
            ];

            if (! auth('customer')->check()) {
                $view->with(['navCart' => $emptyCart, 'navCartSummary' => $emptySummary]);

                return;
            }

            try {
                $cartService = new ShopCartService(new ShopContextService(auth('customer')->user()));
                $summary = $cartService->summarize();
                $agent = auth('customer')->user()?->agent;
                $estimator = app(AgentShippingEstimator::class);
                $summary['shipping_estimate'] = $agent
                    ? $estimator->lowestEstimate($cartService, $agent)
                    : null;

                $view->with([
                    'navCart' => $cartService->get(),
                    'navCartSummary' => $summary,
                ]);
            } catch (\Throwable) {
                $view->with(['navCart' => $emptyCart, 'navCartSummary' => $emptySummary]);
            }
        });

        View::composer(['layouts.*', 'auth.*', 'dashboard', 'customer.*'], function ($view) {
            try {
                $view->with('appTheme', app(AppThemeService::class)->viewData());
            } catch (\Throwable) {
                $view->with('appTheme', [
                    'primary' => '#5C9E84',
                    'secondary' => '#7BB5A0',
                    'primary_rgb' => '92, 158, 132',
                    'secondary_rgb' => '123, 181, 160',
                    'primary_600' => '#4A8770',
                    'primary_700' => '#3D7260',
                    'primary_soft' => '#E8F3EE',
                    'secondary_600' => '#6AA894',
                    'secondary_soft' => '#E8F3EE',
                    'color_mode' => 'logo_extract',
                    'glass_enabled' => true,
                    'motion_enabled' => true,
                    'logo_url' => asset('assets/img/harnica/logo.png'),
                    'favicon_url' => asset('assets/img/wms/favicon.ico'),
                    'default_logo_url' => asset('assets/img/harnica/logo.png'),
                ]);
            }
        });

        // Blade directive for checking permissions in views
        // Usage: @permission('Menu Name', 'is_create') or @permission('Menu Name')
        // Reads from session('permissions') which is built during login from iam_has_accesses table
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
            database_path('migrations/training'),
            database_path('migrations/marketing'),
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
