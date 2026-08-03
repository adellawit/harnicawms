<?php

namespace Database\Seeders;

use App\Models\IamAccess;
use App\Models\IamHasAccess;
use App\Models\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MarketingAccessSeeder extends Seeder
{
    private const ADMINISTRATOR_ROLE_ID = '08d263b7-2c3b-43f0-a49b-b80d9d4b7685';

    private const MARKETING_ROLE_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000001';

    private const MENU_CENTER_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000020';

    private const MENU_CATEGORY_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000021';

    private const MENU_ASSETS_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000022';

    public function run(): void
    {
        $this->upsertMenu(self::MENU_CENTER_ID, [
            'parent_id' => null,
            'name' => 'Marketing Center',
            'code' => 'marketing-center',
            'text_sidebar' => 'Marketing Center',
            'icon' => 'ti ti-speakerphone',
            'has_page' => true,
            'url_path' => null,
            'route_name' => null,
            'slug' => 'marketing-center',
            'level_sidebar' => 1,
            'order_number' => 7,
        ]);

        $this->upsertMenu(self::MENU_CATEGORY_ID, [
            'parent_id' => self::MENU_CENTER_ID,
            'name' => 'Marketing Category',
            'code' => 'marketing-category',
            'text_sidebar' => 'Marketing Category',
            'icon' => 'ti ti-category',
            'has_page' => false,
            'url_path' => 'marketing/categories',
            'route_name' => 'marketing.categories.index',
            'slug' => 'marketing-category',
            'level_sidebar' => 2,
            'order_number' => 1,
        ]);

        $this->upsertMenu(self::MENU_ASSETS_ID, [
            'parent_id' => self::MENU_CENTER_ID,
            'name' => 'Marketing Assets',
            'code' => 'marketing-campaign',
            'text_sidebar' => 'Marketing Assets',
            'icon' => 'ti ti-photo',
            'has_page' => false,
            'url_path' => 'marketing/assets',
            'route_name' => 'marketing.assets.index',
            'slug' => 'marketing-campaign',
            'level_sidebar' => 2,
            'order_number' => 2,
        ]);

        $menuIds = [
            self::MENU_CENTER_ID,
            self::MENU_CATEGORY_ID,
            self::MENU_ASSETS_ID,
        ];

        foreach ([self::ADMINISTRATOR_ROLE_ID, self::MARKETING_ROLE_ID] as $roleId) {
            foreach ($menuIds as $menuId) {
                $this->grantFullAccess($roleId, $menuId);
            }
        }
    }

    /** @param array<string, mixed> $attributes */
    private function upsertMenu(string $menuId, array $attributes): void
    {
        $menu = Menu::withTrashed()->firstOrNew(['id' => $menuId]);
        $menu->fill(array_merge($attributes, [
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
        ]));
        $menu->deleted_by = null;
        $menu->save();

        if ($menu->trashed()) {
            $menu->restore();
        }
    }

    private function grantFullAccess(string $roleId, string $menuId): void
    {
        $iamAccess = IamAccess::firstOrCreate(
            ['role_id' => $roleId],
            ['id' => (string) Str::uuid(), 'is_notification' => false]
        );

        IamHasAccess::updateOrCreate(
            ['iam_access_id' => $iamAccess->id, 'sidebar_menu_id' => $menuId],
            [
                'is_create' => true,
                'is_read' => true,
                'is_update' => true,
                'is_delete' => true,
                'is_custom_1' => false,
                'is_custom_2' => false,
                'is_custom_3' => false,
                'is_custom_4' => false,
                'is_custom_5' => false,
            ]
        );
    }
}
