<?php

namespace Database\Seeders;

use App\Models\Partner\CuttingPriceConfig;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class CuttingPriceConfigSeeder extends Seeder
{
    /**
     * Seed cutting price config per kategori (FOREDI), bukan per produk.
     * Report floor: map_price.
     */
    public function run(): void
    {
        $category = ProductCategory::withTrashed()
            ->where('code', 'FOREDI')
            ->first();

        if (! $category) {
            $product = Product::query()
                ->where('code', 'FOREDI-FG')
                ->whereNull('deleted_at')
                ->first();

            if (! $product) {
                $this->command?->error(
                    'Kategori FOREDI / produk FOREDI-FG tidak ditemukan. Jalankan ForediProductSeeder terlebih dahulu.'
                );

                return;
            }

            $category = ProductCategory::query()->create([
                'company_id' => $product->company_id,
                'branch_id' => null,
                'parent_id' => null,
                'name' => 'FOREDI',
                'code' => 'FOREDI',
                'slug' => 'foredi',
                'description' => 'Kategori produk Foredi (barang jadi & distribusi partner)',
                'sort_order' => 10,
            ]);
            $this->command?->info('Kategori FOREDI dibuat otomatis.');

            if ($product->category_id !== $category->id) {
                $product->category_id = $category->id;
                $product->save();
            }
        } elseif ($category->trashed()) {
            $category->restore();
        }

        $row = CuttingPriceConfig::withTrashed()
            ->where('category_id', $category->id)
            ->where('unit_code', 'BOX')
            ->first();

        $payload = [
            'category_id' => $category->id,
            'product_id' => null,
            'unit_code' => 'BOX',
            'official_price' => 249000,
            'map_price' => 229000,
            'reseller_price_30' => 180000,
            'reseller_price_60' => 175000,
            'reseller_price_120' => 170000,
            'agent_price_600' => 160000,
            'sort_order' => 10,
            'is_active' => true,
            'deleted_at' => null,
            'deleted_by' => null,
        ];

        if ($row) {
            $row->fill($payload)->save();
        } else {
            CuttingPriceConfig::query()->create($payload);
        }

        $this->command?->info(
            'Cutting price config seeded: kategori FOREDI / BOX resmi=249k MAP=229k reseller 30/60/120 agen 600.'
        );
    }
}
