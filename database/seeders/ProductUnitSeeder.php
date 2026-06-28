<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\ProductUnit;
use Illuminate\Database\Seeder;

class ProductUnitSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = BusinessUnit::query()
            ->where('type_code', 'COMPANY')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->value('id');

        foreach ($this->units() as $unit) {
            ProductUnit::updateOrCreate(
                ['code' => $unit['code']],
                [
                    'company_id' => $companyId,
                    'branch_id' => null,
                    'name' => $unit['name'],
                    'symbol' => $unit['symbol'],
                    'description' => $unit['description'] ?? null,
                ]
            );
        }

        $this->command?->info('Product units seeded successfully.');
    }

    private function units(): array
    {
        return [
            ['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs'],
            ['code' => 'BOX', 'name' => 'Box', 'symbol' => 'box'],
            ['code' => 'DUS', 'name' => 'Dus', 'symbol' => 'dus'],
            ['code' => 'BTL', 'name' => 'Botol', 'symbol' => 'btl'],
            ['code' => 'PACK', 'name' => 'Pack', 'symbol' => 'pack'],
            ['code' => 'KG', 'name' => 'Kilogram', 'symbol' => 'kg'],
            ['code' => 'GR', 'name' => 'Gram', 'symbol' => 'gr'],
            ['code' => 'LTR', 'name' => 'Liter', 'symbol' => 'ltr'],
            ['code' => 'ML', 'name' => 'Mililiter', 'symbol' => 'ml'],
            ['code' => 'MTR', 'name' => 'Meter', 'symbol' => 'm'],
        ];
    }
}
