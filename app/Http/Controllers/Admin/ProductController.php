<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ProductExport;
use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Models\AttributeDefinition;
use App\Models\ParameterDetail;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductNature;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\ProductUnitConversion;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\ProductVariantPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\DataTables;

class ProductController extends Controller
{
    protected function generateSku(): string
    {
        // Format: DDMMYYYY + 7-digit sequence
        $prefix = date('dmY');
        $last = Product::withTrashed()
            ->where('sku', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(sku) DESC, sku DESC')
            ->value('sku');
        $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 7, '0', STR_PAD_LEFT);
    }

    protected function generateCode(): string
    {
        // Format: C-MMYYYY-XXXXX (5-digit sequence)
        $prefix = 'C-'.date('m-Y');
        $last = Product::withTrashed()
            ->where('code', 'like', $prefix.'%')
            ->orderBy('code', 'desc')
            ->value('code');
        if ($last) {
            $seq = (int) substr($last, strrpos($last, '-') + 1) + 1;
        } else {
            $seq = 1;
        }

        return $prefix.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $branchId = $request->get('branch_id', auth('web')->user()->current_business_unit_id);
        $isFilter = $status !== '' || $request->filled('sku') || $request->filled('product') || $request->filled('nature_id') || $request->filled('category_id') || $branchId !== auth('web')->user()->current_business_unit_id;
        $natures = ProductNature::whereNull('deleted_at')
            ->orderBy('name')
            ->pluck('name', 'id');
        $categories = ProductCategory::whereNull('deleted_at')
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('admin.product.master.index', compact('status', 'isFilter', 'natures', 'categories', 'branchId'));
    }

