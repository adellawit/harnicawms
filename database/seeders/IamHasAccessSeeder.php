<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IamHasAccessSeeder extends Seeder
{
    private const SUPER_ADMIN_IAM_ACCESS_ID = Role::SUPER_ADMIN_IAM_ACCESS_ID;

    private const AGENT_IAM_ACCESS_ID = '2ac6f6a1-7b8c-4d9e-9f10-111213141517';

    private const PARTNER_MENU_CODES = [
        'partner-network',
        'partner-application',
        'partner-agent',
        'partner-reseller',
    ];

    /**
     * Sinkronkan IAM dari master_data.menus.
     * Jalankan setelah MenuSeeder — struktur menu (Customer > Network,
     * Product > Production, tanpa Stok Masuk) sudah didefinisikan di sana.
     */
    public function run(): void
    {
        $menus = DB::table('master_data.menus')
            ->whereNull('deleted_at')
            ->orderBy('order_number')
            ->get();

        $hasAccesses = [];

        foreach ($menus as $menu) {
            $hasAccesses[] = $this->buildAccessRow(
                self::SUPER_ADMIN_IAM_ACCESS_ID,
                $menu,
                true,
                true,
                true,
                true
            );
        }

        foreach ($menus as $menu) {
            if (! in_array($menu->code, self::PARTNER_MENU_CODES, true)) {
                continue;
            }

            $agentCanWrite = $menu->code === 'partner-application';

            $hasAccesses[] = $this->buildAccessRow(
                self::AGENT_IAM_ACCESS_ID,
                $menu,
                $agentCanWrite,
                true,
                $agentCanWrite,
                false
            );
        }

        DB::statement('TRUNCATE TABLE auth.iam_has_accesses CASCADE');

        DB::table('auth.iam_has_accesses')->insert($hasAccesses);

        $this->command->info('IAM Has Access seeded successfully! Total access rows: ' . count($hasAccesses));
    }

    private function buildAccessRow(
        string $iamAccessId,
        object $menu,
        bool $isCreate,
        bool $isRead,
        bool $isUpdate,
        bool $isDelete
    ): array {
        return [
            'id' => (string) Str::uuid(),
            'iam_access_id' => $iamAccessId,
            'sidebar_menu_id' => $menu->id,
            'is_create' => $isCreate,
            'is_read' => $isRead,
            'is_update' => $isUpdate,
            'is_delete' => $isDelete,
            'is_custom_1' => false,
            'is_custom_2' => false,
            'is_custom_3' => false,
            'is_custom_4' => false,
            'is_custom_5' => false,
        ];
    }
}
