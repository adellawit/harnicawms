<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductNature;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\StockMutationType;
use App\Support\InventoryWarehouseContext;
use Illuminate\Http\Request;

class StockOpnameController extends Controller
{
    protected function getBranchId(): ?string
    {
        return auth('web')->user()->current_business_unit_id;
    }

    protected function getCompanyId(): ?string
    {
        return auth('web')->user()->getCompanyIdForProduct();
    }

    public function indexView(Request $request)
    {
        $ctx = InventoryWarehouseContext::resolve($request, autoSelectDefault: false);
        $branchId = $ctx['filter_branch_id'];
        $warehouseId = $ctx['warehouse_id'];
        $selectedWarehouse = $ctx['warehouse'];
        $warehouses = $ctx['warehouses'];
        $accessibleWarehouseIds = $warehouses->pluck('id')->all();
        $perPage = $request->get('per_page', 20);

        $query = Product::with([
                'defaultUnit:id,name,symbol',
                'nature:id,name',
                'category:id,name',
                'variants' => function ($q) {
                    $q->with(['variantAttributes.attributeValue.attributeDefinition:id,name'])
                        ->where('is_active', true)
                        ->orderBy('sort_order');
                },
            ])
            ->where('is_stock_item', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name');

        if ($request->filled('product_id')) {
            $query->where('id', $request->product_id);
        }
        if ($request->filled('sku')) {
            $query->where('sku', $request->sku);
        }
        if ($request->filled('nature_id')) {
            $query->where('nature_id', $request->nature_id);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('variant_search')) {
            $variantSearch = $request->variant_search;
            $query->whereHas('variants', function ($q) use ($variantSearch) {
                $q->where('sku', 'like', "%{$variantSearch}%");
            });
        }

        $paginator = $query->paginate($perPage);

        $allProducts = Product::where('is_stock_item', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        $allNatures = ProductNature::orderBy('name')->get(['id', 'name']);
        $allCategories = ProductCategory::orderBy('name')->get(['id', 'name']);

        $variantStocks = collect();
        if ($warehouseId) {
            $variantStocks = ProductVariantStock::with('unit:id,name,symbol')
                ->whereNull('deleted_at')
                ->where('warehouse_id', $warehouseId)
                ->get()
                ->keyBy('product_variant_id');
        } else {
            // Agregat semua gudang yang accessible (default: All Warehouse)
            $stockQuery = ProductVariantStock::with('unit:id,name,symbol')
                ->whereNull('deleted_at')
                ->when(
                    ! empty($accessibleWarehouseIds),
                    fn ($q) => $q->whereIn('warehouse_id', $accessibleWarehouseIds),
                    fn ($q) => $q->when($branchId, fn ($q2) => $q2->where('branch_id', $branchId))
                );

            $variantStocks = $stockQuery->get()
                ->groupBy('product_variant_id')
                ->map(function ($rows) {
                    $first = $rows->first();
                    // Clone ringan agar qty agregat tidak menimpa model asli di memory lain
                    $agg = clone $first;
                    $agg->quantity = $rows->sum(fn ($row) => (float) $row->quantity);

                    return $agg;
                });
        }

        $productData = [];
        foreach ($paginator as $product) {
            $variants = $product->variants;
            $hasVariants = $variants->isNotEmpty();

            if ($hasVariants) {
                foreach ($variants as $variant) {
                    $stock = $variantStocks->get($variant->id);
                    $unit = $stock?->unit ?? $product->defaultUnit;

                    $attributes = $variant->variantAttributes->map(function ($va) {
                        return $va->attributeValue?->value ?? '';
                    })->filter()->implode(' / ');

                    $productData[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'variant_id' => $variant->id,
                        'variant_name' => $attributes ?: $variant->sku,
                        'sku' => $variant->sku,
                        'nature' => $product->nature?->name ?? '-',
                        'category' => $product->category?->name ?? '-',
                        'quantity' => (float) ($stock?->quantity ?? 0),
                        'unit' => $unit?->symbol ?? $unit?->name ?? '-',
                        'unit_id' => $unit?->id ?? $product->default_unit_id,
                        'is_first_variant' => $variant === $variants->first(),
                        'variant_count' => $variants->count(),
                    ];
                }
            } else {
                $defaultVariant = $variants->first() ?? $product->variants()->first();
                if (!$defaultVariant) {
                    $defaultVariant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $product->sku ?? 'PROD-' . substr($product->id, 0, 8),
                        'is_active' => true,
                        'created_by' => auth('web')->id(),
                    ]);
                }

                $stock = $variantStocks->get($defaultVariant->id);
                $unit = $stock?->unit ?? $product->defaultUnit;

                $productData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variant_id' => $defaultVariant->id,
                    'variant_name' => null,
                    'sku' => $product->sku,
                    'nature' => $product->nature?->name ?? '-',
                    'category' => $product->category?->name ?? '-',
                    'quantity' => (float) ($stock?->quantity ?? 0),
                    'unit' => $unit?->symbol ?? $unit?->name ?? '-',
                    'unit_id' => $unit?->id ?? $product->default_unit_id,
                    'is_first_variant' => true,
                    'variant_count' => 1,
                ];
            }
        }

