<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Menu Marketing Center sementara dihapus dari sidebar.
 * Seeder ini memastikan menu tidak muncul lagi setelah seed/re-login.
 */
class MarketingAccessSeeder extends Seeder
{
    private const MENU_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000020';

    public function run(): void
    {
        $menu = Menu::withTrashed()->find(self::MENU_ID);
        if ($menu && ! $menu->trashed()) {
            $menu->delete();
        }
    }
}
