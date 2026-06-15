<?php

namespace App\Support\Ai;

use App\Models\User;

class ChatAccess
{
    public static function canShowWidget(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (self::isSuperAdmin($user)) {
            return true;
        }

        $menu = (string) config('agent.permission_menu', 'AI Assistant');
        $permissions = session('permissions', []);

        return isset($permissions[$menu]['is_read'])
            && (int) $permissions[$menu]['is_read'] === 1;
    }

    public static function isSuperAdmin(User $user): bool
    {
        return strtolower((string) ($user->role?->name ?? '')) === 'super admin';
    }
}
