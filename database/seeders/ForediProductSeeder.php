<?php

namespace Database\Seeders;

use App\Models\BillOfMaterial;
use App\Models\BomItem;
use App\Models\BusinessUnit;
use App\Models\Parameter;
use App\Models\ParameterDetail;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductBatchStock;
use App\Models\ProductCostLayer;
use App\Models\ProductNature;
use App\Models\ProductPriceList;
use App\Models\ProductStockMovement;
use App\Models\ProductUnit;
use App\Models\ProductUnitConversion;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantStock;
use App\Models\StockMutationType;
use App\Models\Warehouse;
use App\Services\Product\ProductStockBootstrapService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Foredi RM → FG packing conversion.
 *
 * Raw material (beli, 1 box = 3 sachet):
 *   1 Karton = 30 Pack = 300 Box = 900 Sachet
 *   (1 Pack = 10 Box, 1 Box = 3 Sachet)
 *
 * Finished good (produksi, 1 box = 4 sachet):
 *   1 Karton = 30 Pack = 300 Box = 1200 Sachet
 *   (1 Pack = 10 Box, 1 Box = 4 Sachet)
 *
 * BOM (konversi isi per sachet):
 *   1 Box FG (4 sachet) ← 4 Sachet RM
 *   ⇒ 1 Karton FG (1200 sachet) ← 1200 Sachet RM = 4/3 Karton RM
 *
 * Warehouse:
 *   RM → SUHARA-BDG-WH-RM (RAW_MATERIAL) — Foredi RM + Label, Plastik, Dus
 *   FG → SUHARA-BDG-WH-PRD (FG), is_sale_item = true
 *
 * Packaging RM (Label / Plastik / Dus): satuan Pcs saja (1 Pcs).
 *
 * Depends on: BusinessUnitSeeder, ProductUnitSeeder (+SACHET, +PCS), ProductNatureSeeder,
 *             ProductParameterSeeder, ProductPriceListSeeder, WarehouseSeeder
 */
class ForediProductSeeder extends Seeder
{
    private const COMPANY_CODE = 'SUHARA-001';

    private const BRANCH_CODE = 'SUHARA-BDG-001';

    private const WH_RM_CODE = 'SUHARA-BDG-WH-RM';

    private const WH_FG_CODE = 'SUHARA-BDG-WH-PRD';

    private const RM_CODE = 'FOREDI-RM';

    private const FG_CODE = 'FOREDI-FG';

    /**
     * Packaging raw materials — satuan Pcs (tanpa konversi).
     *
     * @var list<array{code: string, name: string, sku: string, purchase_per_pcs: float}>
     */
    private const PACKAGING_RMS = [
        [
            'code' => 'FOREDI-LABEL',
            'name' => 'Label (Bahan Baku)',
            'sku' => 'FRD-LABEL-STD',
            'purchase_per_pcs' => 500.0,
        ],
        [
            'code' => 'FOREDI-PLASTIK',
            'name' => 'Plastik (Bahan Baku)',
            'sku' => 'FRD-PLASTIK-STD',
            'purchase_per_pcs' => 750.0,
        ],
        [
            'code' => 'FOREDI-DUS',
            'name' => 'Dus (Bahan Baku)',
            'sku' => 'FRD-DUS-STD',
            'purchase_per_pcs' => 1500.0,
        ],
    ];

    /** Initial RM stock: 10 Karton → 9000 Sachet */
    private const RM_INITIAL_KARTON = 10;

    /** Sample purchase price per Karton RM (IDR) */
    private const RM_PURCHASE_PER_KARTON = 450000.0;

    /** Sample selling price per Karton FG (IDR) */
    private const FG_SELLING_PER_KARTON = 900000.0;

    /** Notes marker for idempotent seed stock movements */
    private const SEED_MOVEMENT_NOTES = 'Seed - Saldo Awal';

