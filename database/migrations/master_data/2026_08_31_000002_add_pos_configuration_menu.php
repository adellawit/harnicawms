<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_ID = 'd0123456-f012-4234-5678-9abcdef01241';

    private const SETTINGS_PARENT_ID = 'c1234567-89ab-cdef-0123-456789abcdef';

    public function up(): void
    {
        $parentExists = DB::table('master_data.menus')
            ->where('id', self::SETTINGS_PARENT_ID)
            ->exists();

        if (! $parentExists) {
            return;
        }

        $exists = DB::table('master_data.menus')
            ->where('id', self::MENU_ID)
            ->orWhere('code', 'pos_configuration')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('master_data.menus')->insert([
            'id' => self::MENU_ID,
            'parent_id' => self::SETTINGS_PARENT_ID,
            'name' => 'POS Configuration',
            'code' => 'pos_configuration',
            'text_sidebar' => 'POS Configuration',
            'icon' => 'ti ti-cash-register',
            'has_page' => false,
            'url_path' => 'settings/pos-configuration',
            'route_name' => 'settings.pos-configuration.index.view',
            'slug' => 'pos-configuration',
            'level_sidebar' => 2,
            'order_number' => 10,
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
