<?php

namespace Database\Seeders;

use App\Models\ProductPriceList;
use App\Models\ProductVariantPrice;
use Illuminate\Database\Seeder;

class ProductPriceListVariantSeeder extends Seeder
{
    /**
     * Create ProductVariantPrice for every active price list.
     * Uses base prices (price_list_id = null) and replicates to each list.
     */
    public function run(): void
    {
        $priceLists = ProductPriceList::where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();

        if ($priceLists->isEmpty()) {
            $this->command?->warn('No price lists found. Run ProductPriceListSeeder first.');
            return;
        }

        // Base prices: variant prices without price_list_id
        $basePrices = ProductVariantPrice::whereNull('deleted_at')
            ->whereNull('price_list_id')
            ->where('selling_price', '>', 0)
            ->get();

        if ($basePrices->isEmpty()) {
            $this->command?->warn('No base variant prices found. Seed products and variant prices first.');
            return;
        }

        $upserted = 0;
        $priceListMultipliers = [
            'RTL-REGULAR' => 1.00,
            'RTL-MEMBER' => 0.95,
            'RTL-WHOLESALE' => 0.90,
            'FNB-DINEIN' => 1.00,
            'FNB-TAKEAWAY' => 1.00,
            'FNB-GOFOOD' => 1.12,
            'FNB-GRABFOOD' => 1.12,
        ];

        foreach ($priceLists as $priceList) {
            foreach ($basePrices as $base) {
                $multiplier = $priceListMultipliers[$priceList->code] ?? 1.00;
                $sellingPrice = round($base->selling_price * $multiplier, 4);

                // Keep FNB list prices above 10k.
                if (str_starts_with((string) $priceList->code, 'FNB-')) {
                    $sellingPrice = max($sellingPrice, 10001);
                }

                ProductVariantPrice::withTrashed()->updateOrCreate(
                    [
                        'variant_id' => $base->variant_id,
                        'branch_id' => $base->branch_id,
                        'unit_id' => $base->unit_id,
                        'price_list_id' => $priceList->id,
                    ],
                    [
                        'company_id' => $base->company_id,
                        'purchase_price' => $base->purchase_price,
                        'selling_price' => $sellingPrice,
                        'deleted_at' => null,
                    ]
                );

                $upserted++;
            }
        }

        $this->command?->info("Product variant prices per price list: {$upserted} rows upserted (full coverage).");
    }
}
