<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_ID = 'a1c00001-f001-4001-8001-000000000007';

    public function up(): void
    {
        if (! Schema::hasTable('master_data.menus')) {
            return;
        }

        DB::table('master_data.menus')
            ->where('id', self::MENU_ID)
            ->orWhere('code', 'journal_entry')
            ->update([
                'url_path' => 'finance/journal-entry',
                'route_name' => 'finance.journal-entry.index.view',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('master_data.menus')) {
            return;
        }

        DB::table('master_data.menus')
            ->where('id', self::MENU_ID)
            ->orWhere('code', 'journal_entry')
            ->update([
                'url_path' => 'finance/journal-entry/insert',
                'route_name' => 'finance.journal-entry.insert.view',
                'updated_at' => now(),
            ]);
    }
};
