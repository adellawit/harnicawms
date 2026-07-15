<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Menu Training Academy / Academy sementara dihapus dari sidebar.
 * Seeder ini memastikan menu tidak muncul lagi setelah seed/re-login.
 */
class TrainingAccessSeeder extends Seeder
{
    private const MENU_MANAGE_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000010';
    private const MENU_LEARN_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000011';

    public function run(): void
    {
        foreach ([self::MENU_MANAGE_ID, self::MENU_LEARN_ID] as $menuId) {
            $menu = Menu::withTrashed()->find($menuId);
            if ($menu && ! $menu->trashed()) {
                $menu->delete();
            }
        }
    }
}
