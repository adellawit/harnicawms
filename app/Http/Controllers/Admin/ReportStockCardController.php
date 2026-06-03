<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductNature;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Models\BusinessUnit;
use Illuminate\Http\Request;

class ReportStockCardController extends Controller
{
    protected function getBranchId(): ?string
    {
        return auth('web')->user()->current_business_unit_id;
    }

    public function indexView(Request $request)
    {
        $defaultBranchId = $this->getBranchId();
        $branchId = $request->get('branch_id', $defaultBranchId);
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (! $dateFrom || ! $dateTo) {
            $today = now();
            $dateFrom = $today->copy()->startOfMonth()->toDateString();
            $dateTo = $today->toDateString();
        }

        $branches = BusinessUnit::where('is_active', true)
            ->where('type_code', 'BRANCH')
            ->orderBy('name')
            ->get(['id', 'name']);

        $allProducts = Product::where('is_stock_item', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        $allNatures = ProductNature::orderBy('name')->get(['id', 'name']);
        $allCategories = ProductCategory::orderBy('name')->get(['id', 'name']);

        $productVariantsJson = [];
        if ($allProducts->isNotEmpty()) {
            $products = Product::with(['variants' => function ($q) {
                    $q->where('is_active', true)
                        ->with(['variantAttributes.attributeValue'])
                        ->orderBy('sort_order');
                }, 'defaultUnit:id,name,symbol'])
                ->whereIn('id', $allProducts->pluck('id'))
                ->get();

            foreach ($products as $p) {
                $variants = $p->variants->map(function ($v) {
                    $attrs = $v->variantAttributes->map(fn ($va) => $va->attributeValue?->value ?? '')->filter()->implode(' / ');
                    return [
                        'id' => $v->id,
                        'label' => $attrs ?: $v->sku,
                        'sku' => $v->sku,
                    ];
                });
                $productVariantsJson[$p->id] = $variants->values()->toArray();
            }
        }

        $movements = collect();
        $selectedProduct = null;
        $selectedVariant = null;
        $openingBalance = 0;
        $totalIn = 0;
        $totalOut = 0;
        $closingBalance = 0;
        $unitLabel = '';

        $productId = $request->get('product_id');
        $variantId = $request->get('variant_id');

        if ($productId && $branchId) {
            $selectedProduct = Product::with([
                'defaultUnit:id,name,symbol',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
            ])->find($productId);

            if ($selectedProduct) {
                $unitLabel = $selectedProduct->defaultUnit
                    ? ($selectedProduct->defaultUnit->symbol ?: $selectedProduct->defaultUnit->name)
                    : '';

                if ($variantId) {
                    $selectedVariant = $selectedProduct->variants->firstWhere('id', $variantId);
                }

                $query = ProductStockMovement::with([
                        'stockMutationType:id,code,name,direction',
                        'variant.variantAttributes.attributeValue',
                    ])
                    ->where('product_id', $productId)
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at');

                if ($variantId) {
                    $query->where('product_variant_id', $variantId);
                }

                if ($dateFrom) {
                    $dateFromTs = $dateFrom . ' 00:00:00';

                    $openingQuery = ProductStockMovement::where('product_id', $productId)
                        ->where('branch_id', $branchId)
                        ->whereNull('deleted_at')
                        ->where('created_at', '<', $dateFromTs);

                    if ($variantId) {
                        $openingQuery->where('product_variant_id', $variantId);
                    }

                    $openingBalance = (float) ($openingQuery->orderByDesc('created_at')->value('quantity_after') ?? 0);
                    $query->where('created_at', '>=', $dateFromTs);
                }

                if ($dateTo) {
                    $query->where('created_at', '<=', $dateTo . ' 23:59:59');
                }

                $movements = $query->orderBy('created_at', 'asc')->get();

                foreach ($movements as $mv) {
                    $qty = abs((float) $mv->quantity);
                    $isIn = $mv->stockMutationType
                        ? $mv->stockMutationType->direction === 'in'
                        : $mv->type === 'in';

                    if ($isIn) {
                        $totalIn += $qty;
                    } else {
                        $totalOut += $qty;
                    }
                }

                $closingBalance = $movements->isNotEmpty()
                    ? (float) $movements->last()->quantity_after
                    : $openingBalance;
            }
        }

        return view('admin.reporting.product.stock-card.index', compact(
            'branches', 'branchId', 'defaultBranchId', 'dateFrom', 'dateTo',
            'allProducts', 'allNatures', 'allCategories',
            'productVariantsJson',
            'movements', 'selectedProduct', 'selectedVariant',
            'openingBalance', 'totalIn', 'totalOut', 'closingBalance',
            'unitLabel'
        ));
    }
}
