<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerNetworkMenuSeeder extends Seeder
{
    private const AGENT_ROLE_ID = '2ac6f6a1-7b8c-4d9e-9f10-111213141516';
    private const AGENT_IAM_ACCESS_ID = '2ac6f6a1-7b8c-4d9e-9f10-111213141517';

    private array $menus = [
        [
            'id' => '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c001',
            'parent_id' => null,
            'name' => 'Partner Network',
            'code' => 'partner-network',
            'text_sidebar' => 'Partner Network',
            'icon' => 'ti ti-affiliate',
            'url_path' => 'partner-network',
            'route_name' => 'partner.reports.index',
            'slug' => 'partner-network',
            'level_sidebar' => 1,
            'order_number' => 35,
            'has_page' => false,
            'has_create' => false,
            'has_update' => false,
            'has_delete' => false,
        ],
        [
            'id' => '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c002',
            'name' => 'Partner Application',
            'code' => 'partner-application',
            'text_sidebar' => 'Applications',
            'icon' => 'ti ti-clipboard-list',
            'url_path' => 'partner-network/applications',
            'route_name' => 'partner.applications.index',
            'slug' => 'partner-application',
            'level_sidebar' => 2,
            'order_number' => 1,
            'has_create' => true,
            'has_update' => true,
            'has_delete' => false,
        ],
        [
            'id' => '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c003',
            'name' => 'Partner Agent',
            'code' => 'partner-agent',
            'text_sidebar' => 'Agents',
            'icon' => 'ti ti-users',
            'url_path' => 'partner-network/agents',
            'route_name' => 'partner.agents.index',
            'slug' => 'partner-agent',
            'level_sidebar' => 2,
            'order_number' => 2,
            'has_create' => false,
            'has_update' => true,
            'has_delete' => false,
        ],
        [
            'id' => '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c004',
            'name' => 'Partner Reseller',
            'code' => 'partner-reseller',
            'text_sidebar' => 'Resellers',
            'icon' => 'ti ti-user-star',
            'url_path' => 'partner-network/resellers',
            'route_name' => 'partner.resellers.index',
            'slug' => 'partner-reseller',
            'level_sidebar' => 2,
            'order_number' => 3,
            'has_create' => false,
            'has_update' => true,
            'has_delete' => false,
        ],
    ];

    public function run(): void
    {
        DB::table('master_data.roles')->updateOrInsert(
            ['id' => self::AGENT_ROLE_ID],
            ['name' => 'Agent', 'updated_at' => now(), 'created_at' => now()]
        );

        DB::table('auth.iam_accesses')->updateOrInsert(
            ['id' => self::AGENT_IAM_ACCESS_ID],
            ['role_id' => self::AGENT_ROLE_ID, 'is_notification' => false, 'updated_at' => now(), 'created_at' => now()]
        );

        $parentId = null;
        foreach ($this->menus as $menu) {
            $menu['parent_id'] = $menu['parent_id'] ?? $parentId;
            $menu['has_page'] = $menu['has_page'] ?? true;
            $menu['has_read'] = true;
            $menu['has_custom1'] = false;
            $menu['has_custom2'] = false;
            $menu['has_custom3'] = false;
            $menu['has_custom4'] = false;
            $menu['has_custom5'] = false;
            $menu['is_label'] = false;
            $menu['updated_at'] = now();

            DB::table('master_data.menus')->updateOrInsert(
                ['id' => $menu['id']],
                array_merge($menu, ['created_at' => now()])
            );

            if ($menu['code'] === 'partner-network') {
                $parentId = $menu['id'];
            }
        }

        $menuIds = collect($this->menus)->pluck('id');
        $iamAccessRows = DB::table('auth.iam_accesses')
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($iamAccessRows as $iamAccessId) {
            foreach ($menuIds as $menuId) {
                $menu = collect($this->menus)->firstWhere('id', $menuId);
                $isAgentRole = $iamAccessId === self::AGENT_IAM_ACCESS_ID;
                $agentCanWrite = in_array($menu['code'] ?? '', ['partner-application'], true);
                $agentCanRead = in_array($menu['code'] ?? '', ['partner-network', 'partner-application', 'partner-agent', 'partner-reseller'], true);
                $exists = DB::table('auth.iam_has_accesses')
                    ->where('iam_access_id', $iamAccessId)
                    ->where('sidebar_menu_id', $menuId)
                    ->exists();

                $values = [
                    'is_create' => $isAgentRole ? $agentCanWrite : true,
                    'is_read' => $isAgentRole ? $agentCanRead : true,
                    'is_update' => $isAgentRole ? $agentCanWrite : true,
                    'is_delete' => $isAgentRole ? false : true,
                    'is_custom_1' => false,
                    'is_custom_2' => false,
                    'is_custom_3' => false,
                    'is_custom_4' => false,
                    'is_custom_5' => false,
                ];

                if ($exists) {
                    DB::table('auth.iam_has_accesses')
                        ->where('iam_access_id', $iamAccessId)
                        ->where('sidebar_menu_id', $menuId)
                        ->update($values);
                } else {
                    DB::table('auth.iam_has_accesses')->insert(array_merge($values, [
                        'id' => (string) Str::uuid(),
                        'iam_access_id' => $iamAccessId,
                        'sidebar_menu_id' => $menuId,
                    ]));
                }
            }
        }
    }
}
