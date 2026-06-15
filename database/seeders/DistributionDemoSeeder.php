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
use Illuminate\Support\Carbon;

/**
 * Data demo rantai Distributor -> Agen + Produksi (HPP FEFO, produk herbal).
 *
 * Membuat:
 *  - 2 Gudang: WIP (bahan baku & proses) + Barang Jadi
 *  - Bahan baku herbal (Jahe Merah, Temulawak, Madu, Kunyit) dengan EXPIRY DATE
 *    -> masuk ke Gudang WIP, beberapa punya 2 layer (urutan masuk != urutan expiry) untuk demo FEFO
 *  - Produk jadi "Jamu Sehat Herbal" + resep (BOM)
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

        // === 2 Gudang ===
        $wip = $this->ensureWarehouse('WH-WIP', 'Gudang WIP (Bahan Baku & Proses)', $distributor->id);
        $fg = $this->ensureWarehouse('WH-FG', 'Gudang Barang Jadi', $distributor->id);

        $natureRaw = ProductNature::where('code', 'RAW_MATERIAL')->first();
        $natureFg = ProductNature::where('code', 'FINISHED_GOOD')->first();

        // Satuan
        $unitBtl = ProductUnit::firstOrCreate(['code' => 'BTL'], ['name' => 'Botol', 'symbol' => 'btl']);
        $unitGr = ProductUnit::firstOrCreate(['code' => 'GR'], ['name' => 'Gram', 'symbol' => 'gr']);
        $unitMl = ProductUnit::firstOrCreate(['code' => 'ML'], ['name' => 'Mililiter', 'symbol' => 'ml']);

        // Bahan baku herbal: [code, name, unit, layers[ [qty, cost, expiry, daysAgoMasuk] ... ] ]
        // Catatan FEFO: Jahe Merah sengaja layer pertama (masuk lbh dulu) expiry-nya LEBIH LAMA,
        // layer kedua (masuk belakangan) expiry-nya LEBIH DEKAT -> FEFO konsumsi layer kedua dulu.
        $rawDefs = [
            ['DEMO-RM-JAHE', 'Jahe Merah (Demo)', $unitGr, [
                [3000, 0.10, '2027-06-01', 3],
                [3000, 0.12, '2026-09-01', 1],
            ]],
            ['DEMO-RM-TEMU', 'Temulawak (Demo)', $unitGr, [
                [4000, 0.08, '2026-12-01', 2],
            ]],
            ['DEMO-RM-MADU', 'Madu (Demo)', $unitMl, [
                [5000, 0.05, '2027-01-01', 2],
            ]],
            ['DEMO-RM-KUNYIT', 'Kunyit (Demo)', $unitGr, [
                [3000, 0.07, '2026-11-01', 2],
            ]],
        ];

        $rawVariants = [];
        foreach ($rawDefs as [$code, $name, $unit, $layers]) {
            $variant = $this->ensureProduct($code, $name, $unit, $natureRaw?->id, $distributor->id, isSale: false, isPurchase: true);
            $rawVariants[$code] = $variant;

            foreach ($layers as [$qty, $cost, $expiry, $daysAgo]) {
                StockMutationService::inbound(
                    $variant->product_id,
                    $variant->id,
                    $distributor->id,
                    $wip->id,                       // bahan baku -> Gudang WIP
                    $unit->id,
                    (float) $qty,
                    (float) $cost,
                    'Inbound',
                    null,
                    null,
                    'Saldo awal demo (exp ' . $expiry . ')',
                    Carbon::now()->subDays($daysAgo)->toDateString(),
                    $expiry                          // expiry date -> FEFO
                );
            }
        }

        // === Produk jadi ===
        $fgVariant = $this->ensureProduct('DEMO-FG-JAMU', 'Jamu Sehat Herbal (Demo)', $unitBtl, $natureFg?->id, $distributor->id, isSale: true, isPurchase: false);

        // === BOM: 1 botol Jamu = 20gr jahe + 15gr temulawak + 30ml madu + 10gr kunyit ===
        if (! BillOfMaterial::where('product_variant_id', $fgVariant->id)->exists()) {
            $bom = BillOfMaterial::create([
                'company_id' => $distributor->id,
                'product_id' => $fgVariant->product_id,
                'product_variant_id' => $fgVariant->id,
                'output_unit_id' => $unitBtl->id,
                'output_quantity' => 1,
                'name' => 'Jamu Sehat Herbal - Resep Standar (Demo)',
                'version' => 1,
                'is_active' => true,
            ]);

            $recipe = [
                ['DEMO-RM-JAHE', $unitGr, 20],
                ['DEMO-RM-TEMU', $unitGr, 15],
                ['DEMO-RM-MADU', $unitMl, 30],
                ['DEMO-RM-KUNYIT', $unitGr, 10],
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

        $this->command?->info('DistributionDemoSeeder: 2 gudang, bahan baku herbal + expiry (FEFO), & BOM Jamu Sehat dibuat.');
    }

    private function ensureWarehouse(string $code, string $name, string $parentId): BusinessUnit
    {
        return BusinessUnit::firstOrCreate(
            ['code' => $code],
            [
                'parent_id' => $parentId,
                'type_code' => 'WAREHOUSE',
                'name' => $name,
                'is_active' => true,
                'is_inventory_active' => true,
            ]
        );
    }

    private function ensureProduct(string $code, string $name, ProductUnit $unit, ?string $natureId, string $companyId, bool $isSale, bool $isPurchase): ProductVariant
    {
        $product = Product::withTrashed()->where('code', $code)->first();
        if (! $product) {
            $product = Product::create([
                'company_id' => $companyId,
                'branch_id' => $companyId, // set branch_id = distributor agar terlihat di product/items
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
