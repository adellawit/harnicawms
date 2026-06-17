<?php

namespace Database\Seeders;

use App\Models\BillOfMaterial;
use App\Models\BomItem;
use App\Models\BusinessUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductNature;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ManufacturingBomSeeder extends Seeder
{
    /** @var array<string, ProductUnit> */
    protected array $units = [];

    /** @var array<string, ProductCategory> */
    protected array $categories = [];

    /** @var array<string, array{product: Product, variant: ProductVariant}> */
    protected array $products = [];

    public function run(): void
    {
        $path = database_path('data/product_manufacture.json');

        if (! is_file($path)) {
            $this->command?->warn('Skip ManufacturingBomSeeder: product_manufacture.json tidak ditemukan.');

            return;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || empty($data['products'])) {
            $this->command?->warn('Skip ManufacturingBomSeeder: data JSON tidak valid.');

            return;
        }

        $company = BusinessUnit::query()
            ->where('type_code', 'COMPANY')
            ->orderBy('created_at')
            ->first();

        if ($company === null) {
            $this->command?->warn('Skip ManufacturingBomSeeder: COMPANY belum ada.');

            return;
        }

        foreach ($data['units'] ?? [] as $unitDef) {
            $this->units[$unitDef['code']] = ProductUnit::firstOrCreate(
                ['code' => $unitDef['code']],
                [
                    'name' => $unitDef['name'],
                    'symbol' => $unitDef['symbol'] ?? $unitDef['code'],
                ]
            );
        }

        foreach ($data['categories'] ?? [] as $categoryDef) {
            $parentId = null;

            if (! empty($categoryDef['parent'])) {
                $parentId = $this->categories[$categoryDef['parent']]->id ?? null;
            }

            $category = ProductCategory::firstOrCreate(
                ['code' => $categoryDef['code'], 'company_id' => $company->id],
                [
                    'branch_id' => null,
                    'parent_id' => $parentId,
                    'name' => $categoryDef['name'],
                    'slug' => Str::slug($categoryDef['code']),
                    'sort_order' => 0,
                ]
            );

            $this->categories[$categoryDef['code']] = $category;
        }

        foreach ($data['products'] as $productDef) {
            $this->seedProduct($productDef, $company->id);
        }

        $bomCount = 0;

        foreach ($data['products'] as $productDef) {
            if (empty($productDef['bom']) || ! is_array($productDef['bom'])) {
                continue;
            }

            $code = (string) $productDef['code'];
            $entry = $this->products[$code] ?? null;

            if ($entry === null) {
                continue;
            }

            if (BillOfMaterial::query()->where('product_id', $entry['product']->id)->exists()) {
                continue;
            }

            $outputUnit = $this->units[$productDef['default_unit']] ?? ProductUnit::query()->first();

            if ($outputUnit === null) {
                continue;
            }

            $bom = BillOfMaterial::create([
                'company_id' => $company->id,
                'product_id' => $entry['product']->id,
                'product_variant_id' => $entry['variant']->id,
                'output_unit_id' => $outputUnit->id,
                'output_quantity' => 1,
                'name' => ($productDef['name'] ?? $code).' - BOM Demo',
                'version' => 1,
                'is_active' => true,
            ]);

            foreach ($productDef['bom'] as $itemDef) {
                $componentCode = (string) ($itemDef['product_code'] ?? '');
                $component = $this->products[$componentCode] ?? null;
                $unit = $this->units[$itemDef['unit'] ?? ''] ?? null;

                if ($component === null || $unit === null) {
                    continue;
                }

                BomItem::create([
                    'bom_id' => $bom->id,
                    'component_product_id' => $component['product']->id,
                    'component_variant_id' => $component['variant']->id,
                    'unit_id' => $unit->id,
                    'quantity' => (float) ($itemDef['quantity'] ?? 0),
                ]);
            }

            $bomCount++;
        }

        $this->command?->info(sprintf(
            'ManufacturingBomSeeder: %d produk & %d BOM (sepatu manufacturing) dibuat.',
            count($this->products),
            $bomCount
        ));
    }

    /**
     * @param  array<string, mixed>  $productDef
     */
    protected function seedProduct(array $productDef, string $companyId): void
    {
        $code = (string) $productDef['code'];
        $unit = $this->units[$productDef['default_unit'] ?? ''] ?? ProductUnit::query()->first();
        $nature = ProductNature::query()->where('code', $productDef['nature'] ?? 'FINISHED_GOOD')->first();
        $category = $this->categories[$productDef['category'] ?? ''] ?? null;

        if ($unit === null) {
            return;
        }

        $product = Product::withTrashed()->where('code', $code)->first();

        if ($product === null) {
            $product = Product::create([
                'company_id' => $companyId,
                'branch_id' => $companyId,
                'nature_id' => $nature?->id,
                'category_id' => $category?->id,
                'default_unit_id' => $unit->id,
                'name' => (string) ($productDef['name'] ?? $code),
                'code' => $code,
                'sku' => $code,
                'description' => $productDef['description'] ?? null,
                'is_stock_item' => (bool) ($productDef['is_stock_item'] ?? true),
                'is_sale_item' => (bool) ($productDef['is_sale_item'] ?? false),
                'is_purchase_item' => (bool) ($productDef['is_purchase_item'] ?? false),
            ]);
        }

        $variants = $productDef['variants'] ?? [null];
        $firstVariant = null;

        foreach ($variants as $index => $variantDef) {
            $sku = $code;

            if (is_array($variantDef) && ! empty($variantDef['sku_suffix'])) {
                $sku = $code.'-'.$variantDef['sku_suffix'];
            }

            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->when($index === 0, fn ($q) => $q->orderBy('created_at'))
                ->first();

            if ($variant === null) {
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'is_active' => true,
                ]);
            }

            if ($firstVariant === null) {
                $firstVariant = $variant;
            }
        }

        if ($firstVariant !== null) {
            $this->products[$code] = [
                'product' => $product,
                'variant' => $firstVariant,
            ];
        }
    }
}
