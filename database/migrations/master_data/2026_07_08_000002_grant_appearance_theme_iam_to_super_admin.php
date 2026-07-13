<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const MENU_ID = 'e0123456-f012-4234-5678-9abcdef01236';

    private const SUPER_ADMIN_IAM_ACCESS_ID = '87d14961-0c14-474f-a6fa-b1130b521d39';

    public function up(): void
    {
        $menuExists = DB::table('master_data.menus')
            ->where('id', self::MENU_ID)
            ->exists();

        $iamExists = DB::table('auth.iam_accesses')
            ->where('id', self::SUPER_ADMIN_IAM_ACCESS_ID)
            ->exists();

        if (! $menuExists || ! $iamExists) {
            return;
        }

        $alreadyGranted = DB::table('auth.iam_has_accesses')
            ->where('iam_access_id', self::SUPER_ADMIN_IAM_ACCESS_ID)
            ->where('sidebar_menu_id', self::MENU_ID)
            ->whereNull('deleted_at')
            ->exists();

        if ($alreadyGranted) {
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
        DB::table('auth.iam_has_accesses')
            ->where('iam_access_id', self::SUPER_ADMIN_IAM_ACCESS_ID)
            ->where('sidebar_menu_id', self::MENU_ID)
            ->delete();
    }
};
