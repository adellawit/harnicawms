<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\IamAccess;
use App\Models\IamHasAccess;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SidebarService
{
    /**
     * Load sidebar menus and build permission map for the current user's role.
     * Stores both 'sidebars' and 'permissions' in the session.
     *
     * @param string|null $roleId  Optional role ID. Defaults to the current authenticated user's role.
     */
    public static function loadSidebarsAndPermissions(?string $roleId = null): void
    {
        $roleId = $roleId ?? Auth::user()->role_id;

        if ($roleId === Role::SUPER_ADMIN_ID) {
            self::loadSuperAdminSidebars();

            return;
        }

        $iamMenuConstraint = function ($query) use ($roleId) {
            $query->where('is_read', '!=', 0)
                ->whereHas('iamAccess', function ($query) use ($roleId) {
                    $query->where('role_id', $roleId);
                });
        };

        // Load root menus only, with nested children filtered by IAM
        $sidebars = Menu::query()
            ->whereNull('parent_id')
            ->whereHas('iamHasAccess', $iamMenuConstraint)
            ->with([
                'children' => function ($query) use ($iamMenuConstraint) {
                    $query->whereHas('iamHasAccess', $iamMenuConstraint)
                        ->orderBy('order_number');
                },
                'children.children' => function ($query) use ($iamMenuConstraint) {
                    $query->whereHas('iamHasAccess', $iamMenuConstraint)
                        ->orderBy('order_number');
                },
                'children.children.children' => function ($query) use ($iamMenuConstraint) {
                    $query->whereHas('iamHasAccess', $iamMenuConstraint)
                        ->orderBy('order_number');
                },
            ])
            ->orderBy('order_number')
            ->get();

        session(['sidebars' => $sidebars]);
        session(['permissions' => self::buildPermissionMapFromIam($roleId)]);
    }

    private static function loadSuperAdminSidebars(): void
    {
        $sidebars = Menu::query()
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query->orderBy('order_number'),
                'children.children' => fn ($query) => $query->orderBy('order_number'),
                'children.children.children' => fn ($query) => $query->orderBy('order_number'),
            ])
            ->orderBy('order_number')
            ->get();

        session(['sidebars' => $sidebars]);
        session(['permissions' => self::buildFullPermissionMap($sidebars)]);
    }

    private static function buildPermissionMapFromIam(string $roleId): array
    {
        $permissionMap = [];
        $iamAccess = IamAccess::where('role_id', $roleId)->first();

        if (! $iamAccess) {
            return $permissionMap;
        }

        $hasAccesses = IamHasAccess::where('iam_access_id', $iamAccess->id)
            ->with('sidebarMenu')
            ->get();

        foreach ($hasAccesses as $access) {
            if ($access->sidebarMenu) {
                $permissionMap[$access->sidebarMenu->name] = [
                    'is_create' => $access->is_create,
                    'is_read' => $access->is_read,
                    'is_update' => $access->is_update,
                    'is_delete' => $access->is_delete,
                    'is_custom1' => $access->is_custom_1,
                    'is_custom2' => $access->is_custom_2,
                    'is_custom3' => $access->is_custom_3,
                    'is_custom4' => $access->is_custom_4,
                    'is_custom5' => $access->is_custom_5,
                ];
            }
        }

        return $permissionMap;
    }

    private static function buildFullPermissionMap(Collection $menus): array
    {
        $permissionMap = [];

        $walk = function (Collection $items) use (&$permissionMap, &$walk): void {
            foreach ($items as $menu) {
                $permissionMap[$menu->name] = [
                    'is_create' => true,
                    'is_read' => true,
                    'is_update' => true,
                    'is_delete' => true,
                    'is_custom1' => true,
                    'is_custom2' => true,
                    'is_custom3' => true,
                    'is_custom4' => true,
                    'is_custom5' => true,
                ];

                if ($menu->relationLoaded('children') && $menu->children->isNotEmpty()) {
                    $walk($menu->children);
                }
            }
        };

        $walk($menus);

        return $permissionMap;
    }
}
