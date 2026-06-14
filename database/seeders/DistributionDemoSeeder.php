<?php

namespace Database\Seeders;

use App\Models\BillOfMaterial;
use App\Models\BomItem;
use App\Models\BusinessUnit;
use App\Models\Product;
use App\Models\ProductNature;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Services\StockMutationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data demo rantai Distributor -> Agen + Produksi (HPP FIFO).
 *
 * Membuat:
 *  - Satuan & bahan baku (Biji Kopi, Susu, Gula) + produk jadi (Es Kopi Susu)
 *  - Saldo awal stok bahan baku di Distributor dengan 2 layer harga (untuk demo FIFO)
 *  - Resep (BOM) Es Kopi Susu
 */
class DistributionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $distributor = BusinessUnit::where('type_code', 'COMPANY')->orderBy('created_at')->first();
        if (! $distributor) {
            $this->command?->warn('Skip DistributionDemoSeeder: COMPANY belum ada.');
            return;
        }

        $natureRaw = ProductNature::where('code', 'RAW_MATERIAL')->first();
        $natureFg = ProductNature::where('code', 'FINISHED_GOOD')->first();

        // Satuan
        $unitPcs = ProductUnit::firstOrCreate(['code' => 'PCS'], ['name' => 'Pcs', 'symbol' => 'pcs']);
        $unitGr = ProductUnit::firstOrCreate(['code' => 'GR'], ['name' => 'Gram', 'symbol' => 'gr']);
        $unitMl = ProductUnit::firstOrCreate(['code' => 'ML'], ['name' => 'Mililiter', 'symbol' => 'ml']);

        // Bahan baku: [code, name, unit, nature, layer1(qty,cost), layer2(qty,cost)]
        $rawDefs = [
            ['DEMO-RM-KOPI', 'Biji Kopi (Demo)', $unitGr, [5000, 0.15], [5000, 0.18]],
            ['DEMO-RM-SUSU', 'Susu (Demo)', $unitMl, [10000, 0.02], [10000, 0.025]],
            ['DEMO-RM-GULA', 'Gula (Demo)', $unitGr, [8000, 0.012], null],
        ];

        $rawVariants = [];
        foreach ($rawDefs as [$code, $name, $unit, $layer1, $layer2]) {
            $variant = $this->ensureProduct($code, $name, $unit, $natureRaw?->id, $distributor->id, isSale: false, isPurchase: true);
            $rawVariants[$code] = $variant;

            // Saldo awal -> layer FIFO di gudang distributor (branch = company id)
            foreach (array_filter([$layer1, $layer2]) as $i => $layer) {
                StockMutationService::inbound(
                    $variant->product_id,
                    $variant->id,
                    $distributor->id,
                    $distributor->id,
                    $unit->id,
                    (float) $layer[0],
                    (float) $layer[1],
                    'Inbound',
                    null,
                    null,
                    'Saldo awal demo (layer ' . ($i + 1) . ')',
                    now()->subDays(2 - $i)->toDateString()
                );
            }
        }

        // Produk jadi
        $fgVariant = $this->ensureProduct('DEMO-FG-ESKOSU', 'Es Kopi Susu (Demo)', $unitPcs, $natureFg?->id, $distributor->id, isSale: true, isPurchase: false);

        // BOM: 1 Es Kopi Susu = 15gr kopi + 150ml susu + 10gr gula
        $existingBom = BillOfMaterial::where('product_variant_id', $fgVariant->id)->first();
        if (! $existingBom) {
            $bom = BillOfMaterial::create([
                'company_id' => $distributor->id,
                'product_id' => $fgVariant->product_id,
                'product_variant_id' => $fgVariant->id,
                'output_unit_id' => $unitPcs->id,
                'output_quantity' => 1,
                'name' => 'Es Kopi Susu - Resep Standar (Demo)',
                'version' => 1,
                'is_active' => true,
            ]);

            $recipe = [
                ['DEMO-RM-KOPI', $unitGr, 15],
                ['DEMO-RM-SUSU', $unitMl, 150],
                ['DEMO-RM-GULA', $unitGr, 10],
            ];
            foreach ($recipe as [$code, $unit, $qty]) {
                $v = $rawVariants[$code];
                BomItem::create([
                    'bom_id' => $bom->id,
                    'component_product_id' => $v->product_id,
                    'component_variant_id' => $v->id,
                    'unit_id' => $unit->id,
                    'quantity' => $qty,
                ]);
            }
        }

        $this->command?->info('DistributionDemoSeeder: bahan baku, saldo awal (FIFO), & BOM Es Kopi Susu dibuat.');
    }

    private function ensureProduct(string $code, string $name, ProductUnit $unit, ?string $natureId, string $companyId, bool $isSale, bool $isPurchase): ProductVariant
    {
        $product = Product::where('code', $code)->first();
        if (! $product) {
            $product = Product::create([
                'company_id' => $companyId,
                'nature_id' => $natureId,
                'default_unit_id' => $unit->id,
                'name' => $name,
                'code' => $code,
                'sku' => $code,
                'is_stock_item' => true,
                'is_sale_item' => $isSale,
                'is_purchase_item' => $isPurchase,
            ]);
        }

        $variant = ProductVariant::where('product_id', $product->id)->first();
        if (! $variant) {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'is_active' => true,
            ]);
        }

        return $variant;
    }
}
