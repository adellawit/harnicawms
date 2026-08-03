<?php

namespace Database\Seeders;

use App\Models\IamAccess;
use App\Models\IamHasAccess;
use App\Models\Menu;
use Illuminate\Database\Seeder;

class MarketingCampaignAccessSeeder extends Seeder
{
    private const ADMINISTRATOR_ROLE_ID = '08d263b7-2c3b-43f0-a49b-b80d9d4b7685';

    private const MARKETING_ROLE_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000001';

    private const MARKETING_CENTER_PARENT_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000020';

    private const MENU_CAMPAIGN_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000023';

    public function run(): void
    {
        Menu::withTrashed()->where('id', self::MENU_CAMPAIGN_ID)->restore();

        Menu::updateOrCreate(['id' => self::MENU_CAMPAIGN_ID], [
            'parent_id' => self::MARKETING_CENTER_PARENT_ID,
            'name' => 'Marketing Campaign',
            'code' => 'marketing-campaign',
            'text_sidebar' => 'Marketing Campaign',
            'icon' => 'ti ti-speakerphone',
            'has_page' => true,
            'url_path' => 'marketing/campaigns',
            'route_name' => 'marketing.campaigns.index',
            'slug' => 'marketing-campaign',
            'level_sidebar' => 2,
            'order_number' => 3,
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
        ]);

        foreach ([self::ADMINISTRATOR_ROLE_ID, self::MARKETING_ROLE_ID] as $roleId) {
            $this->grant($roleId, self::MENU_CAMPAIGN_ID);
        }
    }

    private function grant(string $roleId, string $menuId): void
    {
        $iamAccess = IamAccess::firstOrCreate(
            ['role_id' => $roleId],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'is_notification' => false]
        );

        IamHasAccess::updateOrCreate(
            ['iam_access_id' => $iamAccess->id, 'sidebar_menu_id' => $menuId],
            [
                'is_create' => true,
                'is_read' => true,
                'is_update' => true,
                'is_delete' => true,
                'is_custom_1' => false,
                'is_custom_2' => false,
                'is_custom_3' => false,
                'is_custom_4' => false,
                'is_custom_5' => false,
            ]
        );
    }
}
