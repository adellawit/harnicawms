<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarehouseMenuSeeder extends Seeder
{
    public const MENU_ID = 'f1a2b3c4-d5e6-4789-a012-3456789abcde';

    public function run(): void
    {
        $business = DB::table('master_data.menus')
            ->where('code', 'business')
            ->whereNull('deleted_at')
            ->first();

        if ($business === null) {
            $this->command?->warn('Business menu not found — skipping Warehouse menu seed.');

            return;
        }

        $exists = DB::table('master_data.menus')
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->where('id', self::MENU_ID)
                    ->orWhere('name', 'Warehouse');
            })
            ->exists();

        if (! $exists) {
            DB::table('master_data.menus')->insert([
                'id' => self::MENU_ID,
                'parent_id' => $business->id,
                'name' => 'Warehouse',
                'code' => 'warehouse',
                'text_sidebar' => 'Gudang',
                'icon' => 'ti ti-building-warehouse',
                'has_page' => false,
                'url_path' => 'business/warehouse',
                'route_name' => 'warehouse.index.view',
                'slug' => 'warehouse',
                'level_sidebar' => 2,
                'order_number' => 4,
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

            $this->command?->info('Warehouse menu created under Business.');
        } else {
            DB::table('master_data.menus')
                ->where('name', 'Warehouse')
                ->whereNull('deleted_at')
                ->update([
                    'parent_id' => $business->id,
                    'text_sidebar' => 'Gudang',
                    'url_path' => 'business/warehouse',
                    'route_name' => 'warehouse.index.view',
                    'icon' => 'ti ti-building-warehouse',
                    'order_number' => 4,
                    'has_create' => true,
                    'has_update' => true,
                    'has_read' => true,
                    'has_delete' => true,
                    'updated_at' => now(),
                ]);

            $this->command?->info('Warehouse menu updated.');
        }

        $menuId = DB::table('master_data.menus')
            ->where('name', 'Warehouse')
            ->whereNull('deleted_at')
            ->value('id');

        if ($menuId === null) {
            return;
        }

        $iamAccessRows = DB::table('auth.iam_accesses')
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($iamAccessRows as $iamAccessId) {
            $accessExists = DB::table('auth.iam_has_accesses')
                ->where('iam_access_id', $iamAccessId)
                ->where('sidebar_menu_id', $menuId)
                ->exists();

            if ($accessExists) {
                continue;
            }

            DB::table('auth.iam_has_accesses')->insert([
                'id' => (string) Str::uuid(),
                'iam_access_id' => $iamAccessId,
                'sidebar_menu_id' => $menuId,
                'is_create' => true,
                'is_read' => true,
                'is_update' => true,
                'is_delete' => true,
                'is_custom_1' => false,
                'is_custom_2' => false,
                'is_custom_3' => false,
                'is_custom_4' => false,
                'is_custom_5' => false,
            ]);
        }

        $this->command?->info('Warehouse access granted to all IAM profiles.');
    }
}
