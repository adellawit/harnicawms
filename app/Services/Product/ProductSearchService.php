<?php

namespace App\Services\Product;

use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantStock;
use App\Support\WmsContext;
use Illuminate\Support\Collection;

class ProductSearchService
{
    /**
     * @return Collection<int, array{
     *   variant_id: string,
     *   product_id: string,
     *   unit_id: string,
     *   sku: string,
     *   label: string,
     *   unit_price: float,
     *   stock: float
     * }>
     */
    public function search(string $query, string $branchId, ?string $companyId, string $priceListId, int $limit = 5): Collection
    {
        $keyword = trim($query);

        if ($keyword === '') {
            return collect();
        }

        $variants = $this->findVariantsByKeyword($keyword, $branchId, $limit * 3);

        if ($variants->isEmpty()) {
            $variants = $this->findVariantsByTokens($keyword, $branchId, $limit * 3);
        }

        return $this->mapVariantResults($variants, $branchId, $priceListId, $limit);
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    protected function findVariantsByKeyword(string $keyword, string $branchId, int $limit): Collection
    {
        return ProductVariant::query()
            ->with(['product', 'variantAttributes.attributeValue'])
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where(function ($q) use ($keyword) {
                $q->where('sku', 'ilike', '%'.$keyword.'%')
                    ->orWhere('barcode', 'ilike', '%'.$keyword.'%')
                    ->orWhereHas('product', function ($p) use ($keyword) {
                        $p->whereNull('deleted_at')
                            ->where('is_sale_item', true)
                            ->where(function ($nameQ) use ($keyword) {
                                $nameQ->where('name', 'ilike', '%'.$keyword.'%')
                                    ->orWhere('code', 'ilike', '%'.$keyword.'%');
                            });
                    })
                    ->orWhereHas('variantAttributes.attributeValue', function ($av) use ($keyword) {
                        $av->where('value', 'ilike', '%'.$keyword.'%');
                    });
            })
            ->whereHas('product', fn ($p) => $this->applyBranchProductScope($p, $branchId))
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    protected function findVariantsByTokens(string $keyword, string $branchId, int $limit): Collection
    {
        $tokens = $this->tokenizeProductQuery($keyword);

        if (count($tokens) < 2) {
            return collect();
        }

        $query = ProductVariant::query()
            ->with(['product', 'variantAttributes.attributeValue'])
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereHas('product', fn ($p) => $this->applyBranchProductScope($p, $branchId));

        foreach ($tokens as $token) {
            $query->where(function ($q) use ($token) {
                $q->where('sku', 'ilike', '%'.$token.'%')
                    ->orWhere('barcode', 'ilike', '%'.$token.'%')
                    ->orWhereHas('product', function ($p) use ($token) {
                        $p->whereNull('deleted_at')
                            ->where('is_sale_item', true)
                            ->where(function ($nameQ) use ($token) {
                                $nameQ->where('name', 'ilike', '%'.$token.'%')
                                    ->orWhere('code', 'ilike', '%'.$token.'%');
                            });
                    })
                    ->orWhereHas('variantAttributes.attributeValue', function ($av) use ($token) {
                        $av->where('value', 'ilike', '%'.$token.'%');
                    });
            });
        }

        return $query
            ->limit($limit)
            ->get()
            ->sortBy(fn (ProductVariant $v) => $this->variantMatchScore($v, $tokens))
            ->values();
    }

    /**
     * @return array<int, string>
     */
    protected function tokenizeProductQuery(string $keyword): array
    {
        $normalized = preg_replace('/[\s\-–—\/]+/u', ' ', mb_strtolower(trim($keyword)));
        $parts = preg_split('/\s+/u', (string) $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($parts, fn (string $token) => mb_strlen($token) >= 2));
    }

    /**
     * @param  array<int, string>  $tokens
     */
    protected function variantMatchScore(ProductVariant $variant, array $tokens): int
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $variant->product?->name,
            $variant->product?->code,
            $variant->sku,
            $variant->display_name,
        ])));

        $score = 0;

        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                $score++;
            }
        }

        return -$score;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Product>  $query
     */
    protected function applyBranchProductScope($query, string $branchId): void
    {
        $query->whereNull('deleted_at')
            ->where('is_sale_item', true)
            ->where('branch_id', $branchId);
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<int, array{
     *   variant_id: string,
     *   product_id: string,
     *   unit_id: string,
     *   sku: string,
     *   label: string,
     *   unit_price: float,
     *   stock: float
     * }>
     */
    protected function mapVariantResults(Collection $variants, string $branchId, string $priceListId, int $limit): Collection
    {
        return $variants
            ->map(fn (ProductVariant $variant) => $this->mapVariant($variant, $branchId, $priceListId))
            ->filter(fn (?array $row) => $row !== null)
            ->unique('variant_id')
            ->take($limit)
            ->values();
    }

    /**
     * @return array{
     *   variant_id: string,
     *   product_id: string,
     *   unit_id: string,
     *   sku: string,
     *   label: string,
     *   unit_price: float,
     *   stock: float
     * }|null
     */
    public function mapVariant(ProductVariant $variant, string $branchId, string $priceListId): ?array
    {
        if ($variant->product === null) {
            return null;
        }

        $pricing = $this->resolveVariantPricing($variant, $branchId, $priceListId);

        if ($pricing['unit_id'] === null || $pricing['selling_price'] <= 0) {
            return null;
        }

        return [
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'unit_id' => $pricing['unit_id'],
            'sku' => (string) $variant->sku,
            'label' => $variant->display_name,
            'unit_price' => $pricing['selling_price'],
            'stock' => $pricing['stock'],
        ];
    }

    /**
     * @return array{
     *   id: string,
     *   sku: ?string,
     *   display_name: string,
     *   selling_price: float,
     *   stock: int,
     *   unit_id: ?string,
     *   unit_label: ?string
     * }|null
     */
    public function mapVariantForPos(ProductVariant $variant, string $branchId, string $priceListId): ?array
    {
        if ($variant->product === null) {
            return null;
        }

        $pricing = $this->resolveVariantPricing($variant, $branchId, $priceListId);
        $unitLabel = null;
        if ($pricing['unit_id']) {
            $unit = ProductUnit::query()->find($pricing['unit_id'], ['id', 'symbol', 'name']);
            $unitLabel = $unit?->symbol ?: ($unit?->name ?: null);
        }

        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'display_name' => $variant->display_name,
            'selling_price' => $pricing['selling_price'],
            'stock' => (int) $pricing['stock'],
            'unit_id' => $pricing['unit_id'],
            'unit_label' => $unitLabel,
        ];
    }

    /**
     * @return array{unit_id: ?string, selling_price: float, stock: float}
     */
    protected function resolveVariantPricing(ProductVariant $variant, string $branchId, string $priceListId): array
    {
        $warehouseId = optional(WmsContext::defaultWarehouse($branchId))->id;
        $product = $variant->product;

        $unitId = $product?->default_unit_id;

        $priceRow = $unitId
            ? $this->findVariantPriceRow($variant->id, $branchId, $priceListId, $unitId)
            : null;

        if (! $priceRow) {
            $priceRow = $this->findVariantPriceRow($variant->id, $branchId, $priceListId, null);
            if ($priceRow) {
                $unitId = $priceRow->unit_id;
            }
        }

        if (! $unitId) {
            $unitId = ProductVariantStock::query()
                ->where('product_variant_id', $variant->id)
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId), fn ($q) => $q->where('branch_id', $branchId))
                ->whereNull('deleted_at')
                ->value('unit_id');
        }

        if (! $unitId) {
            return ['unit_id' => null, 'selling_price' => 0.0, 'stock' => 0.0];
        }

        if (! $priceRow) {
            $priceRow = $this->findVariantPriceRow($variant->id, $branchId, $priceListId, $unitId);
        }

        $stockRow = ProductVariantStock::query()
            ->where('product_variant_id', $variant->id)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId), fn ($q) => $q->where('branch_id', $branchId))
            ->where('unit_id', $unitId)
            ->whereNull('deleted_at')
            ->first();

        return [
            'unit_id' => $unitId,
            'selling_price' => (float) ($priceRow?->selling_price ?? 0),
            'stock' => (float) ($stockRow?->quantity ?? 0),
        ];
    }

    protected function findVariantPriceRow(string $variantId, string $branchId, string $priceListId, ?string $unitId): ?ProductVariantPrice
    {
        return ProductVariantPrice::query()
            ->where('variant_id', $variantId)
            ->where('branch_id', $branchId)
            ->where('price_list_id', $priceListId)
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->whereNull('deleted_at')
            ->first();
    }
}
