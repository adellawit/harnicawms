<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgentMenuSeeder extends Seeder
{
    public const MENU_ID = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

    public function run(): void
    {
        $dashboard = DB::table('master_data.menus')
            ->where('code', 'dashboard')
            ->whereNull('deleted_at')
            ->first();

        if ($dashboard === null) {
            $this->command?->warn('Dashboard menu not found — skipping AI Assistant menu seed.');

            return;
        }

        $exists = DB::table('master_data.menus')
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->where('id', self::MENU_ID)
                    ->orWhere('name', 'AI Assistant');
            })
            ->exists();

        if (! $exists) {
            DB::table('master_data.menus')->insert([
                'id' => self::MENU_ID,
                'parent_id' => $dashboard->id,
                'name' => 'AI Assistant',
                'code' => 'ai_assistant',
                'text_sidebar' => 'AI Assistant',
                'icon' => 'ti ti-message-chatbot',
                'has_page' => false,
                'url_path' => null,
                'route_name' => null,
                'slug' => 'ai-assistant',
                'level_sidebar' => 2,
                'order_number' => 2,
                'is_label' => false,
                'has_create' => false,
                'has_update' => false,
                'has_read' => true,
                'has_delete' => false,
                'has_custom1' => false,
                'has_custom2' => false,
                'has_custom3' => false,
                'has_custom4' => false,
                'has_custom5' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command?->info('AI Assistant menu created.');
        } else {
            $this->command?->info('AI Assistant menu already exists.');
        }

        $menuId = DB::table('master_data.menus')
            ->where('name', 'AI Assistant')
            ->whereNull('deleted_at')
            ->value('id');

        if ($menuId === null) {
            return;
        }

        $superAdminIamAccessId = '87d14961-0c14-474f-a6fa-b1130b521d39';

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
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'iam_access_id' => $iamAccessId,
                'sidebar_menu_id' => $menuId,
                'is_create' => false,
                'is_read' => true,
                'is_update' => false,
                'is_delete' => false,
                'is_custom_1' => false,
                'is_custom_2' => false,
                'is_custom_3' => false,
                'is_custom_4' => false,
                'is_custom_5' => false,
            ]);
        }

        $this->command?->info('AI Assistant read access granted to all IAM access profiles.');
    }
}
