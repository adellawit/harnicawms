<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_ID = 'e0123456-f012-4234-5678-9abcdef01236';
    private const SETTINGS_PARENT_ID = 'c1234567-89ab-cdef-0123-456789abcdef';

    public function up(): void
    {
        $exists = DB::table('master_data.menus')
            ->where('id', self::MENU_ID)
            ->orWhere('code', 'appearance_theme')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('master_data.menus')->insert([
            'id' => self::MENU_ID,
            'parent_id' => self::SETTINGS_PARENT_ID,
            'name' => 'Appearance & Theme',
            'code' => 'appearance_theme',
            'text_sidebar' => 'Appearance & Theme',
            'icon' => 'ti ti-palette',
            'has_page' => false,
            'url_path' => 'settings/theme-configuration',
            'route_name' => 'settings.theme-configuration.index.view',
            'slug' => 'appearance-theme',
            'level_sidebar' => 2,
            'order_number' => 9,
            'is_label' => false,
            'has_create' => false,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => false,
            'has_custom1' => false,
            'has_custom2' => false,
            'has_custom3' => false,
            'has_custom4' => false,
            'has_custom5' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('master_data.menus')
            ->where('id', self::MENU_ID)
            ->delete();
    }
};
