<?php

namespace Database\Seeders;

use App\Models\Partner\ForediPriceTier;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ForediPriceTierSeeder extends Seeder
{
    /**
     * Seed Foredi partner price tiers (terpisah dari product price lists).
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

        $tiers = [
            [
                'level' => ForediPriceTier::LEVEL_RESMI,
                'min_qty' => null,
                'price' => 249000,
                'sort_order' => 10,
            ],
            [
                'level' => ForediPriceTier::LEVEL_MAP,
                'min_qty' => null,
                'price' => 229000,
                'sort_order' => 20,
            ],
            [
                'level' => ForediPriceTier::LEVEL_RESELLER,
                'min_qty' => 30,
                'price' => 180000,
                'sort_order' => 30,
            ],
            [
                'level' => ForediPriceTier::LEVEL_RESELLER,
                'min_qty' => 60,
                'price' => 175000,
                'sort_order' => 40,
            ],
            [
                'level' => ForediPriceTier::LEVEL_RESELLER,
                'min_qty' => 120,
                'price' => 170000,
                'sort_order' => 50,
            ],
            [
                'level' => ForediPriceTier::LEVEL_AGEN,
                'min_qty' => 600,
                'price' => 160000,
                'sort_order' => 60,
            ],
        ];

        foreach ($tiers as $tier) {
            $this->upsertTier(
                categoryId: $category->id,
                productId: $product->id,
                level: $tier['level'],
                minQty: $tier['min_qty'],
                price: $tier['price'],
                sortOrder: $tier['sort_order'],
            );
        }

        $this->command?->info('Foredi price tiers seeded: 6 rows (RESMI/MAP/RESELLER×3/AGEN).');
    }

    private function upsertTier(
        string $categoryId,
        string $productId,
        string $level,
        int|float|null $minQty,
        int|float $price,
        int $sortOrder,
    ): void {
        $query = ForediPriceTier::withTrashed()
            ->where('product_id', $productId)
            ->where('level', $level);

        if ($minQty === null) {
            $query->whereNull('min_qty');
        } else {
            $query->where('min_qty', $minQty);
        }

        $row = $query->first();

        $payload = [
            'category_id' => $categoryId,
            'product_id' => $productId,
            'level' => $level,
            'min_qty' => $minQty,
            'unit_code' => 'BOX',
            'price' => $price,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'deleted_at' => null,
            'deleted_by' => null,
        ];

        if ($row) {
            $row->fill($payload)->save();

            return;
        }

        ForediPriceTier::query()->create($payload);
    }
}
