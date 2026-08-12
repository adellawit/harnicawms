<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Child menu under Marketing Center that incorrectly pointed to marketing.assets
     * but was labeled "Marketing Campaign" / sidebar "Campaign".
     */
    private const MENU_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000022';

    public function up(): void
    {
        DB::table('master_data.menus')
            ->where('id', self::MENU_ID)
            ->where('route_name', 'marketing.assets.index')
            ->where(function ($q) {
                $q->where('name', 'Marketing Campaign')
                    ->orWhere('text_sidebar', 'Campaign')
                    ->orWhere('code', 'marketing-campaign');
            })
            ->update([
                'name' => 'Marketing Assets',
                'text_sidebar' => 'Marketing Assets',
                'slug' => 'marketing-assets',
                'code' => 'marketing_assets',
                'icon' => 'ti ti-photo',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('master_data.menus')
            ->where('id', self::MENU_ID)
            ->where('route_name', 'marketing.assets.index')
            ->where(function ($q) {
                $q->where('name', 'Marketing Assets')
                    ->orWhere('text_sidebar', 'Marketing Assets')
                    ->orWhere('code', 'marketing_assets');
            })
            ->update([
                'name' => 'Marketing Campaign',
                'text_sidebar' => 'Campaign',
                'slug' => 'marketing-campaign',
                'code' => 'marketing-campaign',
                'updated_at' => now(),
            ]);
    }
};
