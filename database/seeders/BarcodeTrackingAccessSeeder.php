<?php

namespace Database\Seeders;

use App\Models\IamAccess;
use App\Models\IamHasAccess;
use App\Models\Menu;
use Illuminate\Database\Seeder;

class BarcodeTrackingAccessSeeder extends Seeder
{
    private const ADMIN_ROLE_ID = '08d263b7-2c3b-43f0-a49b-b80d9d4b7685';

    private const ADMIN_ACCESS_ID = 'b0763f22-c9d1-41de-b7b9-28b523a7a354';

    private const LEGACY_POS_DISPATCH_MENU_ID = 'a0b1c2d3-0004-4a5b-8c9d-0e1f2a3b4c5e';

    private const REPORT_MENU_ID = 'e2345678-0041-cdef-0123-456789abcdef';

    public function run(): void
    {
        $legacyMenu = Menu::withTrashed()->find(self::LEGACY_POS_DISPATCH_MENU_ID);
        if ($legacyMenu && ! $legacyMenu->trashed()) {
            $legacyMenu->delete();
        }

        IamHasAccess::withTrashed()
            ->where('sidebar_menu_id', self::LEGACY_POS_DISPATCH_MENU_ID)
            ->get()
            ->each(function (IamHasAccess $grant): void {
                if (! $grant->trashed()) {
                    $grant->delete();
                }
            });

        $reportMenu = Menu::withTrashed()->updateOrCreate(
            ['id' => self::REPORT_MENU_ID],
            [
                'parent_id' => 'e2345678-0031-cdef-0123-456789abcdef',
                'name' => 'Barcode Dispatch',
                'code' => 'reporting_barcode_dispatch',
                'text_sidebar' => 'Barcode Dispatch',
                'icon' => 'ti ti-barcode',
                'has_page' => false,
                'url_path' => 'reporting/barcode-dispatch',
                'route_name' => 'reporting.barcode-dispatch.index',
                'slug' => 'reporting-barcode-dispatch',
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
            'e2345678-9abc-cdef-0123-456789abcdef' => ['is_read' => true],
            'e2345678-0031-cdef-0123-456789abcdef' => ['is_read' => true],
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
