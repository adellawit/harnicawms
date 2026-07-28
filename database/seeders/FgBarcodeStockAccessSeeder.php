<?php

namespace Database\Seeders;

use App\Models\IamAccess;
use App\Models\IamHasAccess;
use App\Models\Menu;
use Illuminate\Database\Seeder;

class FgBarcodeStockAccessSeeder extends Seeder
{
    private const ADMIN_ROLE_ID = '08d263b7-2c3b-43f0-a49b-b80d9d4b7685';

    private const ADMIN_ACCESS_ID = 'b0763f22-c9d1-41de-b7b9-28b523a7a354';

    private const REPORT_MENU_ID = 'e2345678-00a0-cdef-0123-456789abcdef';

    private const INVENTORY_PARENT_ID = 'e2345678-0021-cdef-0123-456789abcdef';

    private const REPORTING_PARENT_ID = 'e2345678-9abc-cdef-0123-456789abcdef';

    public function run(): void
    {
        $reportMenu = Menu::withTrashed()->updateOrCreate(
            ['id' => self::REPORT_MENU_ID],
            [
                'parent_id' => self::INVENTORY_PARENT_ID,
                'name' => 'FG Barcode & Stock',
                'code' => 'reporting_fg_barcode_stock',
                'text_sidebar' => 'FG Barcode & Stock',
                'icon' => 'ti ti-barcode',
                'has_page' => false,
                'url_path' => 'reporting/fg-barcode-stock',
                'route_name' => 'reporting.fg-barcode-stock.index',
                'slug' => 'reporting-fg-barcode-stock',
                'level_sidebar' => 3,
                'order_number' => 10,
                'is_label' => false,
                'has_create' => false,
                'has_update' => false,
                'has_read' => true,
                'has_delete' => false,
            ] + $this->menuDefaults()
        );

        if ($reportMenu->trashed()) {
            $reportMenu->restore();
        }

        $access = IamAccess::updateOrCreate(
            ['id' => self::ADMIN_ACCESS_ID],
            ['role_id' => self::ADMIN_ROLE_ID, 'is_notification' => false]
        );

        foreach ([
            self::REPORTING_PARENT_ID => ['is_read' => true],
            self::INVENTORY_PARENT_ID => ['is_read' => true],
            self::REPORT_MENU_ID => ['is_read' => true],
        ] as $menuId => $permissions) {
            $grant = IamHasAccess::withTrashed()->firstOrNew([
                'iam_access_id' => $access->id,
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
