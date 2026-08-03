<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const FINANCE_PARENT_ID = 'a1c00001-f001-4001-8001-000000000001';

    private const COA_MENU_ID = 'a1c00001-f001-4001-8001-000000000002';

    private const MAPPING_MENU_ID = 'a1c00001-f001-4001-8001-000000000003';

    private const SUPER_ADMIN_IAM_ACCESS_ID = '87d14961-0c14-474f-a6fa-b1130b521d39';

    public function up(): void
    {
        if (! Schema::hasTable('auth.iam_has_accesses') || ! Schema::hasTable('master_data.menus')) {
            return;
        }

        $iamExists = DB::table('auth.iam_accesses')
            ->where('id', self::SUPER_ADMIN_IAM_ACCESS_ID)
            ->exists();

        if (! $iamExists) {
            return;
        }

        foreach ([self::FINANCE_PARENT_ID, self::COA_MENU_ID, self::MAPPING_MENU_ID] as $menuId) {
            if (! DB::table('master_data.menus')->where('id', $menuId)->exists()) {
                continue;
            }

            $already = DB::table('auth.iam_has_accesses')
                ->where('iam_access_id', self::SUPER_ADMIN_IAM_ACCESS_ID)
                ->where('sidebar_menu_id', $menuId)
                ->exists();

            if ($already) {
                continue;
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
    }

    public function down(): void
    {
        if (! Schema::hasTable('auth.iam_has_accesses')) {
            return;
        }

        DB::table('auth.iam_has_accesses')
            ->where('iam_access_id', self::SUPER_ADMIN_IAM_ACCESS_ID)
            ->whereIn('sidebar_menu_id', [
                self::FINANCE_PARENT_ID,
                self::COA_MENU_ID,
                self::MAPPING_MENU_ID,
            ])
            ->delete();
    }
};
