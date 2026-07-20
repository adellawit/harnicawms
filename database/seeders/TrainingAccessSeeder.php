<?php

namespace Database\Seeders;

use App\Models\IamAccess;
use App\Models\IamHasAccess;
use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;

class TrainingAccessSeeder extends Seeder
{
    private const ADMINISTRATOR_ROLE_ID = '08d263b7-2c3b-43f0-a49b-b80d9d4b7685';

    private const MARKETING_ROLE_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000001';

    private const AGENT_ROLE_ID = '2ac6f6a1-7b8c-4d9e-9f10-111213141516';

    private const MENU_MANAGE_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000010';

    private const MENU_LEARN_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000011';

    private const MENU_SETTINGS_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000012';

    public function run(): void
    {
        // 1) Ensure Marketing role exists (safe if RoleSeeder already ran).
        Role::updateOrCreate(
            ['id' => self::MARKETING_ROLE_ID],
            ['name' => 'Marketing']
        );

        // 2) Single "Training Academy" menu — admin & agent share the same resource.
        Menu::withTrashed()->where('id', self::MENU_MANAGE_ID)->restore();
        Menu::updateOrCreate(['id' => self::MENU_MANAGE_ID], [
            'parent_id' => null,
            'name' => 'Training Academy',
            'code' => 'training-academy',
            'text_sidebar' => 'Training Academy',
            'icon' => 'ti ti-school',
            'has_page' => false,
            'url_path' => 'training/academy',
            'route_name' => 'training.academy.home',
            'slug' => 'training-academy',
            'level_sidebar' => 1,
            'order_number' => 900,
            'is_label' => false,
            'has_create' => true,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => true,
            'has_custom1' => false, 'has_custom2' => false, 'has_custom3' => false,
            'has_custom4' => false, 'has_custom5' => false,
        ]);

        // 3) "Pengaturan Academy" menu.
        Menu::updateOrCreate(['id' => self::MENU_SETTINGS_ID], [
            'parent_id' => null,
            'name' => 'Pengaturan Academy',
            'code' => 'training-academy-settings',
            'text_sidebar' => 'Pengaturan Academy',
            'icon' => 'ti ti-settings',
            'has_page' => false,
            'url_path' => 'training/settings',
            'route_name' => 'training.settings.edit',
            'slug' => 'training-academy-settings',
            'level_sidebar' => 1,
            'order_number' => 902,
            'is_label' => false,
            'has_create' => false,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => false,
            'has_custom1' => false, 'has_custom2' => false, 'has_custom3' => false,
            'has_custom4' => false, 'has_custom5' => false,
        ]);

        // 4) Retire separate "Academy" menu — unified under Training Academy.
        $legacyLearnMenu = Menu::withTrashed()->find(self::MENU_LEARN_ID);
        if ($legacyLearnMenu && ! $legacyLearnMenu->trashed()) {
            $legacyLearnMenu->delete();
        }

        // 5) Grant management (CRUD) to Administrator + Marketing; read-only to Agent.
        foreach ([self::ADMINISTRATOR_ROLE_ID, self::MARKETING_ROLE_ID] as $roleId) {
            $this->grant($roleId, self::MENU_MANAGE_ID, create: true, read: true, update: true, delete: true);
            $this->grant($roleId, self::MENU_SETTINGS_ID, create: false, read: true, update: true, delete: false);
        }

        $this->grant(self::AGENT_ROLE_ID, self::MENU_MANAGE_ID, create: false, read: true, update: false, delete: false);
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
                'is_custom_1' => false, 'is_custom_2' => false, 'is_custom_3' => false,
                'is_custom_4' => false, 'is_custom_5' => false,
            ]
        );
    }
}