        return view('admin.product.stock-opname.index', [
            'products' => collect($productData),
            'paginator' => $paginator,
            'allProducts' => $allProducts,
            'allNatures' => $allNatures,
            'allCategories' => $allCategories,
            'warehouses' => $warehouses,
            'selectedWarehouse' => $selectedWarehouse,
            'filterWarehouseId' => $warehouseId,
            'filterBranchId' => $branchId,
        ]);
    }

    public function saveData(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|uuid',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product.product_variants,id',
            'items.*.product_id' => 'required|exists:product.products,id',
            'items.*.unit_id' => 'required',
            'items.*.physical_qty' => 'required|string',
            'notes' => 'nullable|string|max:500',
        ]);

        $warehouse = InventoryWarehouseContext::assertAccessible($request->warehouse_id);
        $branchId = $warehouse->branch_id ?: $warehouse->company_id ?: $this->getBranchId();
        $companyId = $this->getCompanyId();
        if (! $branchId) {
            return response()->json(['success' => false, 'message' => 'No branch assigned.'], 422);
        }

        $userId = auth('web')->id();
        $notes = $request->notes ?: 'Stock opname';
        $adjusted = 0;
        $warehouseId = $warehouse->id;

        $adjInType = StockMutationType::where('code', 'STOCK_ADJUSTMENT_IN')->first();
        $adjOutType = StockMutationType::where('code', 'STOCK_ADJUSTMENT_OUT')->first();

        foreach ($request->items as $item) {
            $physicalQty = normalize_number_input($item['physical_qty']);
            if ($physicalQty === null || $physicalQty < 0) continue;

            $variantId = $item['variant_id'];
            $productId = $item['product_id'];
            $unitId = $item['unit_id'];

            $stock = ProductVariantStock::withTrashed()
                ->where('product_variant_id', $variantId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            $systemQty = $stock ? (float) $stock->quantity : 0;
            $diff = $physicalQty - $systemQty;

            if (abs($diff) < 0.000001) continue;

            if ($stock) {
                if ($stock->trashed()) $stock->restore();
                $stock->update(['quantity' => $physicalQty, 'updated_by' => $userId]);
            } else {
                $stock = ProductVariantStock::create([
                    'product_variant_id' => $variantId,
                    'product_id' => $productId,
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'unit_id' => $unitId,
                    'quantity' => $physicalQty,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            $isPositive = $diff > 0;
            ProductStockMovement::create([
                'product_variant_stock_id' => $stock->id,
                'product_variant_id' => $variantId,
                'product_id' => $productId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'unit_id' => $unitId,
                'stock_mutation_type_id' => ($isPositive ? $adjInType : $adjOutType)?->id,
                'type' => $isPositive ? 'in' : 'out',
                'quantity' => $diff,
                'quantity_before' => $systemQty,
                'quantity_after' => $physicalQty,
                'notes' => $notes,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $adjusted++;
        }

        if ($adjusted === 0) {
            return response()->json(['success' => true, 'message' => 'No stock difference found. All quantities match.']);
        }

        return response()->json([
            'success' => true,
            'message' => "{$adjusted} item(s) adjusted successfully.",
        ]);
    }
}
