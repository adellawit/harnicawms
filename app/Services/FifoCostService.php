<?php

namespace App\Services;

use App\Models\ProductCostHistory;
use App\Models\ProductCostLayer;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;

/**
 * FEFO costing engine (First Expired First Out) dengan fallback FIFO.
 *
 * Cocok untuk produk herbal yang punya tanggal kadaluarsa: konsumsi mengambil
 * layer dengan expiry_date TERDEKAT lebih dulu; layer tanpa expiry diperlakukan
 * paling akhir (nulls last) lalu diurutkan FIFO (effective_date, created_at).
 *
 * - addLayer(): setiap barang masuk membuat satu layer biaya (qty + unit_cost + expiry).
 * - consume(): barang keluar mengkonsumsi layer sesuai urutan FEFO dan mengembalikan
 *   total HPP (COGS) + expiry terdekat dari layer yang terpakai.
 *
 * Semua method diasumsikan dipanggil di dalam DB::transaction oleh caller.
 */
class FifoCostService
{
    /**
     * Terapkan urutan FEFO (expiry terdekat dulu, NULLS LAST) lalu FIFO.
     */
    protected static function fefoOrder($query)
    {
        return $query
            ->orderByRaw('expiry_date ASC NULLS LAST')
            ->orderBy('effective_date')
            ->orderBy('created_at');
    }

