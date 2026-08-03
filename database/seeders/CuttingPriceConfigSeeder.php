<?php

namespace Database\Seeders;

use App\Models\Partner\CuttingPriceConfig;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class CuttingPriceConfigSeeder extends Seeder
{
    /**
     * Seed cutting price config (official + MAP) for FOREDI-FG.
     * Report floor nanti: map_price.
     */
    public function run(): void
    {
        $product = Product::query()
            ->where('code', 'FOREDI-FG')
            ->whereNull('deleted_at')
            ->first();

        if (! $product) {
            $this->command?->error(
                'Produk FOREDI-FG tidak ditemukan. Jalankan ForediProductSeeder terlebih dahulu.'
            );

            return;
        }

        $category = ProductCategory::withTrashed()
            ->where('code', 'FOREDI')
            ->first();

        if ($category) {
            if ($category->trashed()) {
                $category->restore();
            }
            $category->fill([
                'name' => 'FOREDI',
                'company_id' => $category->company_id ?: $product->company_id,
                'sort_order' => $category->sort_order ?: 10,
            ])->save();
        } else {
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
        }

        if ($product->category_id !== $category->id) {
            $product->category_id = $category->id;
            $product->save();
        }

        $row = CuttingPriceConfig::withTrashed()
            ->where('product_id', $product->id)
            ->where('unit_code', 'BOX')
            ->first();

        $payload = [
            'category_id' => $category->id,
            'product_id' => $product->id,
            'unit_code' => 'BOX',
            'official_price' => 249000,
            'map_price' => 229000,
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
            'Cutting price config seeded: FOREDI-FG BOX official=249000 map=229000.'
        );
    }
}
