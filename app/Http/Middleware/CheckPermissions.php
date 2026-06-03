<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Log;
use Symfony\Component\HttpFoundation\Response;

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

        // Mendapatkan session sidebar dari session
        $permissions = session()->get('sidebars');

        $permissionObject = [];

        foreach ($permissions as $p) {
            $this->flattenPermissionTree($p, $permissionObject);
        }

        // Menampilkan hasil format baru
        // Log::info('Transformed Permissions:', $permissionObject);

        // Pastikan permissions dari session tersedia dan tidak kosong
        if (!$permissionObject || !is_array($permissionObject)) {
            // Log::info('Permissions not found or invalid.');
            abort(403, 'Unauthorized action.');
        }

        // Pastikan permission parameter selalu dalam bentuk array
        $permission = is_array($permission) ? $permission : [$permission];
        // Log::info('Permissions:', $permission);

        $hasPermission = false;

       // Loop melalui setiap permission yang diberikan
       foreach ($permission as $perm) {
        // Cek apakah permission ada di dalam session dan apakah flag yang diminta aktif
        if (isset($permissionObject[$perm]) && isset($permissionObject[$perm][$action]) && $permissionObject[$perm][$action] == 1) {
            $hasPermission = true;
            break;
        }
    }

    // Jika permission ditemukan, lanjutkan request
    if ($hasPermission) {
        return $next($request);
    }

    // Jika tidak ada permission yang cocok, return 403 Unauthorized
    abort(403, 'Unauthorized action.');
    }

    private function flattenPermissionTree(array $node, array &$permissionObject): void
    {
        if (!empty($node['name'])) {
            $permissionObject[$node['name']] = [
                'is_create' => $node['has_create'] ?? false,
                'is_update' => $node['has_update'] ?? false,
                'is_read' => $node['has_read'] ?? false,
                'is_delete' => $node['has_delete'] ?? false,
                'is_custom1' => $node['has_custom1'] ?? false,
                'is_custom2' => $node['has_custom2'] ?? false,
                'is_custom3' => $node['has_custom3'] ?? false,
                'is_custom4' => $node['has_custom4'] ?? false,
                'is_custom5' => $node['has_custom5'] ?? false,
            ];
        }

        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                if (is_array($child)) {
                    $this->flattenPermissionTree($child, $permissionObject);
                }
            }
        }
    }
}