    public function run(ProductStockBootstrapService $stockBootstrap): void
    {
        $company = BusinessUnit::query()
            ->where('code', self::COMPANY_CODE)
            ->where('type_code', 'COMPANY')
            ->whereNull('deleted_at')
            ->first();

        $branch = BusinessUnit::query()
            ->where('code', self::BRANCH_CODE)
            ->whereNull('deleted_at')
            ->first();

        if (! $company || ! $branch) {
            $this->command?->error('Company/branch Suhara tidak ditemukan. Jalankan BusinessUnitSeeder dulu.');

            return;
        }

        $units = ProductUnit::query()
            ->whereIn('code', ['KARTON', 'PACK', 'BOX', 'SACHET', 'PCS'])
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('code');

        foreach (['KARTON', 'PACK', 'BOX', 'SACHET', 'PCS'] as $code) {
            if (! isset($units[$code])) {
                $this->command?->error("Satuan {$code} belum ada. Jalankan ProductUnitSeeder dulu.");

                return;
            }
        }

        $natureRm = ProductNature::query()->where('code', 'RAW_MATERIAL')->first();
        $natureFg = ProductNature::query()->where('code', 'FINISHED_GOOD')->first();
        if (! $natureRm || ! $natureFg) {
            $this->command?->error('ProductNature belum lengkap. Jalankan ProductNatureSeeder dulu.');

            return;
        }

        $itemTypeRm = $this->parameterDetailId('ITEM_TYPE', 'raw_material');
        $itemTypeFg = $this->parameterDetailId('ITEM_TYPE', 'finished_good');
        $productNatureInventory = $this->parameterDetailId('PRODUCT_NATURE', 'inventory');
        $procurementPurchase = $this->parameterDetailId('PROCUREMENT_TYPE', 'purchase');
        $procurementProduce = $this->parameterDetailId('PROCUREMENT_TYPE', 'produce');

        if (! $itemTypeRm || ! $itemTypeFg || ! $productNatureInventory) {
            $this->command?->error('Parameter ITEM_TYPE / PRODUCT_NATURE belum lengkap.');

            return;
        }

        $whRm = Warehouse::query()->where('code', self::WH_RM_CODE)->whereNull('deleted_at')->first();
        $whFg = Warehouse::query()->where('code', self::WH_FG_CODE)->whereNull('deleted_at')->first();
        if (! $whRm || ! $whFg) {
            $this->command?->error('Gudang RM/FG Suhara Bandung tidak ditemukan. Jalankan WarehouseSeeder dulu.');

            return;
        }

        $priceList = ProductPriceList::query()->where('code', 'REGULER')->whereNull('deleted_at')->first();

        DB::connection('pgsql')->transaction(function () use (
            $company,
            $branch,
            $units,
            $natureRm,
            $natureFg,
            $itemTypeRm,
            $itemTypeFg,
            $productNatureInventory,
            $procurementPurchase,
            $procurementProduce,
            $whRm,
            $whFg,
            $priceList,
            $stockBootstrap,
        ) {
            // --- Raw Material: 1 krt = 30 pack = 300 box = 900 sachet ---
            $rm = $this->upsertProduct([
                'code' => self::RM_CODE,
                'name' => 'Foredi (Bahan Baku)',
                'description' => 'Foredi raw material. Packing beli: 1 Pack=10 Box, 1 Box=3 Sachet ⇒ 1 Karton=30 Pack=300 Box=900 Sachet.',
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'nature_id' => $natureRm->id,
                'item_type_id' => $itemTypeRm,
                'product_nature_id' => $productNatureInventory,
                'procurement_type_id' => $procurementPurchase,
                'default_unit_id' => $units['KARTON']->id,
                'is_stock_item' => true,
                'is_sale_item' => false,
                'is_purchase_item' => true,
            ]);

            $this->syncConversionChain($rm, [
                ['from' => 'KARTON', 'to' => 'PACK', 'factor' => 30, 'level' => 1],
                ['from' => 'PACK', 'to' => 'BOX', 'factor' => 10, 'level' => 2],
                ['from' => 'BOX', 'to' => 'SACHET', 'factor' => 3, 'level' => 3],
            ], $units);

            $rmVariant = $this->ensureDefaultVariant($rm, 'FRD-RM-STD');
            $stockBootstrap->bootstrap($rm);
            $this->ensureStockInWarehouse(
                product: $rm,
                variant: $rmVariant,
                warehouse: $whRm,
                unitId: $units['SACHET']->id,
                quantity: self::RM_INITIAL_KARTON * 30 * 10 * 3, // 10 Karton → 9000 Sachet
                unitCost: self::RM_PURCHASE_PER_KARTON / (30 * 10 * 3),
                seedBatchUnitId: $units['KARTON']->id,
                seedBatchQuantity: (float) self::RM_INITIAL_KARTON,
            );

            if ($priceList) {
                $this->syncPrices(
                    variant: $rmVariant,
                    companyId: $company->id,
                    branchId: $branch->id,
                    priceListId: $priceList->id,
                    defaultUnitId: $units['KARTON']->id,
                    smallestUnitId: $units['SACHET']->id,
                    factorToSmallest: 900.0,
                    purchasePerDefault: self::RM_PURCHASE_PER_KARTON,
                    sellingPerDefault: null,
                );
            }

            // --- Packaging raw materials (Label, Plastik, Dus) — satuan Pcs saja ---
            foreach (self::PACKAGING_RMS as $pkg) {
                $pkgProduct = $this->upsertProduct([
                    'code' => $pkg['code'],
                    'name' => $pkg['name'],
                    'description' => $pkg['name'].'. Satuan: Pcs (1 Pcs).',
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'nature_id' => $natureRm->id,
                    'item_type_id' => $itemTypeRm,
                    'product_nature_id' => $productNatureInventory,
                    'procurement_type_id' => $procurementPurchase,
                    'default_unit_id' => $units['PCS']->id,
                    'is_stock_item' => true,
                    'is_sale_item' => false,
                    'is_purchase_item' => true,
                ]);

                // Hapus konversi lama (jika sempat di-seed dengan rantai karton).
                $this->syncConversionChain($pkgProduct, [], $units);

                $pkgVariant = $this->ensureDefaultVariant($pkgProduct, $pkg['sku']);
                $stockBootstrap->bootstrap($pkgProduct);
                $this->ensureStockInWarehouse(
                    product: $pkgProduct,
                    variant: $pkgVariant,
                    warehouse: $whRm,
                    unitId: $units['PCS']->id,
                    quantity: 0,
                    unitCost: null,
                );

                if ($priceList) {
                    $this->syncPrices(
                        variant: $pkgVariant,
                        companyId: $company->id,
                        branchId: $branch->id,
                        priceListId: $priceList->id,
                        defaultUnitId: $units['PCS']->id,
                        smallestUnitId: $units['PCS']->id,
                        factorToSmallest: 1.0,
                        purchasePerDefault: $pkg['purchase_per_pcs'],
                        sellingPerDefault: null,
                    );
                }
            }

            // --- Finished Good: 1 krt = 30 pack = 300 box = 1200 sachet ---
            $fg = $this->upsertProduct([
                'code' => self::FG_CODE,
                'name' => 'Foredi (Barang Jadi)',
                'description' => 'Foredi finished good hasil produksi. Packing jual: 1 Pack=10 Box, 1 Box=4 Sachet ⇒ 1 Karton=30 Pack=300 Box=1200 Sachet.',
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'nature_id' => $natureFg->id,
                'item_type_id' => $itemTypeFg,
                'product_nature_id' => $productNatureInventory,
                'procurement_type_id' => $procurementProduce,
                'default_unit_id' => $units['KARTON']->id,
                'is_stock_item' => true,
                'is_sale_item' => true,
                'is_purchase_item' => false,
            ]);

            $this->syncConversionChain($fg, [
                ['from' => 'KARTON', 'to' => 'PACK', 'factor' => 30, 'level' => 1],
                ['from' => 'PACK', 'to' => 'BOX', 'factor' => 10, 'level' => 2],
                ['from' => 'BOX', 'to' => 'SACHET', 'factor' => 4, 'level' => 3],
            ], $units);

            $fgVariant = $this->ensureDefaultVariant($fg, 'FRD-FG-STD');
            $stockBootstrap->bootstrap($fg);
            $this->ensureStockInWarehouse(
                product: $fg,
                variant: $fgVariant,
                warehouse: $whFg,
                unitId: $units['SACHET']->id,
                quantity: 0,
                unitCost: null,
            );

            if ($priceList) {
                $this->syncPrices(
                    variant: $fgVariant,
                    companyId: $company->id,
                    branchId: $branch->id,
                    priceListId: $priceList->id,
                    defaultUnitId: $units['KARTON']->id,
                    smallestUnitId: $units['SACHET']->id,
                    factorToSmallest: 1200.0,
                    purchasePerDefault: 0,
                    sellingPerDefault: self::FG_SELLING_PER_KARTON,
                );
            }

            // BOM: 1 Box FG ← 4 Sachet RM (isi konten terjaga saat packing 3→4)
            $this->upsertBom(
                fg: $fg,
                fgVariant: $fgVariant,
                rm: $rm,
                rmVariant: $rmVariant,
                outputUnitId: $units['BOX']->id,
                outputQty: 1,
                componentUnitId: $units['SACHET']->id,
                componentQty: 4,
            );
        });

        $this->command?->info('Foredi products seeded:');
        $this->command?->line('  RM  FOREDI-RM  1 krt=30 pack=300 box=900 sachet  → '.self::WH_RM_CODE);
        foreach (self::PACKAGING_RMS as $pkg) {
            $this->command?->line('  RM  '.$pkg['code'].'  satuan: Pcs (1 Pcs)  → '.self::WH_RM_CODE);
        }
        $this->command?->line('  FG  FOREDI-FG  1 krt=30 pack=300 box=1200 sachet → '.self::WH_FG_CODE.' (saleable)');
        $this->command?->line('  BOM 1 Box FG ← 4 Sachet RM');
        $this->command?->line('  Stock RM awal: '.self::RM_INITIAL_KARTON.' Karton ('.(self::RM_INITIAL_KARTON * 900).' Sachet) @ '.self::WH_RM_CODE);
    }

