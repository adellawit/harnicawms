<?php

namespace Database\Seeders;

use App\Models\IamAccess;
use App\Models\IamHasAccess;
use App\Models\Menu;
use Illuminate\Database\Seeder;

class CategoryMenuPolishSeeder extends Seeder
{
    private const ADMINISTRATOR_ROLE_ID = '08d263b7-2c3b-43f0-a49b-b80d9d4b7685';

    private const MARKETING_ROLE_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000001';

    private const MARKETING_CENTER_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000020';

    private const MARKETING_ASSET_CATEGORY_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000021';

    private const MARKETING_ASSETS_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000022';

    private const TRAINING_ACADEMY_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000010';

    private const COURSE_CATEGORY_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000013';

    public function run(): void
    {
        $this->ensureMarketingCenterMenu();
        $this->ensureMarketingAssetCategoryMenu();
        $this->ensureCourseCategoryMenu();

        foreach ([self::ADMINISTRATOR_ROLE_ID, self::MARKETING_ROLE_ID] as $roleId) {
            $this->grant($roleId, self::MARKETING_CENTER_ID, create: true, read: true, update: true, delete: true);
            $this->grant($roleId, self::MARKETING_ASSETS_ID, create: true, read: true, update: true, delete: true);
            $this->grant($roleId, self::MARKETING_ASSET_CATEGORY_ID, create: true, read: true, update: true, delete: true);
            $this->grant($roleId, self::COURSE_CATEGORY_ID, create: true, read: true, update: true, delete: true);
        }
    }

    protected function ensureMarketingCenterMenu(): void
    {
        $menu = Menu::withTrashed()->updateOrCreate(['id' => self::MARKETING_CENTER_ID], [
            'parent_id' => null,
            'name' => 'Marketing Center',
            'code' => 'marketing-center',
            'text_sidebar' => 'Marketing Center',
            'icon' => 'ti ti-speakerphone',
            'has_page' => false,
            'url_path' => 'marketing',
            'route_name' => null,
            'slug' => 'marketing-center',
            'level_sidebar' => 1,
            'order_number' => 850,
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
        ]);

        if ($menu->trashed()) {
            $menu->restore();
        }
    }

    protected function ensureMarketingAssetCategoryMenu(): void
    {
        Menu::withTrashed()
            ->where('route_name', 'marketing.categories.index')
            ->where('id', '!=', self::MARKETING_ASSET_CATEGORY_ID)
            ->each(function (Menu $duplicate) {
                if (! $duplicate->trashed()) {
                    $duplicate->delete();
                }
            });

        $menu = Menu::withTrashed()->updateOrCreate(['id' => self::MARKETING_ASSET_CATEGORY_ID], [
            'parent_id' => self::MARKETING_CENTER_ID,
            'name' => 'Marketing Asset Category',
            'code' => 'marketing_asset_category',
            'text_sidebar' => 'Marketing Asset Category',
            'icon' => 'ti ti-tags',
            'has_page' => true,
            'url_path' => 'marketing/categories',
            'route_name' => 'marketing.categories.index',
            'slug' => 'marketing-asset-category',
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
        ]);

        if ($menu->trashed()) {
            $menu->restore();
        }

        $assetsMenu = Menu::withTrashed()->find(self::MARKETING_ASSETS_ID);
        if ($assetsMenu) {
            $assetsMenu->update([
                'parent_id' => self::MARKETING_CENTER_ID,
                'level_sidebar' => 2,
                'order_number' => 1,
            ]);
            if ($assetsMenu->trashed()) {
                $assetsMenu->restore();
            }
        }
    }

    protected function ensureCourseCategoryMenu(): void
    {
        $menu = Menu::withTrashed()->updateOrCreate(['id' => self::COURSE_CATEGORY_ID], [
            'parent_id' => self::TRAINING_ACADEMY_ID,
            'name' => 'Course Category',
            'code' => 'course_category',
            'text_sidebar' => 'Course Category',
            'icon' => 'ti ti-tags',
            'has_page' => true,
            'url_path' => 'training/categories',
            'route_name' => 'training.categories.index',
            'slug' => 'course-category',
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
        ]);

        if ($menu->trashed()) {
            $menu->restore();
        }

        $trainingAcademy = Menu::withTrashed()->find(self::TRAINING_ACADEMY_ID);
        if ($trainingAcademy && $trainingAcademy->has_page) {
            $trainingAcademy->update(['has_page' => false]);
        }
    }

    private function grant(string $roleId, string $menuId, bool $create, bool $read, bool $update, bool $delete): void
    {
        $iamAccess = IamAccess::firstOrCreate(
            ['role_id' => $roleId],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'is_notification' => false]
        );

        IamHasAccess::updateOrCreate(
            ['iam_access_id' => $iamAccess->id, 'sidebar_menu_id' => $menuId],
            [
                'is_create' => $create,
                'is_read' => $read,
                'is_update' => $update,
                'is_delete' => $delete,
                'is_custom_1' => false,
                'is_custom_2' => false,
                'is_custom_3' => false,
                'is_custom_4' => false,
                'is_custom_5' => false,
            ]
        );
    }
}
