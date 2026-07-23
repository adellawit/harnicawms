<?php

namespace App\Http\Middleware;

use App\Services\SidebarService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSidebarLoaded
{
    /**
     * Muat ulang sidebar dari database setiap request (urutan & menu terbaru).
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Shop customer app tidak memakai admin sidebar / IAM permissions.
        if ($request->is('shop', 'shop/*', 'orders', 'orders/*')) {
            return $next($request);
        }

        $admin = Auth::guard('web')->user();
        if ($admin && filled($admin->role_id)) {
            SidebarService::loadSidebarsAndPermissions((string) $admin->role_id);
        }

        return $next($request);
    }
}
