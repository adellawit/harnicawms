<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_ID = 'f1234567-8901-4234-5678-9abcdef01237';

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
            ->orWhere('code', 'shipping_rate')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('master_data.menus')->insert([
            'id' => self::MENU_ID,
            'parent_id' => self::SETTINGS_PARENT_ID,
            'name' => 'Master Ongkir',
            'code' => 'shipping_rate',
            'text_sidebar' => 'Master Ongkir',
            'icon' => 'ti ti-truck-delivery',
            'has_page' => false,
            'url_path' => 'master-data/shipping-rate',
            'route_name' => 'shipping-rate.index.view',
            'slug' => 'shipping-rate',
            'level_sidebar' => 2,
            'order_number' => 6,
            'is_label' => false,
            'has_create' => true,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => true,
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
