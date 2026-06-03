<?php

namespace Database\Seeders;

use App\Models\Parameter;
use App\Models\ParameterDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductParameterSeeder extends Seeder
{
    /**
     * Seed parameters for Unified Product Master (ITEM_TYPE, PRODUCT_NATURE, PROCUREMENT_TYPE)
     */
    public function run(): void
    {
        // === ITEM_TYPE ===
        $paramItemType = Parameter::firstOrCreate(
            ['code' => 'ITEM_TYPE'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Item Type',
                'code' => 'ITEM_TYPE',
                'description' => 'Tipe item: Finished Good, Bahan Baku, Semi-Finished, Service, Bundle',
            ]
        );

        $itemTypes = [
            ['key' => 'finished_good', 'value' => 'Finished Good', 'description' => 'Produk jadi'],
            ['key' => 'raw_material', 'value' => 'Bahan Baku', 'description' => 'Bahan baku'],
            ['key' => 'semi_finished', 'value' => 'Semi Finished', 'description' => 'Produk setengah jadi'],
            ['key' => 'service', 'value' => 'Service', 'description' => 'Jasa'],
            ['key' => 'bundle', 'value' => 'Bundle', 'description' => 'Paket/kombinasi produk'],
        ];

        foreach ($itemTypes as $detail) {
            ParameterDetail::firstOrCreate(
                ['parameter_id' => $paramItemType->id, 'key' => $detail['key']],
                array_merge($detail, [
                    'id' => Str::uuid()->toString(),
                    'parameter_id' => $paramItemType->id,
                ])
            );
        }

        // === PRODUCT_NATURE ===
        $paramProductNature = Parameter::firstOrCreate(
            ['code' => 'PRODUCT_NATURE'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Product Type',
                'code' => 'PRODUCT_NATURE',
                'description' => 'Sifat produk: Inventory (track stock) atau Non-Inventory',
            ]
        );

        $productNatures = [
            ['key' => 'inventory', 'value' => 'Inventory Item', 'description' => 'Track stock, COGS'],
            ['key' => 'non_inventory', 'value' => 'Non-Inventory', 'description' => 'Tidak track stock'],
        ];

        foreach ($productNatures as $detail) {
            ParameterDetail::firstOrCreate(
                ['parameter_id' => $paramProductNature->id, 'key' => $detail['key']],
                array_merge($detail, [
                    'id' => Str::uuid()->toString(),
                    'parameter_id' => $paramProductNature->id,
                ])
            );
        }

        // === PROCUREMENT_TYPE ===
        $paramProcurement = Parameter::firstOrCreate(
            ['code' => 'PROCUREMENT_TYPE'],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Procurement Type',
                'code' => 'PROCUREMENT_TYPE',
                'description' => 'Cara memperoleh: Purchase, Produce, Both, None',
            ]
        );

        $procurementTypes = [
            ['key' => 'purchase', 'value' => 'Purchase', 'description' => 'Dibeli dari supplier'],
            ['key' => 'produce', 'value' => 'Produce', 'description' => 'Diproduksi (BOM)'],
            ['key' => 'both', 'value' => 'Both', 'description' => 'Bisa beli atau produksi'],
            ['key' => 'none', 'value' => 'None', 'description' => 'Service/bundle - tidak procure'],
        ];

        foreach ($procurementTypes as $detail) {
            ParameterDetail::firstOrCreate(
                ['parameter_id' => $paramProcurement->id, 'key' => $detail['key']],
                array_merge($detail, [
                    'id' => Str::uuid()->toString(),
                    'parameter_id' => $paramProcurement->id,
                ])
            );
        }

        $this->command?->info('Product parameters (ITEM_TYPE, PRODUCT_NATURE, PROCUREMENT_TYPE) seeded successfully!');
    }
}
