<?php

namespace Database\Seeders;

use App\Models\IamAccess;
use App\Models\IamHasAccess;
use App\Models\Menu;
use Illuminate\Database\Seeder;

class ResellerMappingAccessSeeder extends Seeder
{
    private const ADMIN_ROLE_ID = '08d263b7-2c3b-43f0-a49b-b80d9d4b7685';

    private const ADMIN_ACCESS_ID = 'b0763f22-c9d1-41de-b7b9-28b523a7a354';

    private const MENU_ID = '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c005';

    private const OVERVIEW_MENU_ID = '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c006';

    private const MAP_MENU_ID = '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c007';

    private const CUSTOMER_NETWORK_ID = '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c001';

    private const CUSTOMER_PARENT_ID = 'b29ab2a8-0001-4029-8bd8-cf77f0901e8c';

    private const RESELLER_MENU_ID = '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c004';

    private const APPLICATION_MENU_ID = '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c002';

    private const AGENT_MENU_ID = '8f4b8e5f-52c8-4a71-8f9a-12e7b4a4c003';

    public function run(): void
    {
        Menu::whereKey(self::CUSTOMER_NETWORK_ID)->update(['has_page' => true]);

        $overview = $this->upsertMenu(self::OVERVIEW_MENU_ID, [
            'name' => 'Network Overview',
            'code' => 'partner-network-overview',
            'text_sidebar' => 'Overview',
            'icon' => 'ti ti-layout-dashboard',
            'url_path' => 'partner-network',
            'route_name' => 'partner.reports.index',
            'slug' => 'partner-network-overview',
            'order_number' => 0,
            'has_update' => false,
        ]);

        $map = $this->upsertMenu(self::MAP_MENU_ID, [
            'name' => 'Network Map',
            'code' => 'partner-network-map',
            'text_sidebar' => 'Peta Jaringan',
            'icon' => 'ti ti-map',
            'url_path' => 'partner-network/map',
            'route_name' => 'partner.network-map.index',
            'slug' => 'partner-network-map',
            'order_number' => 1,
            'has_update' => false,
        ]);

        Menu::whereKey(self::APPLICATION_MENU_ID)->update(['order_number' => 2]);
        Menu::whereKey(self::AGENT_MENU_ID)->update(['order_number' => 3]);
        Menu::whereKey(self::RESELLER_MENU_ID)->update(['order_number' => 4]);

        $mapping = $this->upsertMenu(self::MENU_ID, [
            'name' => 'Partner Reseller Mapping',
            'code' => 'partner-reseller-mapping',
            'text_sidebar' => 'Reseller Mapping',
            'icon' => 'ti ti-link',
            'url_path' => 'partner-network/resellers/mapping',
            'route_name' => 'partner.resellers.mapping.index',
            'slug' => 'partner-reseller-mapping',
            'order_number' => 5,
            'has_update' => true,
        ]);

        // Anyone who can read Network gets Overview + Map back.
        $networkGrants = IamHasAccess::withTrashed()
            ->where('sidebar_menu_id', self::CUSTOMER_NETWORK_ID)
            ->where('is_read', true)
            ->get();

        foreach ($networkGrants as $grant) {
            $this->grant($grant->iam_access_id, $overview->id, ['is_read' => true]);
            $this->grant($grant->iam_access_id, $map->id, ['is_read' => true]);
        }

        $access = IamAccess::updateOrCreate(
            ['id' => self::ADMIN_ACCESS_ID],
            ['role_id' => self::ADMIN_ROLE_ID, 'is_notification' => false]
        );

        foreach ([
            self::CUSTOMER_PARENT_ID => ['is_read' => true],
            self::CUSTOMER_NETWORK_ID => ['is_read' => true],
            self::OVERVIEW_MENU_ID => ['is_read' => true],
            self::MAP_MENU_ID => ['is_read' => true],
            self::MENU_ID => ['is_read' => true, 'is_update' => true],
            self::RESELLER_MENU_ID => ['is_read' => true, 'is_update' => true],
        ] as $menuId => $permissions) {
            $this->grant($access->id, $menuId, $permissions);
        }

        unset($mapping);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertMenu(string $id, array $attributes): Menu
    {
        $menu = Menu::withTrashed()->updateOrCreate(
            ['id' => $id],
            [
                'parent_id' => self::CUSTOMER_NETWORK_ID,
                'has_page' => true,
                'level_sidebar' => 3,
                'is_label' => false,
                'has_create' => false,
                'has_read' => true,
                'has_delete' => false,
                'has_update' => (bool) ($attributes['has_update'] ?? false),
            ] + $attributes + $this->menuDefaults()
        );

        if ($menu->trashed()) {
            $menu->restore();
        }

        return $menu;
    }

    /**
     * @param  array<string, bool>  $permissions
     */
    private function grant(string $iamAccessId, string $menuId, array $permissions): void
    {
        $grant = IamHasAccess::withTrashed()->firstOrNew([
            'iam_access_id' => $iamAccessId,
            'sidebar_menu_id' => $menuId,
        ]);

        if (! $grant->exists) {
            $grant->fill($this->permissionDefaults());
        }

        $grant->fill($permissions);
        $grant->save();

        if ($grant->trashed()) {
            $grant->restore();
        }
    }

    /**
     * @return array<string, bool>
     */
    private function permissionDefaults(): array
    {
        return [
            'is_create' => false,
            'is_read' => false,
            'is_update' => false,
            'is_delete' => false,
            'is_custom_1' => false,
            'is_custom_2' => false,
            'is_custom_3' => false,
            'is_custom_4' => false,
            'is_custom_5' => false,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function menuDefaults(): array
    {
        return [
            'has_custom1' => false,
            'has_custom2' => false,
            'has_custom3' => false,
            'has_custom4' => false,
            'has_custom5' => false,
        ];
    }
}
