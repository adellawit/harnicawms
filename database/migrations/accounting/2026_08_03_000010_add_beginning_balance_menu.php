<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const FINANCE_PARENT_ID = 'a1c00001-f001-4001-8001-000000000001';

    private const MENU_ID = 'a1c00001-f001-4001-8001-000000000006';

    private const SUPER_ADMIN_IAM_ACCESS_ID = '87d14961-0c14-474f-a6fa-b1130b521d39';

    public function up(): void
    {
        if (! Schema::hasTable('master_data.menus')) {
            return;
        }

        $exists = DB::table('master_data.menus')
            ->where(function ($q) {
                $q->where('id', self::MENU_ID)
                    ->orWhere('code', 'beginning_balance');
            })
            ->exists();

        if (! $exists && DB::table('master_data.menus')->where('id', self::FINANCE_PARENT_ID)->exists()) {
            DB::table('master_data.menus')->insert([
                'id' => self::MENU_ID,
                'parent_id' => self::FINANCE_PARENT_ID,
                'name' => 'Beginning Balance',
                'code' => 'beginning_balance',
                'text_sidebar' => 'Beginning Balance',
                'icon' => 'ti ti-scale',
                'has_page' => false,
                'url_path' => 'finance/beginning-balance',
                'route_name' => 'finance.beginning-balance.index.view',
                'slug' => 'beginning-balance',
                'level_sidebar' => 2,
                'order_number' => 5,
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
        }

        if (! Schema::hasTable('auth.iam_has_accesses')) {
            return;
        }

        if (! DB::table('auth.iam_accesses')->where('id', self::SUPER_ADMIN_IAM_ACCESS_ID)->exists()) {
            return;
        }

        if (! DB::table('master_data.menus')->where('id', self::MENU_ID)->exists()) {
            return;
        }

        $granted = DB::table('auth.iam_has_accesses')
            ->where('iam_access_id', self::SUPER_ADMIN_IAM_ACCESS_ID)
            ->where('sidebar_menu_id', self::MENU_ID)
            ->exists();

        if ($granted) {
            return;
        }

        DB::table('auth.iam_has_accesses')->insert([
            'id' => (string) Str::uuid(),
            'iam_access_id' => self::SUPER_ADMIN_IAM_ACCESS_ID,
            'sidebar_menu_id' => self::MENU_ID,
            'is_create' => true,
            'is_read' => true,
            'is_update' => true,
            'is_delete' => true,
            'is_custom_1' => false,
            'is_custom_2' => false,
            'is_custom_3' => false,
            'is_custom_4' => false,
            'is_custom_5' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('auth.iam_has_accesses')) {
            DB::table('auth.iam_has_accesses')
                ->where('sidebar_menu_id', self::MENU_ID)
                ->delete();
        }

        if (Schema::hasTable('master_data.menus')) {
            DB::table('master_data.menus')->where('id', self::MENU_ID)->delete();
        }
    }
};
