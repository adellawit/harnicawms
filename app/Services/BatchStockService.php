<?php

namespace App\Services;

use App\Models\ProductBatch;
use App\Models\ProductBatchStock;

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
}
