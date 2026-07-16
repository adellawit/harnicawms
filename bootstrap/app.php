<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \App\Providers\AppServiceProvider::class,
        \App\Providers\BladeServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Middleware aliases
        $middleware->alias([
            'jwt.user' => \App\Http\Middleware\JwtMiddlewareUser::class,
            'jwt.admin' => \App\Http\Middleware\JwtMiddlewareAdmin::class,
            'permission' => \App\Http\Middleware\CheckPermissions::class,
            'api.token' => \App\Http\Middleware\ValidateApiToken::class,
        ]);

        // Add ForceHttps middleware to web group (after TrustProxies)
        $middleware->web(append: [
            \App\Http\Middleware\NormalizeDateInput::class,
            \App\Http\Middleware\ForceHttps::class,
            \App\Http\Middleware\EnsureSidebarLoaded::class,
        ]);

        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            if ($request->is('shop', 'shop/*', 'orders', 'orders/*')) {
                return route('customer.login');
            }

            return route('login');
        });

        // Validate CSRF tokens in web middleware
        $middleware->validateCsrfTokens(except: [
            'webhooks/xendit',
            'webhooks/telegram',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
