<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermissions
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $permission, $action = 'is_read')
    {
        // Super Admin bypass to prevent false-negative permission checks.
        $user = auth('web')->user();
        if ($user && strtolower((string) ($user->role?->name ?? '')) === 'super admin') {
            return $next($request);
        }

        // Built by SidebarService from iam_has_accesses (menu name => CRUD flags).
        $permissionObject = session('permissions', []);

        if (! is_array($permissionObject) || $permissionObject === []) {
            abort(403, 'Unauthorized action.');
        }

        $permission = is_array($permission) ? $permission : [$permission];

        foreach ($permission as $perm) {
            if (
                isset($permissionObject[$perm][$action])
                && (int) $permissionObject[$perm][$action] === 1
            ) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized action.');
    }
}
