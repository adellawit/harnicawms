<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const MENU_ID = '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c008';

    private const SUPER_ADMIN_IAM_ACCESS_ID = '87d14961-0c14-474f-a6fa-b1130b521d39';

    private const ADMIN_IAM_ACCESS_ID = 'b0763f22-c9d1-41de-b7b9-28b523a7a354';

    public function up(): void
    {
        if (! Schema::hasTable('auth.iam_accesses') || ! Schema::hasTable('auth.iam_has_accesses')) {
            return;
        }

        $menuExists = DB::table('master_data.menus')
            ->where('id', self::MENU_ID)
            ->exists();

        if (! $menuExists) {
            return;
        }

        foreach ([self::SUPER_ADMIN_IAM_ACCESS_ID, self::ADMIN_IAM_ACCESS_ID] as $iamAccessId) {
            $iamExists = DB::table('auth.iam_accesses')
                ->where('id', $iamAccessId)
                ->exists();

            if (! $iamExists) {
                continue;
            }

            $alreadyGranted = DB::table('auth.iam_has_accesses')
                ->where('iam_access_id', $iamAccessId)
                ->where('sidebar_menu_id', self::MENU_ID)
                ->whereNull('deleted_at')
                ->exists();

            if ($alreadyGranted) {
                continue;
            }

            DB::table('auth.iam_has_accesses')->insert([
                'id' => (string) Str::uuid(),
                'iam_access_id' => $iamAccessId,
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
    }

    public function down(): void
    {
        DB::table('auth.iam_has_accesses')
            ->where('sidebar_menu_id', self::MENU_ID)
            ->whereIn('iam_access_id', [
                self::SUPER_ADMIN_IAM_ACCESS_ID,
                self::ADMIN_IAM_ACCESS_ID,
            ])
            ->delete();
    }
};
