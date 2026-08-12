<?php

namespace App\Services\Promotion;

use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Warehouse;
use App\Services\Distribution\MarketingAllocationService;
use Illuminate\Support\Collection;

class PromotionEngineService
{
    /**
     * Expand cart lines with free promo reward lines (price 0).
     *
     * @param  list<array{product_id: string, product_variant_id: string, unit_id: string, quantity: float, unit_price: float, discount_type?: string, discount_value?: float, discount_amount?: float, subtotal: float}>  $itemsData
     * @return list<array<string, mixed>>
     */
    public static function applyToCartLines(
        array $itemsData,
        ?string $companyId = null,
        ?string $branchId = null,
        ?string $orderWarehouseId = null
    ): array {
        $promotions = Promotion::query()
            ->activeNow()
            ->productType()
            ->where('trigger_level', 'line')
            ->when($companyId, fn ($q) => $q->where(function ($qq) use ($companyId) {
                $qq->whereNull('company_id')->orWhere('company_id', $companyId);
            }))
            ->orderBy('priority')
            ->orderBy('code')
            ->get();

        if ($promotions->isEmpty()) {
            return $itemsData;
        }

        $result = [];
        $lineIndex = 0;

        foreach ($itemsData as $line) {
            $line['_line_key'] = $lineIndex++;
            $result[] = $line;

            if (! empty($line['is_promo_free'])) {
                continue;
            }

            $matched = self::matchBestPromotion($promotions, $line);
            if (! $matched) {
                continue;
            }

            $freeQty = self::computeFreeQty($matched, (float) $line['quantity']);
            if ($freeQty <= 0) {
                continue;
            }

            $reward = self::resolveRewardVariant($matched, $line);
            if (! $reward) {
                continue;
            }

            $warehouseId = self::resolveFreeWarehouseId(
                $matched->free_warehouse_type ?: 'MARKETING',
                $companyId,
                $branchId,
                $orderWarehouseId
            );

            $unitId = $matched->get_unit_id
                ?: $reward->product?->default_unit_id
                ?: $line['unit_id'];

            $result[] = [
                'product_id' => $reward->product_id,
                'product_variant_id' => $reward->id,
                'unit_id' => $unitId,
                'quantity' => $freeQty,
                'unit_price' => 0,
                'discount_type' => 'percent',
                'discount_value' => 0,
                'discount_amount' => 0,
                'subtotal' => 0,
                'is_promo_free' => true,
                'promotion_id' => $matched->id,
                'source_warehouse_id' => $warehouseId,
                'promo_code' => $matched->code,
                'notes' => 'Promo '.$matched->code.': '.$matched->name,
                '_parent_line_key' => $line['_line_key'],
            ];
        }

        return $result;
    }

    /**
     * @param  Collection<int, Promotion>  $promotions
     * @param  array<string, mixed>  $line
     */
    protected static function matchBestPromotion(Collection $promotions, array $line): ?Promotion
    {
        $variantId = $line['product_variant_id'] ?? null;
        $productId = $line['product_id'] ?? null;
        $qty = (float) ($line['quantity'] ?? 0);

        foreach ($promotions as $promo) {
            if ($qty + 1e-9 < (float) $promo->buy_min_qty) {
                continue;
            }

            if ($promo->buy_variant_id && $promo->buy_variant_id !== $variantId) {
                continue;
            }

            if ($promo->buy_product_id && $promo->buy_product_id !== $productId) {
                continue;
            }

            // Require at least product or variant target to avoid matching everything.
            if (! $promo->buy_product_id && ! $promo->buy_variant_id) {
                continue;
            }

            return $promo;
        }

        return null;
    }

    protected static function computeFreeQty(Promotion $promo, float $buyQty): float
    {
        $min = (float) $promo->buy_min_qty;
        if ($min <= 0) {
            return 0;
        }

        $applications = (int) floor($buyQty / $min + 1e-9);
        if ($promo->max_applications_per_line !== null) {
            $applications = min($applications, (int) $promo->max_applications_per_line);
        }

        return $applications * (float) $promo->get_qty;
    }

    /**
     * @param  array<string, mixed>  $buyLine
     */
    protected static function resolveRewardVariant(Promotion $promo, array $buyLine): ?ProductVariant
    {
        if ($promo->get_product_mode === 'specific') {
            if ($promo->get_variant_id) {
                return ProductVariant::with('product')->find($promo->get_variant_id);
            }
            if ($promo->get_product_id) {
                return ProductVariant::with('product')
                    ->where('product_id', $promo->get_product_id)
                    ->whereNull('deleted_at')
                    ->orderBy('created_at')
                    ->first();
            }

            return null;
        }

        // same as buy line
        return ProductVariant::with('product')->find($buyLine['product_variant_id'] ?? null);
    }

    public static function resolveFreeWarehouseId(
        string $type,
        ?string $companyId,
        ?string $branchId,
        ?string $orderWarehouseId
    ): ?string {
        $type = strtoupper($type);

        if ($type === 'ORDER') {
            return $orderWarehouseId;
        }

        if ($type === 'MARKETING') {
            return MarketingAllocationService::resolveMarketingWarehouse($companyId, $branchId)?->id
                ?? $orderWarehouseId;
        }

        if ($type === 'FG') {
            return MarketingAllocationService::resolveProductWarehouse($companyId, $branchId)?->id
                ?? $orderWarehouseId;
        }

        return Warehouse::query()
            ->where('warehouse_type_code', $type)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->value('id') ?: $orderWarehouseId;
    }
}
