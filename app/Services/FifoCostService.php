<?php

namespace App\Services;

use App\Models\ProductCostHistory;
use App\Models\ProductCostLayer;
use Illuminate\Support\Carbon;

/**
 * FIFO costing engine.
 *
 * - addLayer(): setiap barang masuk membuat satu layer biaya (qty + unit_cost).
 * - consume(): barang keluar mengkonsumsi layer tertua dulu (First In First Out)
 *   dan mengembalikan total HPP (COGS) dari layer yang terpakai.
 *
 * Semua method diasumsikan dipanggil di dalam DB::transaction oleh caller.
 */
class FifoCostService
{
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
        ?string $date = null
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
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        self::recordHistory($productId, $branchId, $unitId, $unitCost, $sourceType, $sourceId, $userId, $effectiveDate);

        return $layer;
    }

    /**
     * Konsumsi qty secara FIFO dari layer tertua.
     *
     * @return array{total_cost: float, unit_cost: float, consumed: float}
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

        $layers = ProductCostLayer::query()
            ->where('product_variant_id', $variantId)
            ->where('branch_id', $branchId)
            ->where('quantity_remaining', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('effective_date')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        $lastUnitCost = 0.0;

        foreach ($layers as $layer) {
            if ($remainingToConsume <= 0) {
                break;
            }

            $available = (float) $layer->quantity_remaining;
            $take = min($available, $remainingToConsume);

            $totalCost += $take * (float) $layer->unit_cost;
            $lastUnitCost = (float) $layer->unit_cost;

            $layer->quantity_remaining = $available - $take;
            $layer->updated_by = $userId;
            $layer->save();

            $remainingToConsume -= $take;
        }

        // Jika stok layer tidak cukup (mis. data awal belum lengkap), gunakan
        // unit_cost terakhir yang diketahui untuk sisa agar HPP tetap wajar.
        if ($remainingToConsume > 0) {
            $fallbackCost = $lastUnitCost > 0 ? $lastUnitCost : self::currentUnitCost($variantId, $branchId);
            $totalCost += $remainingToConsume * $fallbackCost;
        }

        $consumed = $quantity;
        $unitCost = $consumed > 0 ? round($totalCost / $consumed, 4) : 0.0;

        return [
            'total_cost' => round($totalCost, 4),
            'unit_cost' => $unitCost,
            'consumed' => $consumed,
        ];
    }

    /**
     * HPP berjalan = unit_cost dari layer tertua yang masih tersisa.
     */
    public static function currentUnitCost(?string $variantId, string $branchId): float
    {
        $layer = ProductCostLayer::query()
            ->where('product_variant_id', $variantId)
            ->where('branch_id', $branchId)
            ->where('quantity_remaining', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('effective_date')
            ->orderBy('created_at')
            ->first();

        return $layer ? (float) $layer->unit_cost : 0.0;
    }

    /**
     * Nilai persediaan = Σ(quantity_remaining * unit_cost).
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