    /**
     * Buat layer biaya baru (barang masuk) + catat histori HPP.
     */
    public static function addLayer(
        string $productId,
        ?string $variantId,
        ?string $companyId,
        string $branchId,
        string $unitId,
        float $quantity,
        float $unitCost,
        string $sourceType,
        ?string $sourceId,
        ?string $userId = null,
        ?string $date = null,
        ?string $expiryDate = null
    ): ProductCostLayer {
        $effectiveDate = $date ?: Carbon::now()->toDateString();

        $layer = ProductCostLayer::create([
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'unit_id' => $unitId,
            'quantity' => $quantity,
            'quantity_remaining' => $quantity,
            'unit_cost' => round($unitCost, 4),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'effective_date' => $effectiveDate,
            'expiry_date' => $expiryDate,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        self::recordHistory($productId, $branchId, $unitId, $unitCost, $sourceType, $sourceId, $userId, $effectiveDate);

        return $layer;
    }

    /**
     * Konsumsi qty secara FEFO (expiry terdekat dulu, fallback FIFO).
     * Mendukung satuan konsumsi berbeda dari satuan layer (konversi via product_unit_conversions).
     *
     * @return array{total_cost: float, unit_cost: float, consumed: float, earliest_expiry: ?string}
     */
    public static function consume(
        ?string $variantId,
        string $branchId,
        string $unitId,
        float $quantity,
        ?string $userId = null
    ): array {
        $remainingToConsume = $quantity;
        $totalCost = 0.0;
        $product = self::resolveProduct($variantId);

        $layers = self::fefoOrder(
            ProductCostLayer::query()
                ->where('product_variant_id', $variantId)
                ->where('branch_id', $branchId)
                ->where('quantity_remaining', '>', 0)
                ->whereNull('deleted_at')
        )->lockForUpdate()->get();

        $lastUnitCostInConsumeUnit = 0.0;
        $earliestExpiry = null;

        foreach ($layers as $layer) {
            if ($remainingToConsume <= 0) {
                break;
            }

            $layerUnitId = $layer->unit_id;
            $availableInLayerUnit = (float) $layer->quantity_remaining;

            $availableInConsumeUnit = self::toConsumeUnit($product, $availableInLayerUnit, $layerUnitId, $unitId);
            if ($availableInConsumeUnit === null || $availableInConsumeUnit <= 0) {
                continue;
            }

            $takeInConsumeUnit = min($availableInConsumeUnit, $remainingToConsume);
            $takeInLayerUnit = self::fromConsumeUnit($product, $takeInConsumeUnit, $unitId, $layerUnitId);
            if ($takeInLayerUnit === null || $takeInLayerUnit <= 0) {
                continue;
            }

            $costPerConsumeUnit = self::unitCostInConsumeUnit($product, (float) $layer->unit_cost, $layerUnitId, $unitId);
            $totalCost += $takeInConsumeUnit * $costPerConsumeUnit;
            $lastUnitCostInConsumeUnit = $costPerConsumeUnit;

            if ($layer->expiry_date) {
                $exp = $layer->expiry_date->toDateString();
                if ($earliestExpiry === null || $exp < $earliestExpiry) {
                    $earliestExpiry = $exp;
                }
            }

            $layer->quantity_remaining = $availableInLayerUnit - $takeInLayerUnit;
            $layer->updated_by = $userId;
            $layer->save();

            $remainingToConsume -= $takeInConsumeUnit;
        }

        if ($remainingToConsume > 0) {
            $fallbackCost = $lastUnitCostInConsumeUnit > 0
                ? $lastUnitCostInConsumeUnit
                : self::currentUnitCost($variantId, $branchId, $unitId);
            $totalCost += $remainingToConsume * $fallbackCost;
        }

        $consumed = $quantity;
        $unitCost = $consumed > 0 ? round($totalCost / $consumed, 4) : 0.0;

        return [
            'total_cost' => round($totalCost, 4),
            'unit_cost' => $unitCost,
            'consumed' => $consumed,
            'earliest_expiry' => $earliestExpiry,
        ];
    }

    /**
     * HPP berjalan = unit_cost layer FEFO terdepan, dikonversi ke $targetUnitId bila perlu.
     */
    public static function currentUnitCost(?string $variantId, string $branchId, ?string $targetUnitId = null): float
    {
        $layer = self::fefoOrder(
            ProductCostLayer::query()
                ->where('product_variant_id', $variantId)
                ->where('branch_id', $branchId)
                ->where('quantity_remaining', '>', 0)
                ->whereNull('deleted_at')
        )->first();

        if (! $layer) {
            return 0.0;
        }

        $cost = (float) $layer->unit_cost;
        $targetUnitId = $targetUnitId ?: $layer->unit_id;

        if ($targetUnitId === $layer->unit_id) {
            return $cost;
        }

        $product = self::resolveProduct($variantId);

        return self::unitCostInConsumeUnit($product, $cost, $layer->unit_id, $targetUnitId);
    }

    /**
     * Nilai persediaan = Σ(quantity_remaining * unit_cost) per layer (dalam satuan layer).
     */
    public static function inventoryValue(?string $variantId, string $branchId): float
    {
        return (float) ProductCostLayer::query()
            ->where('product_variant_id', $variantId)
            ->where('branch_id', $branchId)
            ->where('quantity_remaining', '>', 0)
            ->whereNull('deleted_at')
            ->get()
            ->sum(fn ($l) => (float) $l->quantity_remaining * (float) $l->unit_cost);
    }

    protected static function resolveProduct(?string $variantId): ?\App\Models\Product
    {
        if (! $variantId) {
            return null;
        }

        return ProductVariant::with(['product.unitConversions'])
            ->find($variantId)
            ?->product;
    }

    protected static function toConsumeUnit(?\App\Models\Product $product, float $qty, string $fromUnitId, string $toUnitId): ?float
    {
        if ($fromUnitId === $toUnitId) {
            return $qty;
        }

        if (! $product) {
            return null;
        }

        return UnitConversionService::convertQuantity($product, $qty, $fromUnitId, $toUnitId);
    }

    protected static function fromConsumeUnit(?\App\Models\Product $product, float $qty, string $fromUnitId, string $toUnitId): ?float
    {
        if ($fromUnitId === $toUnitId) {
            return $qty;
        }

        if (! $product) {
            return null;
        }

        return UnitConversionService::convertQuantity($product, $qty, $fromUnitId, $toUnitId);
    }

    protected static function unitCostInConsumeUnit(?\App\Models\Product $product, float $unitCost, string $layerUnitId, string $consumeUnitId): float
    {
        if ($layerUnitId === $consumeUnitId) {
            return $unitCost;
        }

        if ($product) {
            $converted = UnitConversionService::convertUnitCost($product, $unitCost, $layerUnitId, $consumeUnitId);
            if ($converted !== null) {
                return $converted;
            }
        }

        return $unitCost;
    }

    protected static function recordHistory(
        string $productId,
        string $branchId,
        string $unitId,
        float $cost,
        string $sourceType,
        ?string $sourceId,
        ?string $userId,
        string $effectiveDate
    ): void {
        ProductCostHistory::create([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'unit_id' => $unitId,
            'cost' => round($cost, 4),
            'cost_type' => 'fifo',
            'effective_date' => $effectiveDate,
            'reference_type' => $sourceType,
            'reference_id' => $sourceId,
            'created_by' => $userId,
        ]);
    }
}
