<?php

namespace Database\Seeders;

use App\Models\IamAccess;
use App\Models\IamHasAccess;
use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TrainingAccessSeeder extends Seeder
{
    private const ADMINISTRATOR_ROLE_ID = '08d263b7-2c3b-43f0-a49b-b80d9d4b7685';

    private const MARKETING_ROLE_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000001';

    private const AGENT_ROLE_ID = '2ac6f6a1-7b8c-4d9e-9f10-111213141516';

    private const TRAINING_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000010';

    private const ACADEMY_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000011';

    private const SETTINGS_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000012';

    private const COURSES_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000014';

    public function run(): void
    {
        Role::updateOrCreate(
            ['id' => self::MARKETING_ROLE_ID],
            ['name' => 'Marketing']
        );

        $this->upsertMenu(self::TRAINING_ID, [
            'parent_id' => null,
            'name' => 'Training Academy',
            'code' => 'training-academy',
            'text_sidebar' => 'Training Academy',
            'icon' => 'ti ti-school',
            'has_page' => true,
            'url_path' => 'training/academy',
            'route_name' => 'training.academy.home',
            'slug' => 'training-academy',
            'level_sidebar' => 1,
            'order_number' => 6,
            'has_create' => true,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => true,
        ]);

        $this->upsertMenu(self::COURSES_ID, [
            'parent_id' => self::TRAINING_ID,
            'name' => 'Course',
            'code' => 'training-courses',
            'text_sidebar' => 'Course',
            'icon' => 'ti ti-blackboard',
            'has_page' => false,
            'url_path' => 'training/courses',
            'route_name' => 'training.courses.index',
            'slug' => 'training-courses',
            'level_sidebar' => 2,
            'order_number' => 1,
            'has_create' => true,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => true,
        ]);

        $this->upsertMenu(self::ACADEMY_ID, [
            'parent_id' => self::TRAINING_ID,
            'name' => 'Academy',
            'code' => 'academy',
            'text_sidebar' => 'Academy',
            'icon' => 'ti ti-book',
            'has_page' => false,
            'url_path' => 'academy',
            'route_name' => 'academy.dashboard',
            'slug' => 'academy',
            'level_sidebar' => 2,
            'order_number' => 2,
            'has_create' => false,
            'has_update' => false,
            'has_read' => true,
            'has_delete' => false,
        ]);

        $this->upsertMenu(self::SETTINGS_ID, [
            'parent_id' => self::TRAINING_ID,
            'name' => 'Pengaturan Academy',
            'code' => 'training-academy-settings',
            'text_sidebar' => 'Pengaturan Academy',
            'icon' => 'ti ti-settings',
            'has_page' => false,
            'url_path' => 'training/settings',
            'route_name' => 'training.settings.edit',
            'slug' => 'training-academy-settings',
            'level_sidebar' => 2,
            'order_number' => 3,
            'has_create' => false,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => false,
        ]);

        foreach ([self::ADMINISTRATOR_ROLE_ID, self::MARKETING_ROLE_ID] as $roleId) {
            $this->grant($roleId, self::TRAINING_ID, true, true, true, true);
            $this->grant($roleId, self::COURSES_ID, true, true, true, true);
            $this->grant($roleId, self::ACADEMY_ID, false, true, false, false);
            $this->grant($roleId, self::SETTINGS_ID, false, true, true, false);
        }

        // Agent: learner path only (parent + Academy).
        $this->grant(self::AGENT_ROLE_ID, self::TRAINING_ID, false, true, false, false);
        $this->grant(self::AGENT_ROLE_ID, self::ACADEMY_ID, false, true, false, false);
    }

    /** @param array<string, mixed> $attributes */
    private function upsertMenu(string $menuId, array $attributes): void
    {
        $menu = Menu::withTrashed()->firstOrNew(['id' => $menuId]);
        $menu->fill(array_merge([
            'is_label' => false,
            'has_custom1' => false,
            'has_custom2' => false,
            'has_custom3' => false,
            'has_custom4' => false,
            'has_custom5' => false,
        ], $attributes));
        $menu->deleted_by = null;
        $menu->save();

        if ($menu->trashed()) {
            $menu->restore();
        }
    }

    private function grant(string $roleId, string $menuId, bool $create, bool $read, bool $update, bool $delete): void
    {
        $iamAccess = IamAccess::firstOrCreate(
            ['role_id' => $roleId],
            ['id' => (string) Str::uuid(), 'is_notification' => false]
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
