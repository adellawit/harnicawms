<?php

namespace Database\Seeders;

use App\Models\AttributeDefinition;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductNature;
use App\Models\ProductPriceList;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\ProductVariantPrice;
use App\Models\ParameterDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductMenuSeeder extends Seeder
{
    private const PRICE_LIST_MAP = [
        'reguler' => 'REGULER',
        'grabfood' => 'GRABFOOD',
        'gofood' => 'GOFOOD',
        'shopee_food' => 'SHOPEE-FOOD',
    ];

    public function run(): void
    {
        $path = database_path('data/product_wwwcoffee_menu.json');

        if (! is_file($path)) {
            $this->command?->error("File tidak ditemukan: {$path}");

            return;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || empty($data['products'])) {
            $this->command?->error('Data menu tidak valid.');

            return;
        }

        $company = DB::table('master_data.business_units')
            ->where('code', 'WWW-001')
            ->where('type_code', 'COMPANY')
            ->first();

        if (! $company) {
            $this->command?->error('Company WWW tidak ditemukan.');

            return;
        }

        $branches = DB::table('master_data.business_units')
            ->where('type_code', 'BRANCH')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($branches->isEmpty()) {
            $this->command?->error('Tidak ada cabang aktif.');

            return;
        }

        $priceLists = ProductPriceList::query()
            ->whereIn('code', array_values(self::PRICE_LIST_MAP))
            ->where('is_active', true)
            ->pluck('id', 'code');

        if ($priceLists->count() < count(self::PRICE_LIST_MAP)) {
            $this->command?->error('Jalankan ProductPriceListSeeder terlebih dahulu.');

            return;
        }

        $branchId = $branches->first()->id;
        $companyId = $company->id;

        $unit = $this->ensureUnit($companyId, $branchId, $data['unit'] ?? []);
        $categoryId = $this->ensureCategory($companyId, $branchId, $data['category'] ?? []);
        $varianAttr = $this->ensureVarianAttribute($companyId, $data['attribute'] ?? []);

        $nature = ProductNature::where('code', 'FINISHED_GOOD')->first();
        $itemTypeId = ParameterDetail::whereHas('parameter', fn ($q) => $q->where('code', 'ITEM_TYPE'))
            ->where('key', 'finished_good')->value('id');
        $productNatureId = ParameterDetail::whereHas('parameter', fn ($q) => $q->where('code', 'PRODUCT_NATURE'))
            ->where('key', 'non_inventory')->value('id');
        $procurementTypeId = ParameterDetail::whereHas('parameter', fn ($q) => $q->where('code', 'PROCUREMENT_TYPE'))
            ->where('key', 'produce')->value('id');

        DB::statement('TRUNCATE TABLE product.product_variant_prices CASCADE');
        DB::statement('TRUNCATE TABLE product.product_variant_attributes CASCADE');
        DB::statement('TRUNCATE TABLE product.product_variant_stock CASCADE');
        DB::statement('TRUNCATE TABLE product.product_variants CASCADE');
        DB::statement('TRUNCATE TABLE product.products CASCADE');

        $productCount = 0;
        $variantCount = 0;
        $priceCount = 0;

        foreach ($data['products'] as $productData) {
            $product = Product::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'nature_id' => $nature?->id,
                'category_id' => $categoryId,
                'item_type_id' => $itemTypeId,
                'product_nature_id' => $productNatureId,
                'procurement_type_id' => $procurementTypeId,
                'default_unit_id' => $unit->id,
                'name' => $productData['name'],
                'code' => $productData['code'],
                'sku' => $productData['code'],
                'description' => $productData['description'] ?? null,
                'is_stock_item' => false,
                'is_sale_item' => true,
                'is_purchase_item' => false,
                'min_stock' => 0,
            ]);

            $productCount++;
            $sortOrder = 0;

            foreach ($productData['variants'] as $variantData) {
                $sortOrder++;
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $variantData['kode'],
                    'barcode' => $variantData['kode'],
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]);

                $variantCount++;

                $varianLabel = trim((string) ($variantData['varian'] ?? ''));
                if ($varianLabel !== '' && $varianAttr) {
                    $valueId = $varianAttr['values'][$varianLabel] ?? null;
                    if ($valueId) {
                        ProductVariantAttribute::create([
                            'product_variant_id' => $variant->id,
                            'attribute_definition_id' => $varianAttr['definition_id'],
                            'attribute_value_id' => $valueId,
                        ]);
                    }
                }

                foreach ($branches as $branch) {
                    foreach (self::PRICE_LIST_MAP as $jsonKey => $listCode) {
                        $amount = (float) ($variantData[$jsonKey] ?? 0);
                        $listId = $priceLists[$listCode] ?? null;

                        if (! $listId) {
                            continue;
                        }

                        ProductVariantPrice::create([
                            'variant_id' => $variant->id,
                            'company_id' => $companyId,
                            'branch_id' => $branch->id,
                            'unit_id' => $unit->id,
                            'price_list_id' => $listId,
                            'purchase_price' => (float) ($variantData['harga_beli'] ?? 0),
                            'selling_price' => $amount > 0 ? $amount : null,
                        ]);

                        $priceCount++;
                    }
                }
            }
        }

        $this->command?->info(sprintf(
            'Menu wwwcoffee: %d produk, %d varian, %d harga (semua cabang × price list).',
            $productCount,
            $variantCount,
            $priceCount
        ));
    }

    /**
     * @param  array<string, mixed>  $unitData
     */
    private function ensureUnit(string $companyId, string $branchId, array $unitData): ProductUnit
    {
        $code = $unitData['code'] ?? 'PCS';

        return ProductUnit::firstOrCreate(
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'code' => $code,
            ],
            [
                'name' => $unitData['name'] ?? 'Pcs',
                'symbol' => $unitData['symbol'] ?? 'pcs',
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $categoryData
     */
    private function ensureCategory(string $companyId, string $branchId, array $categoryData): ?string
    {
        if ($categoryData === []) {
            return null;
        }

        $parentId = null;
        if (! empty($categoryData['parent'])) {
            $parent = ProductCategory::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $categoryData['parent']['code'],
                ],
                [
                    'branch_id' => $branchId,
                    'name' => $categoryData['parent']['name'],
                    'slug' => Str::slug($categoryData['parent']['name']),
                ]
            );
            $parentId = $parent->id;
        }

        $category = ProductCategory::firstOrCreate(
            [
                'company_id' => $companyId,
                'code' => $categoryData['code'],
            ],
            [
                'branch_id' => $branchId,
                'parent_id' => $parentId,
                'name' => $categoryData['name'],
                'slug' => Str::slug($categoryData['name']),
            ]
        );

        return $category->id;
    }

    /**
     * @param  array<string, mixed>  $attrData
     * @return array{definition_id: string, values: array<string, string>}|null
     */
    private function ensureVarianAttribute(string $companyId, array $attrData): ?array
    {
        if (empty($attrData['values'])) {
            return null;
        }

        $definition = AttributeDefinition::firstOrCreate(
            ['code' => 'MENU-VARIAN'],
            [
                'company_id' => $companyId,
                'name' => $attrData['name'] ?? 'Varian',
                'type' => 'select',
                'is_variant_attribute' => true,
                'is_filterable' => true,
                'is_required' => false,
            ]
        );

        $valueMap = [];
        $sort = 0;

        foreach ($attrData['values'] as $label) {
            $sort++;
            $value = AttributeValue::firstOrCreate(
                [
                    'attribute_definition_id' => $definition->id,
                    'value' => $label,
                ],
                [
                    'code' => Str::slug($label),
                    'sort_order' => $sort,
                ]
            );
            $valueMap[$label] = $value->id;
        }

        return [
            'definition_id' => $definition->id,
            'values' => $valueMap,
        ];
    }
}
