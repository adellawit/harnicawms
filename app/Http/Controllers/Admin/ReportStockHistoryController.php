<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductNature;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportStockHistoryController extends Controller
{
    protected function getBranchId(): ?string
    {
        return auth('web')->user()->current_business_unit_id;
    }

    public function indexView(Request $request)
    {
        $defaultBranchId = $this->getBranchId();
        $branchId = $request->get('branch_id', $defaultBranchId);
        $selectedDate = $request->input('date', now()->format('Y-m-d'));
        $perPage = $request->get('per_page', 20);
        $isToday = $selectedDate === now()->format('Y-m-d');

        $allProducts = collect();
        $allNatures = ProductNature::orderBy('name')->get(['id', 'name']);
        $allCategories = ProductCategory::orderBy('name')->get(['id', 'name']);
        $productData = [];
        $paginator = null;

        if ($branchId) {
            $allProducts = Product::where('is_stock_item', true)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(['id', 'name', 'sku']);

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

            if ($isToday) {
                $productData = $this->buildCurrentStockData($paginator, $branchId);
            } else {
                $productData = $this->buildHistoricalStockData($paginator, $branchId, $selectedDate);
            }
        }

        return view('admin.reporting.product.stock-history.index', [
            'products' => collect($productData),
            'paginator' => $paginator,
            'allProducts' => $allProducts,
            'allNatures' => $allNatures,
            'allCategories' => $allCategories,
            'selectedDate' => $selectedDate,
        ]);
    }

    /**
     * For today's date: use ProductVariantStock for accurate per-variant data.
     */
    protected function buildCurrentStockData($paginator, string $branchId): array
    {
        $variantStocks = ProductVariantStock::with('unit:id,name,symbol')
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy('product_variant_id');

        $productData = [];
        foreach ($paginator as $product) {
            $variants = $product->variants;
            $unit = $product->defaultUnit;
            $unitLabel = $unit ? ($unit->symbol ?: $unit->name) : '-';

            if ($variants->isNotEmpty()) {
                foreach ($variants as $variant) {
                    $stock = $variantStocks->get($variant->id);
                    $attributes = $variant->variantAttributes->map(fn ($va) => $va->attributeValue?->value ?? '')->filter()->implode(' / ');

                    $productData[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'variant_id' => $variant->id,
                        'variant_name' => $attributes ?: $variant->sku,
                        'sku' => $variant->sku,
                        'nature' => $product->nature?->name ?? '-',
                        'category' => $product->category?->name ?? '-',
                        'unit' => $stock?->unit ? ($stock->unit->symbol ?: $stock->unit->name) : $unitLabel,
                        'quantity' => (float) ($stock?->quantity ?? 0),
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

                $productData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variant_id' => $defaultVariant->id,
                    'variant_name' => null,
                    'sku' => $product->sku,
                    'nature' => $product->nature?->name ?? '-',
                    'category' => $product->category?->name ?? '-',
                    'unit' => $stock?->unit ? ($stock->unit->symbol ?: $stock->unit->name) : $unitLabel,
                    'quantity' => (float) ($stock?->quantity ?? 0),
                    'is_first_variant' => true,
                    'variant_count' => 1,
                ];
            }
        }

        return $productData;
    }

    /**
     * For historical dates: use movements grouped by product_id
     * (product_variant_id may not exist on older records).
     */
    protected function buildHistoricalStockData($paginator, string $branchId, string $selectedDate): array
    {
        $dateEnd = $selectedDate . ' 23:59:59';
        $productIds = collect();
        foreach ($paginator as $product) {
            $productIds->push($product->id);
        }

        // Get historical stock per product from last movement before date
        $historicalStocks = [];
        if ($productIds->isNotEmpty()) {
            $latestMovements = ProductStockMovement::select(
                    'product_id',
                    DB::raw('MAX(created_at) as last_movement_at')
                )
                ->where('branch_id', $branchId)
                ->where('created_at', '<=', $dateEnd)
                ->whereNull('deleted_at')
                ->whereIn('product_id', $productIds)
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');

            foreach ($latestMovements as $productId => $latest) {
                $movement = ProductStockMovement::where('product_id', $productId)
                    ->where('branch_id', $branchId)
                    ->where('created_at', $latest->last_movement_at)
                    ->whereNull('deleted_at')
                    ->orderByDesc('created_at')
                    ->first();

                $historicalStocks[$productId] = $movement ? (float) $movement->quantity_after : 0;
            }
        }

        $productData = [];
        foreach ($paginator as $product) {
            $variants = $product->variants;
            $unit = $product->defaultUnit;
            $unitLabel = $unit ? ($unit->symbol ?: $unit->name) : '-';
            $productQty = $historicalStocks[$product->id] ?? 0;

            if ($variants->isNotEmpty() && $variants->count() > 1) {
                // Multi-variant: show product total on parent, individual rows without qty
                foreach ($variants as $variant) {
                    $attributes = $variant->variantAttributes->map(fn ($va) => $va->attributeValue?->value ?? '')->filter()->implode(' / ');

                    $productData[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'variant_id' => $variant->id,
                        'variant_name' => $attributes ?: $variant->sku,
                        'sku' => $variant->sku,
                        'nature' => $product->nature?->name ?? '-',
                        'category' => $product->category?->name ?? '-',
                        'unit' => $unitLabel,
                        'quantity' => null,
                        'product_quantity' => $productQty,
                        'is_first_variant' => $variant === $variants->first(),
                        'variant_count' => $variants->count(),
                    ];
                }
            } else {
                // Single variant or no variants
                $defaultVariant = $variants->first() ?? $product->variants()->first();
                if (!$defaultVariant) {
                    $defaultVariant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $product->sku ?? 'PROD-' . substr($product->id, 0, 8),
                        'is_active' => true,
                        'created_by' => auth('web')->id(),
                    ]);
                }

                $productData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variant_id' => $defaultVariant->id,
                    'variant_name' => null,
                    'sku' => $product->sku,
                    'nature' => $product->nature?->name ?? '-',
                    'category' => $product->category?->name ?? '-',
                    'unit' => $unitLabel,
                    'quantity' => $productQty,
                    'product_quantity' => $productQty,
                    'is_first_variant' => true,
                    'variant_count' => 1,
                ];
            }
        }

        return $productData;
    }
}
