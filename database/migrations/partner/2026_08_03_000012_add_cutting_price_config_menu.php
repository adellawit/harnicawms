<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_ID = '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c008';

    private const NETWORK_PARENT_ID = '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c001';

    public function up(): void
    {
        $parentExists = DB::table('master_data.menus')
            ->where('id', self::NETWORK_PARENT_ID)
            ->exists();

        if (! $parentExists) {
            return;
        }

        $exists = DB::table('master_data.menus')
            ->where('id', self::MENU_ID)
            ->orWhere('code', 'partner-cutting-price-config')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('master_data.menus')->insert([
            'id' => self::MENU_ID,
            'parent_id' => self::NETWORK_PARENT_ID,
            'name' => 'Cutting Price Config',
            'code' => 'partner-cutting-price-config',
            'text_sidebar' => 'Cutting Price Config',
            'icon' => 'ti ti-discount-check',
            'has_page' => true,
            'url_path' => 'partner-network/cutting-price-config',
            'route_name' => 'partner.cutting-price-config.index.view',
            'slug' => 'partner-cutting-price-config',
            'level_sidebar' => 3,
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
