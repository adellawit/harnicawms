<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductBatchStock;
use App\Models\ProductVariantStock;
use App\Services\UnitConversionService;
use Illuminate\Support\Facades\DB;

class BatchStockService
{
    /**
     * Pastikan master batch ada, lalu tambah qty ke product_batch_stock per gudang.
     */
    public static function receiveInbound(
        string $productId,
        ?string $companyId,
        string $batchNumber,
        ?string $expiryDate,
        string $warehouseId,
        string $branchId,
        string $unitId,
        float $quantity,
        ?string $userId = null
    ): ProductBatch {
        $batchNumber = trim($batchNumber);
        if ($batchNumber === '' || $quantity <= 0) {
            throw new \InvalidArgumentException('Batch number and positive quantity are required.');
        }

        $batch = ProductBatch::query()
            ->where('product_id', $productId)
            ->where('batch_number', $batchNumber)
            ->first();

        if (! $batch) {
            $batch = ProductBatch::create([
                'product_id' => $productId,
                'company_id' => $companyId,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        } elseif ($expiryDate && ! $batch->expiry_date) {
            $batch->update([
                'expiry_date' => $expiryDate,
                'updated_by' => $userId,
            ]);
        }

        $stockQuery = ProductBatchStock::query()
            ->where('product_batch_id', $batch->id)
            ->where('unit_id', $unitId);

        if ($warehouseId) {
            $stockQuery->where('warehouse_id', $warehouseId);
        } else {
            $stockQuery->where('branch_id', $branchId)->whereNull('warehouse_id');
        }

        $stock = $stockQuery->lockForUpdate()->first();

        if ($stock) {
            $stock->update([
                'quantity' => (float) $stock->quantity + $quantity,
                'updated_by' => $userId,
            ]);
        } else {
            ProductBatchStock::create([
                'product_batch_id' => $batch->id,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'unit_id' => $unitId,
                'quantity' => $quantity,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        return $batch->fresh();
    }

    /**
     * Potong stok batch FEFO (expiry paling dekat dulu; tanpa expiry diurutan terakhir).
     * Qty $quantity dalam satuan $unitId.
     */
    public static function consumeOutbound(
        string $productId,
        string $warehouseId,
        string $unitId,
        float $quantity,
        ?string $userId = null
    ): float {
        if ($quantity <= 0 || $warehouseId === '') {
            return 0.0;
        }

        $product = Product::with(['unitConversions.fromUnit', 'unitConversions.toUnit', 'defaultUnit'])
            ->find($productId);
        if (! $product) {
            return 0.0;
        }

        $smallestUnitId = $product->getSmallestUnitId() ?: $unitId;
        $remainingSmallest = UnitConversionService::convertQuantity($product, $quantity, $unitId, $smallestUnitId);
        if ($remainingSmallest === null) {
            $remainingSmallest = $quantity;
            $smallestUnitId = $unitId;
        }
        $remainingSmallest = (float) $remainingSmallest;
        if ($remainingSmallest <= 0) {
            return 0.0;
        }

        $rows = ProductBatchStock::query()
            ->with(['batch', 'unit'])
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '>', 0)
            ->whereHas('batch', fn ($q) => $q->where('product_id', $productId)->whereNull('deleted_at'))
            ->lockForUpdate()
            ->get()
            ->sortBy(function (ProductBatchStock $row) {
                $expiry = $row->batch?->expiry_date;

                // FEFO: tanggal expiry naik; null di belakang.
                return sprintf(
                    '%s|%s',
                    $expiry ? $expiry->format('Y-m-d') : '9999-12-31',
                    $row->batch?->batch_number ?? ''
                );
            })
            ->values();

        $consumedSmallest = 0.0;

        foreach ($rows as $row) {
            if ($remainingSmallest <= 1e-9) {
                break;
            }

            $rowUnitId = $row->unit_id ?: $unitId;
            $rowQty = (float) $row->quantity;
            if ($rowQty <= 0) {
                continue;
            }

            $rowSmallest = $rowUnitId === $smallestUnitId
                ? $rowQty
                : (UnitConversionService::convertQuantity($product, $rowQty, $rowUnitId, $smallestUnitId) ?? $rowQty);

            if ($rowSmallest <= 0) {
                continue;
            }

            $takeSmallest = min($remainingSmallest, $rowSmallest);
            $takeInRowUnit = $rowUnitId === $smallestUnitId
                ? $takeSmallest
                : (UnitConversionService::convertQuantity($product, $takeSmallest, $smallestUnitId, $rowUnitId) ?? $takeSmallest);

            $newQty = max(0.0, $rowQty - (float) $takeInRowUnit);
            $row->quantity = $newQty;
            $row->updated_by = $userId;
            $row->save();

            $remainingSmallest -= $takeSmallest;
            $consumedSmallest += $takeSmallest;
        }

        return $consumedSmallest;
    }

    /**
     * Samakan total batch stock ke stok varian aktual di gudang (perbaiki data yang sempat mismatch).
     *
     * @return array{before_smallest: float, after_smallest: float, trimmed_smallest: float}
     */
    public static function reconcileWarehouseToVariantStock(
        string $productId,
        string $warehouseId,
        ?string $userId = null
    ): array {
        return DB::transaction(function () use ($productId, $warehouseId, $userId) {
            $product = Product::with(['unitConversions', 'defaultUnit', 'variants'])->findOrFail($productId);
            $smallestUnitId = $product->getSmallestUnitId() ?: $product->default_unit_id;
            if (! $smallestUnitId) {
                return ['before_smallest' => 0.0, 'after_smallest' => 0.0, 'trimmed_smallest' => 0.0];
            }

            $variantIds = $product->variants->pluck('id');
            $variantSmallest = 0.0;
            $stocks = ProductVariantStock::query()
                ->whereIn('product_variant_id', $variantIds)
                ->where('warehouse_id', $warehouseId)
                ->whereNull('deleted_at')
                ->get();

            foreach ($stocks as $stock) {
                $qty = (float) $stock->quantity;
                if ($qty <= 0 || ! $stock->unit_id) {
                    continue;
                }
                $asSmallest = $stock->unit_id === $smallestUnitId
                    ? $qty
                    : (UnitConversionService::convertQuantity($product, $qty, $stock->unit_id, $smallestUnitId) ?? 0.0);
                $variantSmallest += (float) $asSmallest;
            }

            $batchRows = ProductBatchStock::query()
                ->with('unit')
                ->where('warehouse_id', $warehouseId)
                ->where('quantity', '>', 0)
                ->whereHas('batch', fn ($q) => $q->where('product_id', $productId)->whereNull('deleted_at'))
                ->get();

            $batchSmallest = 0.0;
            foreach ($batchRows as $row) {
                $qty = (float) $row->quantity;
                $unitId = $row->unit_id ?: $smallestUnitId;
                $asSmallest = $unitId === $smallestUnitId
                    ? $qty
                    : (UnitConversionService::convertQuantity($product, $qty, $unitId, $smallestUnitId) ?? 0.0);
                $batchSmallest += (float) $asSmallest;
            }

            $excess = max(0.0, round($batchSmallest - $variantSmallest, 6));
            if ($excess > 1e-6) {
                self::consumeOutbound($productId, $warehouseId, $smallestUnitId, $excess, $userId);
            }

            return [
                'before_smallest' => round($batchSmallest, 6),
                'after_smallest' => round(max(0.0, $batchSmallest - $excess), 6),
                'trimmed_smallest' => round($excess, 6),
            ];
        });
    }
}