    public function indexData(Request $request)
    {
        $branchId = $request->get('branch_id', auth('web')->user()->current_business_unit_id);

        $data = Product::select(
            'products.id',
            'products.nature_id',
            'products.category_id',
            'products.default_unit_id',
            'products.name',
            'products.code',
            'products.sku',
            'products.created_at',
            'products.deleted_at',
            'products.branch_id'
        )
            ->with([
                'nature:id,name',
                'category:id,name',
                'defaultUnit:id,name,symbol',
                'unitConversions' => fn ($q) => $q->whereNull('deleted_at'),
                'prices' => fn ($q) => $branchId ? $q->where('branch_id', $branchId) : $q,
                'variants' => fn ($q) => $q->whereNull('deleted_at')
                    ->with(['variantAttributes.attributeValue:id,value']),
            ]);

        if ($branchId) {
            $data = $data->where('branch_id', $branchId);
        }

        if ($request->filled('sku') && $request->sku !== '') {
            $data = $data->where(function ($q) use ($request) {
                $q->where('sku', 'ilike', '%'.$request->sku.'%')
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'ilike', '%'.$request->sku.'%'));
            });
        }

        if ($request->filled('product') && $request->product !== '') {
            $data = $data->where(function ($q) use ($request) {
                $q->where('name', 'ilike', '%'.$request->product.'%')
                    ->orWhere('code', 'ilike', '%'.$request->product.'%');
            });
        }

        if ($request->filled('nature_id') && $request->nature_id !== '') {
            $data = $data->where('nature_id', $request->nature_id);
        }

        if ($request->filled('category_id') && $request->category_id !== '') {
            $data = $data->where('category_id', $request->category_id);
        }

        if ($request->filled('status') && $request->status !== '') {
            if ($request->status === 'deleted') {
                $data = $data->onlyTrashed();
            } elseif ($request->status === 'active') {
                $data = $data->withoutTrashed();
            } else {
                $data = $data->withTrashed();
            }
        }

        $data = $data->orderBy('name', 'ASC');

        /** @var \Illuminate\Database\Eloquent\Builder $data */
        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->addColumn('has_variants', fn ($row) => $row->variants->count() > 0)
            ->addColumn('variant_skus', fn ($row) => $row->variants->pluck('sku')->filter()->join(', ') ?: '-')
            ->addColumn('variants_list', function ($row) {
                if ($row->variants->isEmpty()) {
                    return '-';
                }

                $badges = [];
                foreach ($row->variants as $variant) {
                    // Build attribute names (e.g., "Merah, XL")
                    $attrNames = $variant->variantAttributes
                        ->pluck('attributeValue.value')
                        ->filter()
                        ->join(', ');

                    $parts = array_filter([
                        $variant->sku,
                        $variant->barcode,
                        $attrNames,
                    ]);

                    $label = implode(' | ', $parts);
                    $badges[] = '<div class="mb-1">'.$label.'</div>';
                }

                return '<div class="variant-list">'.implode('', $badges).'</div>';
            })
            ->addColumn('nature_name', fn ($row) => $row->nature?->name ?? '-')
            ->addColumn('category_name', fn ($row) => $row->category?->name ?? '-')
            ->addColumn('purchase_price', function ($row) {
                $factor = $row->getFactorToSmallest();
                $smallUnitId = $row->getSmallestUnitId();
                $price = $row->prices->firstWhere('unit_id', $smallUnitId);
                if (! $price) {
                    return '-';
                }
                $bigPrice = (float) $price->purchase_price * $factor;
                $unitLabel = $row->defaultUnit ? ($row->defaultUnit->symbol ?: $row->defaultUnit->name) : '';

                return format_number($bigPrice, 2, true).' <small class="text-muted">/ '.$unitLabel.'</small>';
            })
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('code', 'LIKE', "%{$search}%")
                            ->orWhere('sku', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->rawColumns(['variants_list', 'nature_name', 'unit_name', 'purchase_price'])
            ->toJson();
    }

    // === STEP 1: Insert Product Basic Info ===
    public function generateCodeApi(Request $request)
    {
        return response()->json(['code' => $this->generateCode()]);
    }

    public function insertViewStep1(Request $request)
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $natures = ProductNature::whereNull('deleted_at')
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->when($branchId, fn ($query) => $query->orWhere('branch_id', $branchId));
            })
            ->orderBy('name')
            ->get(['id', 'name']);
        $categories = ProductCategory::whereNull('deleted_at')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->when($companyId, fn ($query) => $query->orWhere('company_id', $companyId));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
        $units = ProductUnit::whereNull('deleted_at')
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->when($branchId, fn ($query) => $query->orWhere('branch_id', $branchId));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'symbol']);

        // Check for temp data from previous attempts
        $tempProduct = session()->get('temp_product', []);

        // Generate auto code for new products
        $generatedCode = $this->generateCode();

        return view('admin.product.master.insert-step1', compact('natures', 'categories', 'units', 'tempProduct', 'generatedCode'));
    }

    public function insertDataStep1(Request $request)
    {
        $request->merge([
            'min_stock' => normalize_number_input($request->min_stock),
            'max_stock' => normalize_number_input($request->max_stock),
        ]);

        $branchId = auth('web')->user()->current_business_unit_id;

        $request->validate([
            'nature_id' => 'nullable|exists:product.product_natures,id',
            'category_id' => 'nullable|exists:product.product_categories,id',
            'default_unit_id' => 'required|exists:product.product_units,id',
            'name' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($branchId) {
                    $exists = Product::withTrashed()
                        ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->exists();
                    if ($exists) {
                        $fail('Nama product "'.$value.'" sudah ada.');
                    }
                },
            ],
            'code' => 'nullable|string|max:100|unique:product.products,code',
            'description' => 'nullable|string',
            'min_stock' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'has_variants' => 'nullable|boolean',
            'is_sale_item' => 'nullable|boolean',
        ], [
            'code.unique' => 'Code already exists.',
        ]);

        // Store in session for next steps
        // Use generated code if not provided
        $code = $request->code ?: $this->generateCode();

        session()->put('temp_product', [
            'nature_id' => $request->nature_id ?: null,
            'category_id' => $request->category_id ?: null,
            'default_unit_id' => $request->default_unit_id,
            'name' => $request->name,
            'code' => $code,
            'description' => $request->description,
            'min_stock' => $request->min_stock ?? 0,
            'max_stock' => $request->max_stock,
            'has_variants' => $request->has('has_variants'),
            'is_sale_item' => $request->boolean('is_sale_item'),
            'purchase_price' => normalize_number_input($request->purchase_price),
            'selling_price' => normalize_number_input($request->selling_price),
        ]);

        if ($request->has('has_variants')) {
            return redirect()->route('product.insert.view.step2');
        } else {
            return redirect()->route('product.insert.view.step3');
        }
    }

    // === STEP 2: Unit Conversions ===
    public function insertViewStep2(Request $request)
    {
        $tempProduct = session('temp_product');
        if (! $tempProduct) {
            return redirect()->route('product.insert.view.step1')
                ->with('error', 'Please fill in product information first.');
        }

        $branchId = auth('web')->user()->current_business_unit_id;
        $units = ProductUnit::whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name', 'symbol']);

        $selectedUnit = ProductUnit::find($tempProduct['default_unit_id']);

        return view('admin.product.master.insert-step2', compact('tempProduct', 'units', 'selectedUnit'));
    }

    public function insertDataStep2(Request $request)
    {
        $tempProduct = session('temp_product');

        // If adding more conversions, validate the conversion fields
        if ($request->has('add_more')) {
            $request->merge([
                'conversion_factor' => normalize_number_input($request->conversion_factor),
            ]);

            $request->validate([
                'from_unit_id' => 'required|exists:product.product_units,id',
                'to_unit_id' => 'required|exists:product.product_units,id',
                'conversion_factor' => 'required|numeric|min:0.000001',
            ], [
                'conversion_factor.required' => 'Conversion factor is required.',
                'conversion_factor.min' => 'Conversion factor must be greater than 0.',
            ]);

            // Store conversions in session
            $conversions = session()->get('temp_conversions', []);
            $conversions[] = [
                'from_unit_id' => $request->from_unit_id,
                'to_unit_id' => $request->to_unit_id,
                'conversion_factor' => $request->conversion_factor,
            ];
            session()->put('temp_conversions', $conversions);

            return redirect()->route('product.insert.view.step2')
                ->with('success', 'Unit conversion added. Add more or continue.');
        }

        // Check if at least one conversion is added before proceeding
        $conversions = session()->get('temp_conversions', []);
        if (empty($conversions)) {
            return redirect()->route('product.insert.view.step2')
                ->with('error', 'Please add at least one unit conversion before proceeding.');
        }

        return redirect()->route('product.insert.view.step3');
    }

    // === STEP 3: Variants & Prices ===
    public function insertViewStep3(Request $request)
    {
        $tempProduct = session('temp_product');
        if (! $tempProduct) {
            return redirect()->route('product.insert.view.step1')
                ->with('error', 'Please fill in product information first.');
        }

        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $hasVariants = $tempProduct['has_variants'] ?? false;

        if ($hasVariants) {
            // Get attributes for variant selection
            $attributeDefinitions = AttributeDefinition::whereNull('deleted_at')
                ->with('attributeValues')
                ->orderBy('name')
                ->get();

            $tempVariants = session()->get('temp_variants', []);

            // Generate next SKU prefix for display
            $skuPrefix = date('dmy').'TH';
            $lastSku = Product::withTrashed()
                ->where('sku', 'like', $skuPrefix.'%')
                ->orderByRaw('LENGTH(sku) DESC, sku DESC')
                ->value('sku');
            $nextSkuNum = $lastSku ? (int) substr($lastSku, strlen($skuPrefix)) + 1 : 1;

            return view('admin.product.master.insert-step3-variants', compact(
                'tempProduct',
                'attributeDefinitions',
                'tempVariants',
                'branchId',
                'companyId',
                'skuPrefix',
                'nextSkuNum'
            ));
        } else {
            // No variants - just prices
            $units = ProductUnit::whereNull('deleted_at')
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(['id', 'name', 'symbol']);

            $conversions = session()->get('temp_conversions', []);

            return view('admin.product.master.insert-step3-prices', compact(
                'tempProduct',
                'units',
                'conversions',
                'branchId',
                'companyId'
            ));
        }
    }

    public function insertDataStep3(Request $request)
    {
        $tempProduct = session('temp_product');
        if (! $tempProduct) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Please fill in product information first.']);
            }

            return redirect()->route('product.insert.view.step1')
                ->with('error', 'Please fill in product information first.');
        }

        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        // Get variants from request body or session
        $variants = [];
        if ($request->has('variants')) {
            $variantsData = $request->input('variants');
            if (is_string($variantsData)) {
                $variants = json_decode($variantsData, true) ?? [];
            } else {
                $variants = $variantsData;
            }
        } else {
            $variants = session()->get('temp_variants', []);
        }

        // Validate at least one variant
        if (empty($variants)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Please add at least one variant.']);
            }

            return redirect()->route('product.insert.view.step3')
                ->with('error', 'Please add at least one variant.');
        }

        DB::beginTransaction();
        try {
            $user = auth('web')->user();
            $itemTypeId = ParameterDetail::whereHas('parameter', fn ($q) => $q->where('code', 'ITEM_TYPE'))->where('key', 'raw_material')->value('id');
            $productNatureId = ParameterDetail::whereHas('parameter', fn ($q) => $q->where('code', 'PRODUCT_NATURE'))->where('key', 'inventory')->value('id');
            $procurementTypeId = ParameterDetail::whereHas('parameter', fn ($q) => $q->where('code', 'PROCUREMENT_TYPE'))->where('key', 'purchase')->value('id');

            // Get the smallest unit for price calculation
            $smallestUnitId = null;
            $factor = 1;
            $conversions = session()->get('temp_conversions', []);
            if (! empty($conversions)) {
                $tempUnitId = $tempProduct['default_unit_id'] ?? null;
                if ($tempUnitId) {
                    $tempUnit = ProductUnit::find($tempUnitId);
                    if ($tempUnit) {
                        // For now, use the default unit as smallest (actual calculation would need conversion logic)
                        $smallestUnitId = $tempUnitId;
                    }
                }
            }

            // Create product
            $product = Product::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'nature_id' => $tempProduct['nature_id'] ?? null,
                'category_id' => $tempProduct['category_id'] ?? null,
                'item_type_id' => $itemTypeId,
                'product_nature_id' => $productNatureId,
                'procurement_type_id' => $procurementTypeId,
                'default_unit_id' => $tempProduct['default_unit_id'],
                'name' => $tempProduct['name'],
                'code' => $tempProduct['code'] ?? null,
                'description' => $tempProduct['description'],
                'sku' => $this->generateSku(),
                'is_stock_item' => true,
                'is_sale_item' => (bool) ($tempProduct['is_sale_item'] ?? false),
                'is_purchase_item' => true,
                'min_stock' => $tempProduct['min_stock'] ?? 0,
                'max_stock' => $tempProduct['max_stock'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Create unit conversions
            foreach ($conversions as $convData) {
                ProductUnitConversion::create([
                    'product_id' => $product->id,
                    'from_unit_id' => $convData['from_unit_id'],
                    'to_unit_id' => $convData['to_unit_id'],
                    'conversion_factor' => $convData['conversion_factor'],
                    'conversion_level' => 1,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            // Reload product to get conversions for price calculation
            $product->load(['unitConversions' => fn ($q) => $q->whereNull('deleted_at')]);
            $factor = $product->getFactorToSmallest();
            $smallestUnitId = $product->getSmallestUnitId();
            $defaultUnitId = $product->default_unit_id;

            $hasVariants = $tempProduct['has_variants'] ?? false;

            if ($hasVariants) {
                // Create variants with prices
                foreach ($variants as $variantData) {
                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $variantData['sku'] ?? null,
                        'barcode' => $variantData['barcode'] ?? null,
                        'weight' => ! empty($variantData['weight']) ? $variantData['weight'] : null,
                        'sort_order' => $variantData['sort_order'] ?? 0,
                        'is_active' => $variantData['is_active'] ?? true,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);

                    // Create variant attributes
                    if (! empty($variantData['attributes'])) {
                        foreach ($variantData['attributes'] as $attrData) {
                            ProductVariantAttribute::create([
                                'product_variant_id' => $variant->id,
                                'attribute_definition_id' => $attrData['attribute_definition_id'],
                                'attribute_value_id' => $attrData['attribute_value_id'],
                                'created_by' => $user->id,
                                'updated_by' => $user->id,
                            ]);
                        }
                    }

                    // Create variant prices - save at both default unit and smallest unit
                    $prices = $variantData['prices'] ?? [];

                    if (! empty($prices)) {
                        foreach ($prices as $priceListId => $priceData) {
                            $purchasePrice = isset($priceData['purchase_price']) ? normalize_number_input($priceData['purchase_price']) : 0;
                            $sellingPrice = isset($priceData['selling_price']) ? normalize_number_input($priceData['selling_price']) : null;

                            if ($purchasePrice > 0 || ($sellingPrice !== null && $sellingPrice > 0)) {
                                // Save price at default unit (From Unit / Satuan Besar)
                                if ($defaultUnitId) {
                                    ProductVariantPrice::create([
                                        'variant_id' => $variant->id,
                                        'company_id' => $companyId,
                                        'branch_id' => $branchId,
                                        'unit_id' => $defaultUnitId,
                                        'price_list_id' => $priceListId,
                                        'purchase_price' => $purchasePrice,
                                        'selling_price' => $sellingPrice,
                                        'created_by' => $user->id,
                                        'updated_by' => $user->id,
                                    ]);
                                }

                                // Also save price at smallest unit (To Unit / Satuan Kecil) if different
                                if ($smallestUnitId && $smallestUnitId != $defaultUnitId) {
                                    $smallPP = $purchasePrice > 0 ? $purchasePrice / $factor : 0;
                                    $smallSP = $sellingPrice !== null ? $sellingPrice / $factor : null;
                                    ProductVariantPrice::create([
                                        'variant_id' => $variant->id,
                                        'company_id' => $companyId,
                                        'branch_id' => $branchId,
                                        'unit_id' => $smallestUnitId,
                                        'price_list_id' => $priceListId,
                                        'purchase_price' => $smallPP,
                                        'selling_price' => $smallSP,
                                        'created_by' => $user->id,
                                        'updated_by' => $user->id,
                                    ]);
                                }
                            }
                        }
                    }
                }
            } else {
                // No variants - create default variant and prices
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => null,
                    'barcode' => null,
                    'sort_order' => 1,
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $purchasePrice = normalize_number_input($tempProduct['purchase_price'] ?? 0);
                $sellingPrice = normalize_number_input($tempProduct['selling_price'] ?? 0);

                if ($purchasePrice > 0 || ($sellingPrice !== null && $sellingPrice > 0)) {
                    // Save price at default unit (From Unit / Satuan Besar)
                    if ($defaultUnitId) {
                        ProductVariantPrice::create([
                            'variant_id' => $variant->id,
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'unit_id' => $defaultUnitId,
                            'purchase_price' => $purchasePrice,
                            'selling_price' => $sellingPrice,
                            'created_by' => $user->id,
                            'updated_by' => $user->id,
                        ]);
                    }

                    // Also save price at smallest unit (To Unit / Satuan Kecil) if different
                    if ($smallestUnitId && $smallestUnitId != $defaultUnitId) {
                        $smallPP = $purchasePrice > 0 ? $purchasePrice / $factor : 0;
                        $smallSP = $sellingPrice !== null ? $sellingPrice / $factor : null;
                        ProductVariantPrice::create([
                            'variant_id' => $variant->id,
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'unit_id' => $smallestUnitId,
                            'purchase_price' => $smallPP,
                            'selling_price' => $smallSP,
                            'created_by' => $user->id,
                            'updated_by' => $user->id,
                        ]);
                    }
                }
            }

            DB::commit();

            // Clear session data
            session()->forget(['temp_product', 'temp_conversions', 'temp_variants']);

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Product added successfully']);
            }

            return redirect()->route('product.index.view')->with('success', 'Product added successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to create product: '.$e->getMessage()]);
            }

            return redirect()->route('product.insert.view.step1')
                ->with('error', 'Failed to create product: '.$e->getMessage());
        }
    }

    // === EDIT View ===
    public function editView(Request $request, $id)
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $product = Product::where('id', $id)->withTrashed()
            ->with(['unitConversions.fromUnit', 'unitConversions.toUnit', 'variants.prices'])
            ->firstOrFail();

        $natures = ProductNature::whereNull('deleted_at')
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->when($branchId, fn ($query) => $query->orWhere('branch_id', $branchId));
            })
            ->orderBy('name')
            ->get(['id', 'name']);
        $categories = ProductCategory::whereNull('deleted_at')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->when($companyId, fn ($query) => $query->orWhere('company_id', $companyId));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
        $units = ProductUnit::whereNull('deleted_at')
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->when($branchId, fn ($query) => $query->orWhere('branch_id', $branchId));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'symbol']);

        return view('admin.product.master.edit', compact('product', 'natures', 'categories', 'units'));
    }

    public function variantsView(Request $request, $productId)
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $product = Product::where('id', $productId)
            ->with(['nature', 'defaultUnit', 'category'])
            ->with(['variants' => function ($q) {
                $q->whereNull('deleted_at')
                    ->with(['variantAttributes.attributeDefinition', 'variantAttributes.attributeValue'])
                    ->with(['prices' => function ($pq) {
                        $pq->whereNull('deleted_at')
                            ->with('priceList');
                    }]);
            }])
            ->firstOrFail();

        // Get all attribute definitions for variant management
        $attributeDefinitions = AttributeDefinition::whereNull('deleted_at')
            ->with('attributeValues')
            ->orderBy('name')
            ->get();

        // Load unit conversions for price display calculation
        $product->load(['unitConversions' => fn ($q) => $q->whereNull('deleted_at')]);
        $factor = $product->getFactorToSmallest();

        return view('admin.product.master.variants', compact(
            'product',
            'attributeDefinitions',
            'branchId',
            'companyId',
            'factor'
        ));
    }

    // === Variant CRUD Methods ===

    public function variantData(Request $request, $variantId)
    {
        $variant = ProductVariant::with([
            'variantAttributes.attributeDefinition',
            'variantAttributes.attributeValue',
            'prices',
        ])->findOrFail($variantId);

        return response()->json([
            'variant' => $variant,
            'attributes' => $variant->variantAttributes->map(function ($attr) {
                return [
                    'attribute_definition_id' => $attr->attribute_definition_id,
                    'attribute_value_id' => $attr->attribute_value_id,
                    'definition_name' => $attr->attributeDefinition?->name,
                    'value_name' => $attr->attributeValue?->value,
                ];
            }),
            'prices' => $variant->prices->keyBy('price_list_id')->map(function ($price) {
                return [
                    'purchase_price' => $price->purchase_price,
                    'selling_price' => $price->selling_price,
                ];
            }),
        ]);
    }

    public function storeVariant(Request $request, $productId)
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $product = Product::findOrFail($productId);

        $request->validate([
            'barcode' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'attributes' => 'nullable|array',
            'attributes.*.attribute_definition_id' => 'required|exists:product.attribute_definitions,id',
            'attributes.*.attribute_value_id' => 'required|exists:product.attribute_values,id',
            'prices' => 'nullable|array',
            'prices.*.price_list_id' => 'required|exists:product.product_price_lists,id',
            'prices.*.purchase_price' => 'nullable|numeric|min:0',
            'prices.*.selling_price' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $user = auth('web')->user();

            // Get units for price calculation
            $product->load(['unitConversions' => fn ($q) => $q->whereNull('deleted_at')]);
            $smallUnitId = $product->getSmallestUnitId();
            $defaultUnitId = $product->default_unit_id;
            $factor = $product->getFactorToSmallest();

            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'barcode' => $request->barcode,
                'weight' => $request->weight,
                'sort_order' => ProductVariant::where('product_id', $product->id)->max('sort_order') + 1,
                'is_active' => $request->is_active ?? true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Create variant attributes
            if ($request->filled('attributes')) {
                foreach ($request->input('attributes') as $attr) {
                    ProductVariantAttribute::create([
                        'product_variant_id' => $variant->id,
                        'attribute_definition_id' => $attr['attribute_definition_id'],
                        'attribute_value_id' => $attr['attribute_value_id'],
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            // Create variant prices
            if ($request->filled('prices')) {
                foreach ($request->prices as $priceData) {
                    if (isset($priceData['purchase_price']) || isset($priceData['selling_price'])) {
                        $pp = normalize_number_input($priceData['purchase_price'] ?? 0);
                        $sp = isset($priceData['selling_price']) ? normalize_number_input($priceData['selling_price']) : null;

                        // Save price at default unit (From Unit / Satuan Besar)
                        if ($defaultUnitId && ($pp > 0 || ($sp !== null && $sp > 0))) {
                            ProductVariantPrice::create([
                                'variant_id' => $variant->id,
                                'company_id' => $companyId,
                                'branch_id' => $branchId,
                                'unit_id' => $defaultUnitId,
                                'price_list_id' => $priceData['price_list_id'],
                                'purchase_price' => $pp,
                                'selling_price' => $sp,
                                'created_by' => $user->id,
                                'updated_by' => $user->id,
                            ]);
                        }

                        // Also save price at smallest unit (To Unit / Satuan Kecil) if different
                        if ($smallUnitId && $smallUnitId != $defaultUnitId && ($pp > 0 || ($sp !== null && $sp > 0))) {
                            $smallPP = $pp > 0 ? $pp / $factor : 0;
                            $smallSP = $sp !== null ? $sp / $factor : null;
                            ProductVariantPrice::create([
                                'variant_id' => $variant->id,
                                'company_id' => $companyId,
                                'branch_id' => $branchId,
                                'unit_id' => $smallUnitId,
                                'price_list_id' => $priceData['price_list_id'],
                                'purchase_price' => $smallPP,
                                'selling_price' => $smallSP,
                                'created_by' => $user->id,
                                'updated_by' => $user->id,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Variant added successfully',
                'variant' => $variant->load('variantAttributes.attributeValue'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to add variant: '.$e->getMessage(),
            ], 500);
        }
    }

    public function editVariantView(Request $request, $variantId)
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $variant = ProductVariant::with([
            'variantAttributes.attributeDefinition',
            'variantAttributes.attributeValue',
            'prices',
            'product',
        ])->findOrFail($variantId);

        $attributeDefinitions = AttributeDefinition::whereNull('deleted_at')
            ->with('attributeValues')
            ->orderBy('name')
            ->get();

        // Get active price lists
        $priceLists = \App\Models\ProductPriceList::whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get product for unit conversion
        $product = $variant->product;
        $product->load(['unitConversions' => fn ($q) => $q->whereNull('deleted_at')]);
        $factor = $product->getFactorToSmallest();

        // Convert prices back to display unit (default unit)
        $prices = $variant->prices->map(function ($price) use ($factor) {
            return [
                'id' => $price->id,
                'price_list_id' => $price->price_list_id,
                'purchase_price' => $price->purchase_price * $factor,
                'selling_price' => $price->selling_price * $factor,
            ];
        })->keyBy('price_list_id');

        return response()->json([
            'variant' => $variant,
            'attributes' => $variant->variantAttributes,
            'prices' => $prices,
            'attributeDefinitions' => $attributeDefinitions,
            'priceLists' => $priceLists,
        ]);
    }

    public function updateVariant(Request $request, $variantId)
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $variant = ProductVariant::findOrFail($variantId);

        $request->validate([
            'barcode' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'attributes' => 'nullable|array',
            'attributes.*.attribute_definition_id' => 'required|exists:product.attribute_definitions,id',
            'attributes.*.attribute_value_id' => 'required|exists:product.attribute_values,id',
            'prices' => 'nullable|array',
            'prices.*.price_list_id' => 'required|exists:product.product_price_lists,id',
            'prices.*.purchase_price' => 'nullable|numeric|min:0',
            'prices.*.selling_price' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $user = auth('web')->user();

            // Get product for unit conversion
            $product = $variant->product;
            $product->load(['unitConversions' => fn ($q) => $q->whereNull('deleted_at')]);
            $smallUnitId = $product->getSmallestUnitId();
            $factor = $product->getFactorToSmallest();

            // Update variant
            $variant->update([
                'barcode' => $request->barcode,
                'weight' => $request->weight,
                'is_active' => $request->is_active ?? true,
                'updated_by' => $user->id,
            ]);

            // Sync variant attributes
            ProductVariantAttribute::where('product_variant_id', $variant->id)->delete();
            if ($request->filled('attributes')) {
                foreach ($request->input('attributes') as $attr) {
                    ProductVariantAttribute::create([
                        'product_variant_id' => $variant->id,
                        'attribute_definition_id' => $attr['attribute_definition_id'],
                        'attribute_value_id' => $attr['attribute_value_id'],
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            // Sync prices
            if ($request->filled('prices')) {
                foreach ($request->prices as $priceData) {
                    $pp = normalize_number_input($priceData['purchase_price'] ?? 0);
                    $sp = isset($priceData['selling_price']) ? normalize_number_input($priceData['selling_price']) : null;

                    // Convert to smallest unit
                    $smallPP = $pp > 0 ? $pp / $factor : 0;
                    $smallSP = $sp !== null ? $sp / $factor : null;

                    ProductVariantPrice::updateOrCreate(
                        [
                            'variant_id' => $variant->id,
                            'branch_id' => $branchId,
                            'unit_id' => $smallUnitId,
                            'price_list_id' => $priceData['price_list_id'],
                        ],
                        [
                            'company_id' => $companyId,
                            'purchase_price' => $smallPP,
                            'selling_price' => $smallSP,
                            'updated_by' => $user->id,
                        ]
                    );
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Variant updated successfully',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update variant: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteVariant(Request $request, $variantId)
    {
        $variant = ProductVariant::findOrFail($variantId);

        $user = auth('web')->user();

        $variant->updated_by = $user->id;
        $variant->deleted_by = $user->id;
        $variant->save();
        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted successfully',
        ]);
    }

    public function editData(Request $request)
    {
        $request->merge([
            'min_stock' => normalize_number_input($request->min_stock),
            'max_stock' => normalize_number_input($request->max_stock),
        ]);

        $branchId = auth('web')->user()->current_business_unit_id;

        $request->validate([
            'id' => 'required|exists:product.products,id',
            'nature_id' => 'nullable|exists:product.product_natures,id',
            'category_id' => 'nullable|exists:product.product_categories,id',
            'default_unit_id' => 'required|exists:product.product_units,id',
            'name' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($branchId, $request) {
                    $exists = Product::withTrashed()
                        ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->where('id', '!=', $request->id)
                        ->exists();
                    if ($exists) {
                        $fail('Nama product "'.$value.'" sudah ada.');
                    }
                },
            ],
            'code' => ['nullable', 'string', 'max:100', Rule::unique('product.products', 'code')->ignore($request->id)],
            'description' => 'nullable|string',
            'min_stock' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
        ], [
            'code.unique' => 'Code already exists.',
        ]);

        $user = auth('web')->user();
        $product = Product::where('id', $request->id)->withTrashed()->firstOrFail();
        $product->update([
            'nature_id' => $request->nature_id ?: null,
            'category_id' => $request->category_id ?: null,
            'default_unit_id' => $request->default_unit_id,
            'name' => $request->name,
            'code' => $request->code ?: null,
            'description' => $request->description,
            'min_stock' => $request->min_stock ?? 0,
            'max_stock' => $request->max_stock,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('product.index.view')->with('success', 'Product updated successfully');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'product_id_deleted' => 'required|exists:product.products,id',
        ]);

        $product = Product::findOrFail($request->product_id_deleted);
        $product->updated_by = auth('web')->id();
        $product->deleted_by = auth('web')->id();
        $product->save();
        $product->delete();

        return redirect()->route('product.index.view')->with('success', 'Product deleted successfully');
    }

    public function addConversion(Request $request)
    {
        $request->validate([
            'raw_material_id' => 'required|exists:product.products,id',
            'from_unit_id' => 'required|exists:product.product_units,id',
            'to_unit_id' => 'required|exists:product.product_units,id',
            'conversion_factor' => 'required|numeric|min:0.000001',
        ]);

        $exists = ProductUnitConversion::where('product_id', $request->raw_material_id)
            ->where('from_unit_id', $request->from_unit_id)
            ->where('to_unit_id', $request->to_unit_id)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return back()->with('error', 'Conversion already exists.');
        }

        ProductUnitConversion::create([
            'product_id' => $request->raw_material_id,
            'from_unit_id' => $request->from_unit_id,
            'to_unit_id' => $request->to_unit_id,
            'conversion_factor' => normalize_number_input($request->conversion_factor),
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]);

        return back()->with('success', 'Conversion added successfully');
    }

    public function editConversion(Request $request)
    {
        $request->validate([
            'conversion_id' => 'required|exists:product.product_unit_conversions,id',
            'from_unit_id' => 'required|exists:product.product_units,id',
            'to_unit_id' => 'required|exists:product.product_units,id',
            'conversion_factor' => 'required|numeric|min:0.000001',
        ]);

        $conversion = ProductUnitConversion::findOrFail($request->conversion_id);

        $exists = ProductUnitConversion::where('product_id', $conversion->product_id)
            ->where('from_unit_id', $request->from_unit_id)
            ->where('to_unit_id', $request->to_unit_id)
            ->where('id', '!=', $request->conversion_id)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return back()->with('error', 'Conversion already exists.');
        }

        $conversion->update([
            'from_unit_id' => $request->from_unit_id,
            'to_unit_id' => $request->to_unit_id,
            'conversion_factor' => normalize_number_input($request->conversion_factor),
            'updated_by' => auth('web')->id(),
        ]);

        return back()->with('success', 'Conversion updated successfully');
    }

    public function deleteConversion(Request $request)
    {
        $request->validate([
            'conversion_id' => 'required|exists:product.product_unit_conversions,id',
        ]);

        $conversion = ProductUnitConversion::findOrFail($request->conversion_id);
        $conversion->forceDelete();

        return back()->with('success', 'Conversion deleted successfully');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'product_id_restored' => 'required|exists:product.products,id',
        ]);

        $product = Product::withTrashed()->findOrFail($request->product_id_restored);
        $product->updated_by = auth('web')->id();
        $product->deleted_by = null;
        $product->save();
        $product->restore();

        return redirect()->route('product.index.view')->with('success', 'Product restored successfully');
    }

    public function removeTempConversion(Request $request)
    {
        $request->validate([
            'index' => 'required|integer|min:0',
        ]);

        $conversions = session()->get('temp_conversions', []);
        if (isset($conversions[$request->index])) {
            unset($conversions[$request->index]);
            session()->put('temp_conversions', array_values($conversions));
        }

        return response()->json([
            'success' => true,
            'message' => 'Conversion removed successfully.',
        ]);
    }

    public function updateTempConversion(Request $request)
    {
        $request->validate([
            'index' => 'required|integer|min:0',
            'from_unit_id' => 'required|exists:product.product_units,id',
            'to_unit_id' => 'required|exists:product.product_units,id',
            'conversion_factor' => 'required|numeric|min:0.000001',
        ], [
            'conversion_factor.required' => 'Conversion factor is required.',
            'conversion_factor.min' => 'Conversion factor must be greater than 0.',
        ]);

        $request->merge([
            'conversion_factor' => normalize_number_input($request->conversion_factor),
        ]);

        $conversions = session()->get('temp_conversions', []);
        if (isset($conversions[$request->index])) {
            $conversions[$request->index] = [
                'from_unit_id' => $request->from_unit_id,
                'to_unit_id' => $request->to_unit_id,
                'conversion_factor' => $request->conversion_factor,
            ];
            session()->put('temp_conversions', $conversions);
        }

        return response()->json([
            'success' => true,
            'message' => 'Conversion updated successfully.',
        ]);
    }

    public function importData(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ], [
            'file.required' => 'File Excel wajib diupload.',
            'file.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 5MB.',
        ]);

        $user = auth('web')->user();
        $branchId = $user->current_business_unit_id;
        if (! $branchId) {
            return redirect()->back()->with('error', 'Tidak ada branch yang terkait dengan akun Anda.');
        }

        $companyId = $user->getCompanyIdForProduct();
        $import = new ProductImport($user->id, $branchId, $companyId);
        Excel::import($import, $request->file('file'));

        $errors = $import->getErrors();
        if (! empty($errors)) {
            return redirect()->route('product.index.view')
                ->with('warning', 'Import dibatalkan. Tidak ada data yang disimpan.')
                ->with('import_errors', $errors);
        }

        if ($import->getImported() > 0) {
            return redirect()->route('product.index.view')
                ->with('success', "{$import->getImported()} data berhasil diimport.");
        }

        return redirect()->route('product.index.view')
            ->with('success', 'Tidak ada data baru untuk diimport.');
    }

    public function exportData()
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $filename = 'product_'.date('Ymd_His').'.xlsx';

        return Excel::download(new ProductExport($branchId), $filename);
    }

    public function downloadTemplate()
    {
        $headers = [
            'No', 'SKU', 'Kode', 'Product', 'Nature', 'Satuan Besar',
            'Konversi', 'Satuan Kecil', 'Jumlah (Satuan Besar)',
            'Harga Beli Satuan Besar', 'Jumlah Minimum (Satuan Besar)', 'Kategori',
        ];

        $example = [
            1, '', 'AQ-001', 'Aqua', 'Dus',
            24, 'Botol', 10, 50000, 5, 'Minuman',
        ];

        $callback = function () use ($headers, $example) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $headers);
            fputcsv($file, $example);
            fclose($file);
        };

        return response()->streamDownload($callback, 'template_import_product.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function upsertPrice(string $productId, string $branchId, ?string $companyId, string $unitId, float $pp, ?float $sp, string $userId): void
    {
        $price = ProductPrice::withTrashed()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('unit_id', $unitId)
            ->first();

        if ($price) {
            if ($price->trashed()) {
                $price->restore();
            }
            $price->update([
                'company_id' => $companyId,
                'purchase_price' => $pp,
                'selling_price' => $sp,
                'updated_by' => $userId,
                'deleted_by' => null,
            ]);
        } elseif ($pp > 0 || ($sp !== null && $sp > 0)) {
            ProductPrice::create([
                'product_id' => $productId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'unit_id' => $unitId,
                'purchase_price' => $pp,
                'selling_price' => $sp,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }
}
