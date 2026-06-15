<?php

namespace App\Services\Product;

use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantStock;
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
        $product = $variant->product;

        if ($product === null) {
            return null;
        }

        $defaultUnitId = $product->default_unit_id;

        if (! $defaultUnitId) {
            $defaultUnitId = ProductVariantPrice::query()
                ->where('variant_id', $variant->id)
                ->where('branch_id', $branchId)
                ->where('price_list_id', $priceListId)
                ->whereNull('deleted_at')
                ->value('unit_id')
                ?? ProductVariantStock::query()
                    ->where('product_variant_id', $variant->id)
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at')
                    ->value('unit_id');
        }

        if (! $defaultUnitId) {
            return null;
        }

        $priceRow = ProductVariantPrice::query()
            ->where('variant_id', $variant->id)
            ->where('branch_id', $branchId)
            ->where('price_list_id', $priceListId)
            ->where('unit_id', $defaultUnitId)
            ->whereNull('deleted_at')
            ->first();

        $stockRow = ProductVariantStock::query()
            ->where('product_variant_id', $variant->id)
            ->where('branch_id', $branchId)
            ->where('unit_id', $defaultUnitId)
            ->whereNull('deleted_at')
            ->first();

        $sellingPrice = (float) ($priceRow?->selling_price ?? 0);

        if ($sellingPrice <= 0) {
            return null;
        }

        return [
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'unit_id' => $defaultUnitId,
            'sku' => (string) $variant->sku,
            'label' => $variant->display_name,
            'unit_price' => $sellingPrice,
            'stock' => (float) ($stockRow?->quantity ?? 0),
        ];
    }
}
