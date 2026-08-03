<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const FINANCE_PARENT_ID = 'a1c00001-f001-4001-8001-000000000001';

    private const SUPER_ADMIN_IAM_ACCESS_ID = '87d14961-0c14-474f-a6fa-b1130b521d39';

    /** @var list<array{id: string, name: string, code: string, icon: string, url_path: string, route_name: string, slug: string, order_number: int}> */
    private const MENUS = [
        [
            'id' => 'a1c00001-f001-4001-8001-00000000000a',
            'name' => 'Balance Sheet',
            'code' => 'balance_sheet',
            'icon' => 'ti ti-scale',
            'url_path' => 'finance/balance-sheet',
            'route_name' => 'finance.balance-sheet.index.view',
            'slug' => 'balance-sheet',
            'order_number' => 9,
        ],
        [
            'id' => 'a1c00001-f001-4001-8001-00000000000b',
            'name' => 'Income Statement',
            'code' => 'income_statement',
            'icon' => 'ti ti-chart-bar',
            'url_path' => 'finance/income-statement',
            'route_name' => 'finance.income-statement.index.view',
            'slug' => 'income-statement',
            'order_number' => 10,
        ],
        [
            'id' => 'a1c00001-f001-4001-8001-00000000000c',
            'name' => 'Cash Flow',
            'code' => 'cash_flow_report',
            'icon' => 'ti ti-arrows-exchange',
            'url_path' => 'finance/cash-flow',
            'route_name' => 'finance.cash-flow.index.view',
            'slug' => 'cash-flow',
            'order_number' => 11,
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('master_data.menus')) {
            return;
        }

        if (! DB::table('master_data.menus')->where('id', self::FINANCE_PARENT_ID)->exists()) {
            return;
        }

        foreach (self::MENUS as $menu) {
            $exists = DB::table('master_data.menus')
                ->where(function ($q) use ($menu) {
                    $q->where('id', $menu['id'])->orWhere('code', $menu['code']);
                })
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('master_data.menus')->insert([
                'id' => $menu['id'],
                'parent_id' => self::FINANCE_PARENT_ID,
                'name' => $menu['name'],
                'code' => $menu['code'],
                'text_sidebar' => $menu['name'],
                'icon' => $menu['icon'],
                'has_page' => false,
                'url_path' => $menu['url_path'],
                'route_name' => $menu['route_name'],
                'slug' => $menu['slug'],
                'level_sidebar' => 2,
                'order_number' => $menu['order_number'],
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
        }

        if (! Schema::hasTable('auth.iam_has_accesses')) {
            return;
        }

        if (! DB::table('auth.iam_accesses')->where('id', self::SUPER_ADMIN_IAM_ACCESS_ID)->exists()) {
            return;
        }

        foreach (self::MENUS as $menu) {
            if (! DB::table('master_data.menus')->where('id', $menu['id'])->exists()) {
                continue;
            }

            $granted = DB::table('auth.iam_has_accesses')
                ->where('iam_access_id', self::SUPER_ADMIN_IAM_ACCESS_ID)
                ->where('sidebar_menu_id', $menu['id'])
                ->exists();

            if ($granted) {
                continue;
            }

            DB::table('auth.iam_has_accesses')->insert([
                'id' => (string) Str::uuid(),
                'iam_access_id' => self::SUPER_ADMIN_IAM_ACCESS_ID,
                'sidebar_menu_id' => $menu['id'],
                'is_create' => false,
                'is_read' => true,
                'is_update' => false,
                'is_delete' => false,
                'is_custom_1' => false,
                'is_custom_2' => false,
                'is_custom_3' => false,
                'is_custom_4' => false,
                'is_custom_5' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $ids = array_column(self::MENUS, 'id');

        if (Schema::hasTable('auth.iam_has_accesses')) {
            DB::table('auth.iam_has_accesses')->whereIn('sidebar_menu_id', $ids)->delete();
        }
        if (Schema::hasTable('master_data.menus')) {
            DB::table('master_data.menus')->whereIn('id', $ids)->delete();
        }
    }
};