    private function parameterDetailId(string $parameterCode, string $key): ?string
    {
        $parameterId = Parameter::query()->where('code', $parameterCode)->value('id');
        if (! $parameterId) {
            return null;
        }

        return ParameterDetail::query()
            ->where('parameter_id', $parameterId)
            ->where('key', $key)
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function upsertProduct(array $attrs): Product
    {
        $product = Product::withTrashed()->updateOrCreate(
            ['code' => $attrs['code']],
            array_merge($attrs, [
                'sku' => $attrs['code'],
                'deleted_at' => null,
            ])
        );

        return $product->fresh();
    }

    /**
     * @param  list<array{from: string, to: string, factor: float|int, level: int}>  $chain
     * @param  \Illuminate\Support\Collection<string, ProductUnit>  $units
     */
    private function syncConversionChain(Product $product, array $chain, $units): void
    {
        ProductUnitConversion::withTrashed()
            ->where('product_id', $product->id)
            ->forceDelete();

        foreach ($chain as $row) {
            ProductUnitConversion::create([
                'product_id' => $product->id,
                'from_unit_id' => $units[$row['from']]->id,
                'to_unit_id' => $units[$row['to']]->id,
                'conversion_factor' => $row['factor'],
                'conversion_level' => $row['level'],
                'description' => sprintf(
                    '1 %s = %s %s',
                    $row['from'],
                    $row['factor'],
                    $row['to']
                ),
            ]);
        }
    }

    private function ensureDefaultVariant(Product $product, string $sku): ProductVariant
    {
        $variant = ProductVariant::withTrashed()
            ->where('product_id', $product->id)
            ->orderBy('created_at')
            ->first();

        if ($variant) {
            $variant->fill([
                'sku' => $sku,
                'is_active' => true,
                'deleted_at' => null,
            ]);
            $variant->save();

            return $variant->fresh();
        }

        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    private function ensureStockInWarehouse(
        Product $product,
        ProductVariant $variant,
        Warehouse $warehouse,
        string $unitId,
        float $quantity,
        ?float $unitCost,
        ?string $seedBatchUnitId = null,
        ?float $seedBatchQuantity = null,
    ): void {
        $stock = ProductVariantStock::withTrashed()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        $qtyBefore = $stock ? (float) $stock->quantity : 0.0;

        if ($stock) {
            // Jangan menimpa stok live (mis. sudah ada receive); hanya isi jika kosong.
            $nextQty = $qtyBefore > 0 ? $qtyBefore : $quantity;
            $stock->fill([
                'product_id' => $product->id,
                'company_id' => $product->company_id,
                'branch_id' => $product->branch_id,
                'unit_id' => $stock->unit_id ?: $unitId,
                'quantity' => $nextQty,
                'deleted_at' => null,
            ]);
            $stock->save();
        } else {
            $stock = ProductVariantStock::create([
                'product_variant_id' => $variant->id,
                'product_id' => $product->id,
                'company_id' => $product->company_id,
                'branch_id' => $product->branch_id,
                'warehouse_id' => $warehouse->id,
                'unit_id' => $unitId,
                'quantity' => $quantity,
            ]);
        }

        // Hapus layer seed lama di gudang ini, lalu buat ulang jika ada qty+cost
        ProductCostLayer::withTrashed()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('source_type', 'SEED')
            ->forceDelete();

        if ($quantity > 0 && $unitCost !== null) {
            ProductCostLayer::create([
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'company_id' => $product->company_id,
                'branch_id' => $product->branch_id,
                'warehouse_id' => $warehouse->id,
                'unit_id' => $unitId,
                'quantity' => $quantity,
                'quantity_remaining' => $quantity,
                'unit_cost' => $unitCost,
                'source_type' => 'SEED',
                'source_id' => null,
                'effective_date' => now()->toDateString(),
            ]);
        }

        $this->ensureSeedStockMovement(
            stock: $stock,
            product: $product,
            variant: $variant,
            warehouse: $warehouse,
            unitId: $unitId,
            seedQuantity: $quantity,
        );

        if ($seedBatchUnitId && $seedBatchQuantity !== null) {
            $this->ensureSeedBatchStock(
                product: $product,
                warehouse: $warehouse,
                unitId: $seedBatchUnitId,
                quantity: $seedBatchQuantity,
            );
        }
    }

    /**
     * Catat mutasi INITIAL_BALANCE agar saldo seed masuk histori mutasi (idempotent per variant+warehouse).
     */
    private function ensureSeedStockMovement(
        ProductVariantStock $stock,
        Product $product,
        ProductVariant $variant,
        Warehouse $warehouse,
        string $unitId,
        float $seedQuantity,
    ): void {
        $existing = ProductStockMovement::withTrashed()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('notes', self::SEED_MOVEMENT_NOTES)
            ->first();

        if ($seedQuantity <= 0) {
            if ($existing) {
                $existing->forceDelete();
            }

            return;
        }

        $mutationTypeId = StockMutationType::query()
            ->where('code', 'INITIAL_BALANCE')
            ->value('id');

        // Saldo awal harus muncul sebelum receive (jika sudah ada).
        $createdAt = ProductStockMovement::query()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('notes', '!=', self::SEED_MOVEMENT_NOTES)
            ->orderBy('created_at')
            ->value('created_at');

        $seedAt = $createdAt
            ? \Carbon\Carbon::parse($createdAt)->subSecond()
            : now();

        $payload = [
            'product_variant_stock_id' => $stock->id,
            'product_variant_id' => $variant->id,
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'branch_id' => $product->branch_id,
            'warehouse_id' => $warehouse->id,
            'unit_id' => $unitId,
            'stock_mutation_type_id' => $mutationTypeId,
            'type' => 'in',
            'quantity' => $seedQuantity,
            'quantity_before' => 0,
            'quantity_after' => $seedQuantity,
            'reference_type' => 'SEED',
            'reference_id' => null,
            'notes' => self::SEED_MOVEMENT_NOTES,
            'deleted_at' => null,
        ];

        if ($existing) {
            $existing->fill($payload);
            $existing->save();
            $movement = $existing;
        } else {
            $movement = ProductStockMovement::create($payload);
        }

        // Pastikan saldo awal kronologis sebelum receive.
        $movement->timestamps = false;
        $movement->created_at = $seedAt;
        $movement->updated_at = $seedAt;
        $movement->save();
    }

    /**
     * Pastikan stok seed punya batch agar tidak muncul sebagai qty "di luar batch".
     */
    private function ensureSeedBatchStock(
        Product $product,
        Warehouse $warehouse,
        string $unitId,
        float $quantity,
    ): void {
        $batchNumber = 'SEED-'.$product->code;

        $batch = ProductBatch::withTrashed()
            ->where('product_id', $product->id)
            ->where('batch_number', $batchNumber)
            ->first();

        if ($quantity <= 0) {
            if ($batch) {
                ProductBatchStock::query()
                    ->where('product_batch_id', $batch->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->delete();
                $batch->forceDelete();
            }

            return;
        }

        if ($batch) {
            $batch->fill([
                'company_id' => $product->company_id,
                'expiry_date' => null,
                'deleted_at' => null,
            ]);
            $batch->save();
        } else {
            $batch = ProductBatch::create([
                'product_id' => $product->id,
                'company_id' => $product->company_id,
                'batch_number' => $batchNumber,
                'expiry_date' => null,
            ]);
        }

        $batchStock = ProductBatchStock::query()
            ->where('product_batch_id', $batch->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('unit_id', $unitId)
            ->first();

        if ($batchStock) {
            $batchStock->fill([
                'branch_id' => $product->branch_id,
                'quantity' => $quantity,
            ]);
            $batchStock->save();
        } else {
            ProductBatchStock::create([
                'product_batch_id' => $batch->id,
                'branch_id' => $product->branch_id,
                'warehouse_id' => $warehouse->id,
                'unit_id' => $unitId,
                'quantity' => $quantity,
            ]);
        }
    }

    private function syncPrices(
        ProductVariant $variant,
        string $companyId,
        string $branchId,
        string $priceListId,
        string $defaultUnitId,
        string $smallestUnitId,
        float $factorToSmallest,
        float $purchasePerDefault,
        ?float $sellingPerDefault,
    ): void {
        ProductVariantPrice::withTrashed()
            ->where('variant_id', $variant->id)
            ->where('price_list_id', $priceListId)
            ->forceDelete();

        $rows = [
            [
                'unit_id' => $defaultUnitId,
                'purchase_price' => $purchasePerDefault,
                'selling_price' => $sellingPerDefault,
            ],
        ];

        if ($smallestUnitId !== $defaultUnitId && $factorToSmallest > 0) {
            $rows[] = [
                'unit_id' => $smallestUnitId,
                'purchase_price' => $purchasePerDefault > 0 ? $purchasePerDefault / $factorToSmallest : 0,
                'selling_price' => $sellingPerDefault !== null ? $sellingPerDefault / $factorToSmallest : null,
            ];
        }

        foreach ($rows as $row) {
            ProductVariantPrice::create([
                'variant_id' => $variant->id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'unit_id' => $row['unit_id'],
                'price_list_id' => $priceListId,
                'purchase_price' => $row['purchase_price'],
                'selling_price' => $row['selling_price'],
            ]);
        }
    }

    private function upsertBom(
        Product $fg,
        ProductVariant $fgVariant,
        Product $rm,
        ProductVariant $rmVariant,
        string $outputUnitId,
        float $outputQty,
        string $componentUnitId,
        float $componentQty,
    ): void {
        $bom = BillOfMaterial::withTrashed()->updateOrCreate(
            [
                'product_id' => $fg->id,
                'version' => 1,
            ],
            [
                'company_id' => $fg->company_id,
                'product_variant_id' => $fgVariant->id,
                'output_unit_id' => $outputUnitId,
                'output_quantity' => $outputQty,
                'name' => 'Foredi RM→FG (3 sachet/box → 4 sachet/box)',
                'is_active' => true,
                'notes' => '1 Box FG (4 Sachet) membutuhkan 4 Sachet bahan baku Foredi. '
                    .'1 Karton FG (1200 Sachet) = 1200 Sachet RM = 4/3 Karton RM.',
                'deleted_at' => null,
            ]
        );

        BomItem::query()->where('bom_id', $bom->id)->delete();

        BomItem::create([
            'bom_id' => $bom->id,
            'component_product_id' => $rm->id,
            'component_variant_id' => $rmVariant->id,
            'unit_id' => $componentUnitId,
            'quantity' => $componentQty,
            'notes' => 'Isi sachet dari packing beli (3/box) diolah ke packing jual (4/box).',
        ]);
    }
}
