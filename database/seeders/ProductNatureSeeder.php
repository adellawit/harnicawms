<?php

namespace Database\Seeders;

use App\Models\ProductNature;
use Illuminate\Database\Seeder;

class ProductNatureSeeder extends Seeder
{
    /**
     * Default product natures for Unified Product Master.
     * RAW_MATERIAL, SEMI_FINISHED, FINISHED_GOOD, SERVICE, NON_STOCK.
     */
    public function run(): void
    {
        $defaults = [
            [
                'code' => 'RAW_MATERIAL',
                'name' => 'Bahan Baku',
                'description' => 'Bahan baku / raw material',
            ],
            [
                'code' => 'SEMI_FINISHED',
                'name' => 'Semi Jadi',
                'description' => 'Produk setengah jadi / semi-finished',
            ],
            [
                'code' => 'FINISHED_GOOD',
                'name' => 'Produk Jadi',
                'description' => 'Produk jadi / finished goods',
            ],
            [
                'code' => 'SERVICE',
                'name' => 'Jasa',
                'description' => 'Layanan / service',
            ],
            [
                'code' => 'NON_STOCK',
                'name' => 'Non Stock',
                'description' => 'Item non-stock',
            ],
        ];

        foreach ($defaults as $item) {
            ProductNature::firstOrCreate(
                ['code' => $item['code']],
                array_merge($item, [
                    'company_id' => null,
                    'branch_id' => null,
                    'parent_id' => null,
                    'created_by' => null,
                    'updated_by' => null,
                ])
            );
        }

        $this->command?->info('Product types seeded successfully!');
    }
}
