<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const MENU_ID = 'd0123456-f012-4234-5678-9abcdef01241';

    private const SOURCE_MENU_CODE = 'payment_gateway_configuration';

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

        $sourceMenuId = DB::table('master_data.menus')
            ->where('code', self::SOURCE_MENU_CODE)
            ->value('id');

        $sourceGrants = $sourceMenuId
            ? DB::table('auth.iam_has_accesses')
                ->where('sidebar_menu_id', $sourceMenuId)
                ->whereNull('deleted_at')
                ->get()
            : collect();

        if ($sourceGrants->isEmpty()) {
            $superAdminIamId = '87d14961-0c14-474f-a6fa-b1130b521d39';
            if (DB::table('auth.iam_accesses')->where('id', $superAdminIamId)->exists()) {
                $sourceGrants = collect([(object) [
                    'iam_access_id' => $superAdminIamId,
                    'is_create' => true,
                    'is_read' => true,
                    'is_update' => true,
                    'is_delete' => true,
                    'is_custom_1' => false,
                    'is_custom_2' => false,
                    'is_custom_3' => false,
                    'is_custom_4' => false,
                    'is_custom_5' => false,
                ]]);
            }
        }

        foreach ($sourceGrants as $grant) {
            $already = DB::table('auth.iam_has_accesses')
                ->where('iam_access_id', $grant->iam_access_id)
                ->where('sidebar_menu_id', self::MENU_ID)
                ->whereNull('deleted_at')
                ->exists();

            if ($already) {
                continue;
            }

            DB::table('auth.iam_has_accesses')->insert([
                'id' => (string) Str::uuid(),
                'iam_access_id' => $grant->iam_access_id,
                'sidebar_menu_id' => self::MENU_ID,
                'is_create' => (bool) $grant->is_create,
                'is_read' => (bool) $grant->is_read,
                'is_update' => (bool) $grant->is_update,
                'is_delete' => (bool) $grant->is_delete,
                'is_custom_1' => (bool) $grant->is_custom_1,
                'is_custom_2' => (bool) $grant->is_custom_2,
                'is_custom_3' => (bool) $grant->is_custom_3,
                'is_custom_4' => (bool) $grant->is_custom_4,
                'is_custom_5' => (bool) $grant->is_custom_5,
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
            ->where('sidebar_menu_id', self::MENU_ID)
            ->delete();
    }
};
