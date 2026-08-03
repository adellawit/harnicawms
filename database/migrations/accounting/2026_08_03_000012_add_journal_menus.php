<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const FINANCE_PARENT_ID = 'a1c00001-f001-4001-8001-000000000001';

    private const JOURNAL_ENTRY_MENU_ID = 'a1c00001-f001-4001-8001-000000000007';

    private const JURNAL_UMUM_MENU_ID = 'a1c00001-f001-4001-8001-000000000008';

    private const SUPER_ADMIN_IAM_ACCESS_ID = '87d14961-0c14-474f-a6fa-b1130b521d39';

    public function up(): void
    {
        if (! Schema::hasTable('master_data.menus')) {
            return;
        }

        if (DB::table('master_data.menus')->where('id', self::FINANCE_PARENT_ID)->exists()) {
            $this->insertMenuIfMissing(self::JOURNAL_ENTRY_MENU_ID, [
                'name' => 'Journal Entry',
                'code' => 'journal_entry',
                'text_sidebar' => 'Journal Entry',
                'icon' => 'ti ti-notebook',
                'url_path' => 'finance/journal-entry',
                'route_name' => 'finance.journal-entry.index.view',
                'slug' => 'journal-entry',
                'order_number' => 6,
            ]);

            $this->insertMenuIfMissing(self::JURNAL_UMUM_MENU_ID, [
                'name' => 'Jurnal Umum',
                'code' => 'jurnal_umum',
                'text_sidebar' => 'Jurnal Umum',
                'icon' => 'ti ti-book',
                'url_path' => 'finance/jurnal-umum',
                'route_name' => 'finance.jurnal-umum.index.view',
                'slug' => 'jurnal-umum',
                'order_number' => 7,
            ]);
        }

        $this->grantIam(self::JOURNAL_ENTRY_MENU_ID);
        $this->grantIam(self::JURNAL_UMUM_MENU_ID);
    }

    private function insertMenuIfMissing(string $id, array $data): void
    {
        $exists = DB::table('master_data.menus')
            ->where(function ($q) use ($id, $data) {
                $q->where('id', $id)->orWhere('code', $data['code']);
            })
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('master_data.menus')->insert([
            'id' => $id,
            'parent_id' => self::FINANCE_PARENT_ID,
            'name' => $data['name'],
            'code' => $data['code'],
            'text_sidebar' => $data['text_sidebar'],
            'icon' => $data['icon'],
            'has_page' => false,
            'url_path' => $data['url_path'],
            'route_name' => $data['route_name'],
            'slug' => $data['slug'],
            'level_sidebar' => 2,
            'order_number' => $data['order_number'],
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

    private function grantIam(string $menuId): void
    {
        if (! Schema::hasTable('auth.iam_has_accesses')) {
            return;
        }

        if (! DB::table('auth.iam_accesses')->where('id', self::SUPER_ADMIN_IAM_ACCESS_ID)->exists()) {
            return;
        }

        if (! DB::table('master_data.menus')->where('id', $menuId)->exists()) {
            return;
        }

        $granted = DB::table('auth.iam_has_accesses')
            ->where('iam_access_id', self::SUPER_ADMIN_IAM_ACCESS_ID)
            ->where('sidebar_menu_id', $menuId)
            ->exists();

        if ($granted) {
            return;
        }

        DB::table('auth.iam_has_accesses')->insert([
            'id' => (string) Str::uuid(),
            'iam_access_id' => self::SUPER_ADMIN_IAM_ACCESS_ID,
            'sidebar_menu_id' => $menuId,
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
        foreach ([self::JOURNAL_ENTRY_MENU_ID, self::JURNAL_UMUM_MENU_ID] as $menuId) {
            if (Schema::hasTable('auth.iam_has_accesses')) {
                DB::table('auth.iam_has_accesses')->where('sidebar_menu_id', $menuId)->delete();
            }
            if (Schema::hasTable('master_data.menus')) {
                DB::table('master_data.menus')->where('id', $menuId)->delete();
            }
        }
    }
};
