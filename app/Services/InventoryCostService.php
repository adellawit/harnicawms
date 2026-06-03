<?php

namespace App\Services;

use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantStock;
use App\Models\ProductPurchaseOrderItem;
use Illuminate\Support\Facades\DB;

class InventoryCostService
{
    /**
     * Hitung average cost baru berdasarkan stok lama & harga lama vs penerimaan baru.
     */
    public static function calculateAverageCost(
        float $oldQty,
        float $oldCost,
        float $newQty,
        float $newCost
    ): float {
        if ($newQty <= 0) {
            return $oldCost;
        }

        // Jika belum ada stok lama, HPP = harga baru
        if ($oldQty <= 0) {
            return $newCost;
        }

        $totalOld = $oldQty * $oldCost;
        $totalNew = $newQty * $newCost;

        $newAvg = ($totalOld + $totalNew) / ($oldQty + $newQty);

        return round($newAvg, 4);
    }

    /**
     * Update HPP (purchase_price) average untuk variant/unit/branch
     * berdasarkan penerimaan PO.
     *
     * - Jika ada variant_id → update di ProductVariantPrice.
     * - Jika tidak ada variant_id → update di ProductPrice (per product/unit/branch).
     */
    public static function updateAverageCostForPurchaseReceive(
        ProductPurchaseOrderItem $poItem,
        float $qtyReceived,
        string $branchId,
        string $companyId,
        string $userId
    ): void {
        if ($qtyReceived <= 0) {
            return;
        }

        $unitCost = (float) $poItem->unit_price;
        if ($unitCost <= 0) {
            return;
        }

        // Jalankan dalam transaksi luar (caller sudah dalam DB::transaction)
        if ($poItem->variant_id) {
            self::updateVariantAverageCost(
                $poItem->variant_id,
                $poItem->product_id,
                $poItem->unit_id,
                $branchId,
                $companyId,
                $userId,
                $qtyReceived,
                $unitCost
            );
        } else {
            self::updateProductAverageCost(
                $poItem->product_id,
                $poItem->unit_id,
                $branchId,
                $companyId,
                $userId,
                $qtyReceived,
                $unitCost
            );
        }
    }

    protected static function updateVariantAverageCost(
        string $variantId,
        string $productId,
        string $unitId,
        string $branchId,
        string $companyId,
        string $userId,
        float $qtyReceived,
        float $unitCost
    ): void {
        // Ambil stok existing untuk variant/unit/branch
        $stockQty = (float) ProductVariantStock::where('product_variant_id', $variantId)
            ->where('branch_id', $branchId)
            ->where('unit_id', $unitId)
            ->whereNull('deleted_at')
            ->sum('quantity');

        // Ambil harga existing (branch/unit) kalau ada, fallback ke variant->purchase_price
        $variant = ProductVariant::find($variantId);

        $price = ProductVariantPrice::withTrashed()
            ->where('variant_id', $variantId)
            ->where('branch_id', $branchId)
            ->where('unit_id', $unitId)
            ->first();

        $oldCost = $price?->purchase_price !== null
            ? (float) $price->purchase_price
            : (float) ($variant?->purchase_price ?? 0);

        $newAvg = self::calculateAverageCost($stockQty, $oldCost, $qtyReceived, $unitCost);

        if ($price) {
            if ($price->trashed()) {
                $price->restore();
            }

            $price->update([
                'company_id' => $companyId,
                'purchase_price' => $newAvg,
                'updated_by' => $userId,
                'deleted_by' => null,
            ]);
        } else {
            ProductVariantPrice::create([
                'variant_id' => $variantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'unit_id' => $unitId,
                'purchase_price' => $newAvg,
                'selling_price' => 0,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    protected static function updateProductAverageCost(
        string $productId,
        string $unitId,
        string $branchId,
        string $companyId,
        string $userId,
        float $qtyReceived,
        float $unitCost
    ): void {
        // Stok existing di level product/unit/branch dari variant stock
        $stockQty = (float) ProductVariantStock::where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('unit_id', $unitId)
            ->whereNull('deleted_at')
            ->sum('quantity');

        $price = ProductPrice::withTrashed()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('unit_id', $unitId)
            ->first();

        $oldCost = $price?->purchase_price !== null
            ? (float) $price->purchase_price
            : 0.0;

        $newAvg = self::calculateAverageCost($stockQty, $oldCost, $qtyReceived, $unitCost);

        if ($price) {
            if ($price->trashed()) {
                $price->restore();
            }

            $price->update([
                'company_id' => $companyId,
                'purchase_price' => $newAvg,
                'updated_by' => $userId,
                'deleted_by' => null,
            ]);
        } else {
            ProductPrice::create([
                'product_id' => $productId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'unit_id' => $unitId,
                'purchase_price' => $newAvg,
                'selling_price' => 0,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }
}

