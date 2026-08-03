<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PARENT_ID = 'a1c00001-f001-4001-8001-000000000001';

    /**
     * Logical Accounting menu order:
     * Master → Setup → Transactions → Cash → Ledgers → Statements
     *
     * @var array<string, int>
     */
    private const ORDER_BY_ID = [
        'a1c00001-f001-4001-8001-000000000002' => 1,  // Chart of Accounts
        'a1c00001-f001-4001-8001-000000000004' => 2,  // Cash Flow Category
        'a1c00001-f001-4001-8001-000000000003' => 3,  // Account Mapping
        'a1c00001-f001-4001-8001-000000000005' => 4,  // Fiscal Calendar
        'a1c00001-f001-4001-8001-000000000006' => 5,  // Beginning Balance
        'a1c00001-f001-4001-8001-000000000007' => 6,  // Journal Entry
        'a1c00001-f001-4001-8001-000000000008' => 7,  // Jurnal Umum
        'a1c00001-f001-4001-8001-000000000009' => 8,  // Cash & Bank
        'a1c00001-f001-4001-8001-00000000000d' => 9,  // General Ledger
        'a1c00001-f001-4001-8001-00000000000a' => 10, // Balance Sheet
        'a1c00001-f001-4001-8001-00000000000b' => 11, // Income Statement
        'a1c00001-f001-4001-8001-00000000000c' => 12, // Cash Flow
    ];

    /** @var array<string, string> */
    private const SIDEBAR_LABELS = [
        'a1c00001-f001-4001-8001-000000000002' => 'Chart of Accounts',
        'a1c00001-f001-4001-8001-000000000004' => 'CF Category',
        'a1c00001-f001-4001-8001-000000000003' => 'Account Mapping',
        'a1c00001-f001-4001-8001-000000000005' => 'Fiscal Calendar',
        'a1c00001-f001-4001-8001-000000000006' => 'Beginning Balance',
        'a1c00001-f001-4001-8001-000000000007' => 'Journal Entry',
        'a1c00001-f001-4001-8001-000000000008' => 'Jurnal Umum',
        'a1c00001-f001-4001-8001-000000000009' => 'Cash & Bank',
        'a1c00001-f001-4001-8001-00000000000d' => 'General Ledger',
        'a1c00001-f001-4001-8001-00000000000a' => 'Balance Sheet',
        'a1c00001-f001-4001-8001-00000000000b' => 'Income Statement',
        'a1c00001-f001-4001-8001-00000000000c' => 'Cash Flow',
    ];

    /** @var array<string, string> */
    private const ICONS = [
        'a1c00001-f001-4001-8001-000000000002' => 'ti ti-sitemap',
        'a1c00001-f001-4001-8001-000000000004' => 'ti ti-arrows-split',
        'a1c00001-f001-4001-8001-000000000003' => 'ti ti-link',
        'a1c00001-f001-4001-8001-000000000005' => 'ti ti-calendar-stats',
        'a1c00001-f001-4001-8001-000000000006' => 'ti ti-scale',
        'a1c00001-f001-4001-8001-000000000007' => 'ti ti-notebook',
        'a1c00001-f001-4001-8001-000000000008' => 'ti ti-book',
        'a1c00001-f001-4001-8001-000000000009' => 'ti ti-building-bank',
        'a1c00001-f001-4001-8001-00000000000d' => 'ti ti-list-details',
        'a1c00001-f001-4001-8001-00000000000a' => 'ti ti-report-analytics',
        'a1c00001-f001-4001-8001-00000000000b' => 'ti ti-chart-bar',
        'a1c00001-f001-4001-8001-00000000000c' => 'ti ti-arrows-exchange',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('master_data.menus')) {
            return;
        }

        DB::table('master_data.menus')
            ->where('id', self::PARENT_ID)
            ->update([
                'name' => 'Accounting',
                'text_sidebar' => 'Accounting',
                'icon' => 'ti ti-calculator',
                'updated_at' => now(),
            ]);

        foreach (self::ORDER_BY_ID as $id => $order) {
            $payload = [
                'order_number' => $order,
                'updated_at' => now(),
            ];
            if (isset(self::SIDEBAR_LABELS[$id])) {
                $payload['text_sidebar'] = self::SIDEBAR_LABELS[$id];
            }
            if (isset(self::ICONS[$id])) {
                $payload['icon'] = self::ICONS[$id];
            }

            DB::table('master_data.menus')->where('id', $id)->update($payload);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('master_data.menus')) {
            return;
        }

        DB::table('master_data.menus')
            ->where('id', self::PARENT_ID)
            ->update([
                'name' => 'Finance',
                'text_sidebar' => 'Finance',
                'icon' => 'ti ti-report-money',
                'updated_at' => now(),
            ]);

        $legacyOrder = [
            'a1c00001-f001-4001-8001-000000000002' => 1,
            'a1c00001-f001-4001-8001-000000000003' => 2,
            'a1c00001-f001-4001-8001-000000000004' => 3,
            'a1c00001-f001-4001-8001-000000000005' => 4,
            'a1c00001-f001-4001-8001-000000000006' => 5,
            'a1c00001-f001-4001-8001-000000000007' => 6,
            'a1c00001-f001-4001-8001-000000000008' => 7,
            'a1c00001-f001-4001-8001-000000000009' => 8,
            'a1c00001-f001-4001-8001-00000000000a' => 9,
            'a1c00001-f001-4001-8001-00000000000b' => 10,
            'a1c00001-f001-4001-8001-00000000000c' => 11,
            'a1c00001-f001-4001-8001-00000000000d' => 12,
        ];

        foreach ($legacyOrder as $id => $order) {
            DB::table('master_data.menus')->where('id', $id)->update([
                'order_number' => $order,
                'updated_at' => now(),
            ]);
        }
    }
};
