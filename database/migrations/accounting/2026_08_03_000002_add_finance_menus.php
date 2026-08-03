<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FINANCE_PARENT_ID = 'a1c00001-f001-4001-8001-000000000001';

    private const COA_MENU_ID = 'a1c00001-f001-4001-8001-000000000002';

    private const MAPPING_MENU_ID = 'a1c00001-f001-4001-8001-000000000003';

    public function up(): void
    {
        $menusTableExists = DB::selectOne("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'master_data' AND table_name = 'menus'
        ");

        if (! $menusTableExists) {
            return;
        }

        $now = now();

        $financeExists = DB::table('master_data.menus')
            ->where(function ($q) {
                $q->where('id', self::FINANCE_PARENT_ID)
                    ->orWhere('code', 'finance');
            })
            ->exists();

        if (! $financeExists) {
            DB::table('master_data.menus')->insert([
                'id' => self::FINANCE_PARENT_ID,
                'parent_id' => null,
                'name' => 'Finance',
                'code' => 'finance',
                'text_sidebar' => 'Finance',
                'icon' => 'ti ti-report-money',
                'has_page' => true,
                'url_path' => 'finance',
                'route_name' => null,
                'slug' => 'finance',
                'level_sidebar' => 1,
                'order_number' => 11,
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
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $coaExists = DB::table('master_data.menus')
            ->where(function ($q) {
                $q->where('id', self::COA_MENU_ID)
                    ->orWhere('code', 'chart_of_accounts');
            })
            ->exists();

        if (! $coaExists) {
            DB::table('master_data.menus')->insert([
                'id' => self::COA_MENU_ID,
                'parent_id' => self::FINANCE_PARENT_ID,
                'name' => 'Chart of Accounts',
                'code' => 'chart_of_accounts',
                'text_sidebar' => 'Chart of Accounts',
                'icon' => 'ti ti-list-details',
                'has_page' => false,
                'url_path' => 'finance/chart-of-accounts',
                'route_name' => 'finance.chart-of-accounts.index.view',
                'slug' => 'chart-of-accounts',
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
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $mappingExists = DB::table('master_data.menus')
            ->where(function ($q) {
                $q->where('id', self::MAPPING_MENU_ID)
                    ->orWhere('code', 'account_mapping');
            })
            ->exists();

        if (! $mappingExists) {
            DB::table('master_data.menus')->insert([
                'id' => self::MAPPING_MENU_ID,
                'parent_id' => self::FINANCE_PARENT_ID,
                'name' => 'Account Mapping',
                'code' => 'account_mapping',
                'text_sidebar' => 'Account Mapping',
                'icon' => 'ti ti-arrows-exchange',
                'has_page' => false,
                'url_path' => 'finance/account-mapping',
                'route_name' => 'finance.account-mapping.index.view',
                'slug' => 'account-mapping',
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
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('master_data.menus')
            ->whereIn('id', [self::MAPPING_MENU_ID, self::COA_MENU_ID, self::FINANCE_PARENT_ID])
            ->delete();
    }
};
