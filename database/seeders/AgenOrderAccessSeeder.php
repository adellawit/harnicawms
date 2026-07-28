<?php

namespace Database\Seeders;

use App\Models\IamAccess;
use App\Models\IamHasAccess;
use App\Models\Menu;
use Illuminate\Database\Seeder;

class AgenOrderAccessSeeder extends Seeder
{
    private const ADMIN_ROLE_ID = '08d263b7-2c3b-43f0-a49b-b80d9d4b7685';

    private const ADMIN_ACCESS_ID = 'b0763f22-c9d1-41de-b7b9-28b523a7a354';

    private const PRODUCT_PARENT_ID = 'a7b8c9d0-e1f2-4a5b-9c8d-7e6f5a4b3c2d';

    private const DISTRIBUTION_PARENT_ID = 'a1000000-0000-4000-8000-000000000010';

    private const MENU_ID = 'a1000000-0000-4000-8000-000000000012';

    public function run(): void
    {
        $menu = Menu::withTrashed()->updateOrCreate(
            ['id' => self::MENU_ID],
            [
                'parent_id' => self::DISTRIBUTION_PARENT_ID,
                'name' => 'Replenishment',
                'code' => 'agen_order',
                'text_sidebar' => 'Agen Order',
                'icon' => 'ti ti-package-import',
                'has_page' => false,
                'url_path' => 'agen-order',
                'route_name' => 'replenishment.index',
                'slug' => 'agen-order',
                'level_sidebar' => 3,
                'order_number' => 2,
                'is_label' => false,
                'has_create' => true,
                'has_update' => true,
                'has_read' => true,
                'has_delete' => false,
            ] + $this->menuDefaults()
        );

        if ($menu->trashed()) {
            $menu->restore();
        }

        $access = IamAccess::updateOrCreate(
            ['id' => self::ADMIN_ACCESS_ID],
            ['role_id' => self::ADMIN_ROLE_ID, 'is_notification' => false]
        );

        foreach ([
            self::PRODUCT_PARENT_ID => ['is_read' => true],
            self::DISTRIBUTION_PARENT_ID => [
                'is_read' => true,
                'is_create' => true,
                'is_update' => true,
            ],
            self::MENU_ID => [
                'is_read' => true,
                'is_create' => true,
                'is_update' => true,
            ],
        ] as $menuId => $permissions) {
            $grant = IamHasAccess::withTrashed()->firstOrNew([
                'iam_access_id' => $access->id,
                'sidebar_menu_id' => $menuId,
            ]);

            if (! $grant->exists) {
                $grant->fill($this->permissionDefaults());
            }

            $grant->fill($permissions);
            $grant->save();

            if ($grant->trashed()) {
                $grant->restore();
            }
        }
    }

    /**
     * @return array<string, bool>
     */
    private function permissionDefaults(): array
    {
        return [
            'is_create' => false,
            'is_read' => false,
            'is_update' => false,
            'is_delete' => false,
            'is_custom_1' => false,
            'is_custom_2' => false,
            'is_custom_3' => false,
            'is_custom_4' => false,
            'is_custom_5' => false,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function menuDefaults(): array
    {
        return [
            'has_custom1' => false,
            'has_custom2' => false,
            'has_custom3' => false,
            'has_custom4' => false,
            'has_custom5' => false,
        ];
    }
}
