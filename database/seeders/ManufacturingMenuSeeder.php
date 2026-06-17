<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManufacturingMenuSeeder extends Seeder
{
    public const PRODUKSI_ID = 'a1000000-0000-4000-8000-000000000001';
    public const STOK_MASUK_ID = 'a1000000-0000-4000-8000-000000000002';
    public const BOM_ID = 'a1000000-0000-4000-8000-000000000003';
    public const PRODUCTION_ORDER_ID = 'a1000000-0000-4000-8000-000000000004';

    public function run(): void
    {
        $menus = [
            [
                'id' => self::PRODUKSI_ID,
                'parent_id' => null,
                'name' => 'Produksi',
                'code' => 'produksi',
                'text_sidebar' => 'Produksi',
                'icon' => 'ti ti-building-factory-2',
                'has_page' => true,
                'url_path' => 'produksi',
                'route_name' => null,
                'slug' => 'produksi',
                'level_sidebar' => 1,
                'order_number' => 20,
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
            ],
            [
                'id' => self::STOK_MASUK_ID,
                'parent_id' => self::PRODUKSI_ID,
                'name' => 'Stok Masuk',
                'code' => 'stok_masuk',
                'text_sidebar' => 'Stok Masuk',
                'icon' => 'ti ti-package-import',
                'has_page' => false,
                'url_path' => 'inbound',
                'route_name' => 'inbound.index',
                'slug' => 'inbound',
                'level_sidebar' => 2,
                'order_number' => 1,
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
            ],
            [
                'id' => self::BOM_ID,
                'parent_id' => self::PRODUKSI_ID,
                'name' => 'Bill of Materials',
                'code' => 'bill_of_materials',
                'text_sidebar' => 'Bill of Materials',
                'icon' => 'ti ti-list-details',
                'has_page' => false,
                'url_path' => 'bom',
                'route_name' => 'bom.index',
                'slug' => 'bom',
                'level_sidebar' => 2,
                'order_number' => 2,
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
            ],
            [
                'id' => self::PRODUCTION_ORDER_ID,
                'parent_id' => self::PRODUKSI_ID,
                'name' => 'Production Order',
                'code' => 'production_order',
                'text_sidebar' => 'Production Order',
                'icon' => 'ti ti-tool',
                'has_page' => false,
                'url_path' => 'production',
                'route_name' => 'production.index',
                'slug' => 'production',
                'level_sidebar' => 2,
                'order_number' => 3,
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
            ],
        ];

        foreach ($menus as $menu) {
            $exists = DB::table('master_data.menus')
                ->where('id', $menu['id'])
                ->exists();

            $payload = array_merge($menu, [
                'updated_at' => now(),
                'deleted_at' => null,
                'deleted_by' => null,
            ]);

            if ($exists) {
                DB::table('master_data.menus')->where('id', $menu['id'])->update($payload);
            } else {
                DB::table('master_data.menus')->insert(array_merge($payload, [
                    'created_at' => now(),
                ]));
            }
        }

        $this->command?->info('Manufacturing menus (Produksi, BOM, Production Order) seeded.');

        $menuIds = [
            self::PRODUKSI_ID,
            self::STOK_MASUK_ID,
            self::BOM_ID,
            self::PRODUCTION_ORDER_ID,
        ];

        $iamAccessRows = DB::table('auth.iam_accesses')
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($iamAccessRows as $iamAccessId) {
            foreach ($menuIds as $menuId) {
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
        }

        $this->command?->info('Manufacturing menu access granted to all IAM profiles.');
    }
}
