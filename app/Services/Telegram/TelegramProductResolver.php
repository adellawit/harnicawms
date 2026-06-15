<?php

namespace App\Services\Telegram;

use App\Models\Product;
use App\Models\ProductPriceList;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantStock;
use App\Services\Product\ProductSearchService;
use Illuminate\Support\Collection;

class TelegramProductResolver
{
    public function __construct(
        protected ProductSearchService $productSearch,
    ) {}

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
        return $this->productSearch->search($query, $branchId, $companyId, $priceListId, $limit);
    }

    public function resolveDefaultPriceListId(string $branchId, ?string $companyId): string
    {
        $priceList = ProductPriceList::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->forBusinessContext($companyId, $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();

        if ($priceList !== null) {
            return $priceList->id;
        }

        $fallback = ProductPriceList::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('code', 'REGULER')
            ->value('id');

        if ($fallback === null) {
            throw new \RuntimeException('Price list default tidak ditemukan.');
        }

        return $fallback;
    }

    /**
     * @return array<int, string>
     */
    public function popularProductContext(string $branchId, int $limit = 30): array
    {
        $products = Product::query()
            ->saleItems()
            ->whereNull('deleted_at')
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->limit($limit)
            ->get(['code', 'name']);

        return $products
            ->map(fn (Product $p) => '- '.$p->code.': '.$p->name)
            ->all();
    }

    public function resolveDefaultPriceListName(string $branchId, ?string $companyId): string
    {
        $priceList = ProductPriceList::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->forBusinessContext($companyId, $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first(['name', 'code']);

        if ($priceList !== null) {
            return (string) ($priceList->name ?: $priceList->code);
        }

        return 'REGULER';
    }

    /**
     * @return Collection<int, array{label: string, unit_price: float, sku: string}>
     */
    public function branchMenuItems(
        string $branchId,
        ?string $companyId,
        ?string $keyword = null,
        ?int $limit = null,
    ): Collection {
        $limit ??= (int) config('telegram.menu_item_limit', 100);
        $priceListId = $this->resolveDefaultPriceListId($branchId, $companyId);
        $keyword = trim((string) $keyword);

        $query = ProductVariant::query()
            ->with(['product', 'variantAttributes.attributeValue'])
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereHas('product', function ($p) use ($branchId) {
                $p->whereNull('deleted_at')
                    ->where('is_sale_item', true)
                    ->where('branch_id', $branchId);
            });

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('sku', 'ilike', '%'.$keyword.'%')
                    ->orWhere('barcode', 'ilike', '%'.$keyword.'%')
                    ->orWhereHas('product', function ($p) use ($keyword) {
                        $p->whereNull('deleted_at')
                            ->where('is_sale_item', true)
                            ->where(function ($nameQ) use ($keyword) {
                                $nameQ->where('name', 'ilike', '%'.$keyword.'%')
                                    ->orWhere('code', 'ilike', '%'.$keyword.'%');
                            });
                    });
            });
        }

        $variants = $query
            ->limit($limit * 3)
            ->get()
            ->sortBy([
                fn (ProductVariant $v) => mb_strtolower($v->product?->name ?? ''),
                fn (ProductVariant $v) => $v->sort_order,
            ])
            ->values();

        return $variants
            ->map(fn (ProductVariant $variant) => $this->productSearch->mapVariant($variant, $branchId, $priceListId))
            ->filter(fn (?array $row) => $row !== null)
            ->unique('variant_id')
            ->take($limit)
            ->map(fn (array $row) => [
                'label' => $row['label'],
                'unit_price' => $row['unit_price'],
                'sku' => $row['sku'],
            ])
            ->values();
    }

    public function formatMenuLine(string $label, float $unitPrice): string
    {
        return '• '.e($label).' — Rp '.number_format($unitPrice, 0, ',', '.');
    }
}
