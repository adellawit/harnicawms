<?php

namespace App\Services;

use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\Warehouse;
use App\Services\UnitConversionService;
use Illuminate\Support\Facades\DB;

/**
 * Penerap mutasi stok terpusat untuk modul Produksi & Distribusi.
 *
 * Setiap mutasi:
 *  - meng-update product_variant_stock (qty per varian per cabang),
 *  - mencatat product_stock_movements (audit, polymorphic reference),
 *  - mengelola layer biaya FIFO (lihat FifoCostService).
 *
 * Diasumsikan dipanggil di dalam DB::transaction oleh caller.
 */
class StockMutationService
{
    /**
     * Barang masuk: tambah stok + buat layer FIFO.
     */
    public static function inbound(
        string $productId,
        ?string $variantId,
        ?string $companyId,
        string $branchId,
        string $unitId,
        float $quantity,
        float $unitCost,
        string $referenceType,
        ?string $referenceId,
        ?string $userId = null,
        ?string $notes = null,
        ?string $date = null,
        ?string $expiryDate = null,
        ?string $warehouseId = null
    ): void {
        if ($quantity <= 0) {
            return;
        }

        [$warehouseId, $operationalBranchId] = self::resolveWarehouseContext($branchId, $warehouseId);

        $stock = self::lockStock($variantId, $productId, $operationalBranchId, $warehouseId, $unitId, $companyId, $userId);
        $before = (float) $stock->quantity;
        $after = $before + $quantity;
        $stock->quantity = $after;
        $stock->updated_by = $userId;
        $stock->save();

        self::recordMovement($stock, $productId, $variantId, $companyId, $operationalBranchId, $warehouseId, $unitId, 'in', $quantity, $before, $after, $referenceType, $referenceId, $userId, $notes);

        FifoCostService::addLayer($productId, $variantId, $companyId, $operationalBranchId, $unitId, $quantity, $unitCost, $referenceType, $referenceId, $userId, $date, $expiryDate, $warehouseId);
    }

    /**
     * Barang keluar: kurangi stok + konsumsi layer FEFO.
     *
     * @return array{total_cost: float, unit_cost: float, earliest_expiry: ?string}
     */
    public static function outbound(
        string $productId,
        ?string $variantId,
        ?string $companyId,
        string $branchId,
        string $unitId,
        float $quantity,
        string $referenceType,
        ?string $referenceId,
        ?string $userId = null,
        ?string $notes = null,
        ?string $warehouseId = null
    ): array {
        if ($quantity <= 0) {
            return ['total_cost' => 0.0, 'unit_cost' => 0.0, 'earliest_expiry' => null];
        }

        $product = ProductVariant::with(['product.unitConversions'])->find($variantId)?->product;

        [$warehouseId, $operationalBranchId] = self::resolveWarehouseContext($branchId, $warehouseId);

        $stock = self::lockStock($variantId, $productId, $operationalBranchId, $warehouseId, $unitId, $companyId, $userId);
        $before = (float) $stock->quantity;

        $deductInStockUnit = $quantity;
        if ($product && $stock->unit_id !== $unitId) {
            $converted = UnitConversionService::convertQuantity($product, $quantity, $unitId, $stock->unit_id);
            if ($converted !== null) {
                $deductInStockUnit = $converted;
            }
        }

        $after = $before - $deductInStockUnit;

        if ($after < -1e-6) {
            $variant = ProductVariant::with('product')->find($variantId);
            $label = $variant?->display_name ?? $variant?->product?->name ?? 'Produk';

            throw new \RuntimeException(sprintf(
                'Stok %s tidak cukup. Tersedia: %s, diminta: %s.',
                $label,
                rtrim(rtrim(number_format(max($before, 0), 4, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format($deductInStockUnit, 4, '.', ''), '0'), '.')
            ));
        }

        $stock->quantity = $after;
        $stock->updated_by = $userId;
        $stock->save();

        self::recordMovement($stock, $productId, $variantId, $companyId, $operationalBranchId, $warehouseId, $unitId, 'out', $quantity, $before, $after, $referenceType, $referenceId, $userId, $notes);

        $cogs = FifoCostService::consume($variantId, $operationalBranchId, $unitId, $quantity, $userId, $warehouseId);

        return [
            'total_cost' => $cogs['total_cost'],
            'unit_cost' => $cogs['unit_cost'],
            'earliest_expiry' => $cogs['earliest_expiry'],
        ];
    }

    protected static function lockStock(
        ?string $variantId,
        string $productId,
        string $branchId,
        ?string $warehouseId,
        string $unitId,
        ?string $companyId,
        ?string $userId
    ): ProductVariantStock {
        $stock = ProductVariantStock::query()
            ->where('product_variant_id', $variantId)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId), fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            $stock = ProductVariantStock::create([
                'product_variant_id' => $variantId,
                'product_id' => $productId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'unit_id' => $unitId,
                'quantity' => 0,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        return $stock;
    }

    protected static function recordMovement(
        ProductVariantStock $stock,
        string $productId,
        ?string $variantId,
        ?string $companyId,
        string $branchId,
        ?string $warehouseId,
        string $unitId,
        string $type,
        float $quantity,
        float $before,
        float $after,
        string $referenceType,
        ?string $referenceId,
        ?string $userId,
        ?string $notes
    ): void {
        ProductStockMovement::create([
            'product_variant_stock_id' => $stock->id,
            'product_variant_id' => $variantId,
            'product_id' => $productId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'unit_id' => $unitId,
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * @return array{0: ?string, 1: string} [warehouse_id, operational_branch_id]
     */
    protected static function resolveWarehouseContext(string $branchId, ?string $warehouseId): array
    {
        $warehouse = null;

        if ($warehouseId) {
            $warehouse = Warehouse::query()->whereKey($warehouseId)->first();
        }

        if (! $warehouse) {
            $warehouse = Warehouse::query()
                ->where('id', $branchId)
                ->orWhere('legacy_business_unit_id', $branchId)
                ->first();
        }

        if (! $warehouse) {
            $warehouse = Warehouse::defaultForBranch($branchId);
        }

        if (! $warehouse) {
            return [null, $branchId];
        }

        return [$warehouse->id, $warehouse->branch_id ?: $branchId];
    }
}
