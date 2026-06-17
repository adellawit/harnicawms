<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentGatewayConfigurationMenuSeeder extends Seeder
{
    public const MENU_ID = 'd9012345-ef01-4234-5678-9abcdef01235';

    public function run(): void
    {
        $settings = DB::table('master_data.menus')
            ->where('code', 'settings')
            ->whereNull('deleted_at')
            ->first();

        if ($settings === null) {
            $this->command?->warn('Settings menu not found — skipping Payment Gateway Configuration menu seed.');

            return;
        }

        $exists = DB::table('master_data.menus')
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->where('id', self::MENU_ID)
                    ->orWhere('name', 'Payment Gateway Configuration');
            })
            ->exists();

        if (! $exists) {
            DB::table('master_data.menus')->insert([
                'id' => self::MENU_ID,
                'parent_id' => $settings->id,
                'name' => 'Payment Gateway Configuration',
                'code' => 'payment_gateway_configuration',
                'text_sidebar' => 'Payment Gateway',
                'icon' => 'ti ti-credit-card-pay',
                'has_page' => false,
                'url_path' => 'settings/payment-gateway-configuration',
                'route_name' => 'settings.payment-gateway-configuration.index.view',
                'slug' => 'payment-gateway-configuration',
                'level_sidebar' => 2,
                'order_number' => 8,
                'is_label' => false,
                'has_create' => false,
                'has_update' => true,
                'has_read' => true,
                'has_delete' => false,
                'has_custom1' => false,
                'has_custom2' => false,
                'has_custom3' => false,
                'has_custom4' => false,
                'has_custom5' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command?->info('Payment Gateway Configuration menu created under Settings.');
        } else {
            DB::table('master_data.menus')
                ->where('name', 'Payment Gateway Configuration')
                ->whereNull('deleted_at')
                ->update([
                    'parent_id' => $settings->id,
                    'url_path' => 'settings/payment-gateway-configuration',
                    'route_name' => 'settings.payment-gateway-configuration.index.view',
                    'icon' => 'ti ti-credit-card-pay',
                    'text_sidebar' => 'Payment Gateway',
                    'order_number' => 8,
                    'has_update' => true,
                    'has_read' => true,
                    'updated_at' => now(),
                ]);

            $this->command?->info('Payment Gateway Configuration menu updated.');
        }

        $menuId = DB::table('master_data.menus')
            ->where('name', 'Payment Gateway Configuration')
            ->whereNull('deleted_at')
            ->value('id');

        if ($menuId === null) {
            return;
        }

        $iamAccessRows = DB::table('auth.iam_accesses')
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($iamAccessRows as $iamAccessId) {
            $accessExists = DB::table('auth.iam_has_accesses')
                ->where('iam_access_id', $iamAccessId)
                ->where('sidebar_menu_id', $menuId)
                ->exists();

            if ($accessExists) {
                continue;
            }

            DB::table('auth.iam_has_accesses')->insert([
                'id' => (string) Str::uuid(),
                'iam_access_id' => $iamAccessId,
                'sidebar_menu_id' => $menuId,
                'is_create' => false,
                'is_read' => true,
                'is_update' => true,
                'is_delete' => false,
                'is_custom_1' => false,
                'is_custom_2' => false,
                'is_custom_3' => false,
                'is_custom_4' => false,
                'is_custom_5' => false,
            ]);
        }

        $this->command?->info('Payment Gateway Configuration access granted to all IAM profiles.');
    }
}
