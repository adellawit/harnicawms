<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiConfigurationMenuSeeder extends Seeder
{
    public const MENU_ID = 'c8901234-def0-4234-5678-9abcdef01234';

    public function run(): void
    {
        $settings = DB::table('master_data.menus')
            ->where('code', 'settings')
            ->whereNull('deleted_at')
            ->first();

        if ($settings === null) {
            $this->command?->warn('Settings menu not found — skipping AI Chat Configuration menu seed.');

            return;
        }

        $exists = DB::table('master_data.menus')
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->where('id', self::MENU_ID)
                    ->orWhere('name', 'AI Chat Configuration');
            })
            ->exists();

        if (! $exists) {
            DB::table('master_data.menus')->insert([
                'id' => self::MENU_ID,
                'parent_id' => $settings->id,
                'name' => 'AI Chat Configuration',
                'code' => 'ai_chat_configuration',
                'text_sidebar' => 'AI Chat Configuration',
                'icon' => 'ti ti-message-chatbot',
                'has_page' => false,
                'url_path' => 'settings/ai-configuration',
                'route_name' => 'settings.ai-configuration.index.view',
                'slug' => 'ai-chat-configuration',
                'level_sidebar' => 2,
                'order_number' => 7,
                'is_label' => false,
                'has_create' => false,
                'has_update' => true,
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

            $this->command?->info('AI Chat Configuration menu created under Settings.');
        } else {
            DB::table('master_data.menus')
                ->where('name', 'AI Chat Configuration')
                ->whereNull('deleted_at')
                ->update([
                    'parent_id' => $settings->id,
                    'url_path' => 'settings/ai-configuration',
                    'route_name' => 'settings.ai-configuration.index.view',
                    'icon' => 'ti ti-message-chatbot',
                    'order_number' => 7,
                    'has_update' => true,
                    'has_read' => true,
                    'updated_at' => now(),
                ]);

            $this->command?->info('AI Chat Configuration menu updated.');
        }

        $menuId = DB::table('master_data.menus')
            ->where('name', 'AI Chat Configuration')
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
                'is_create' => false,
                'is_read' => true,
                'is_update' => true,
                'is_delete' => false,
                'is_custom_1' => false,
                'is_custom_2' => false,
                'is_custom_3' => false,
                'is_custom_4' => false,
                'is_custom_5' => false,
            ]);
        }

        $this->command?->info('AI Chat Configuration access granted to all IAM profiles.');
    }
}
