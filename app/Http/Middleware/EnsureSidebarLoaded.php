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
        if (Auth::guard('web')->check()) {
            SidebarService::loadSidebarsAndPermissions();
        }

        return $next($request);
    }
}
