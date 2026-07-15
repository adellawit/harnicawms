<?php

namespace App\Services\Manufacturing;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderMaterial;
use App\Models\ProductionOrderOutput;
use App\Services\StockAvailabilityService;
use App\Services\StockMutationService;
use App\Support\ProductionQuantityNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Logika penyelesaian Production Order.
 *
 * Saat submit (create):
 *  1. Konsumsi bahan baku sesuai BOM × planned_qty (FIFO).
 *
 * Saat receiving:
 *  1. FG masuk stok; HPP = (total biaya bahan + overhead) / qty aktual.
 *  2. Bahan baku tidak dipotong ulang jika sudah dikonsumsi saat submit.
 */
class ProductionService
{
    /**
     * Potong stok bahan baku sesuai BOM × qty output (satuan BOM).
     *
     * @return float total material cost
     */
    public static function consumeMaterials(
        ProductionOrder $order,
        float $outputQty,
        ?string $userId = null,
        ?float $expectedOutputQty = null
    ): float {
        if ($outputQty <= 0) {
            throw new \RuntimeException('Production quantity must be greater than 0.');
        }

        $bom = $order->bom()->with(['items.componentVariant.product', 'items.componentProduct'])->first();
        if (! $bom) {
            throw new \RuntimeException('Production order has no BOM recipe.');
        }

        $outputPerBatch = (float) ($bom->output_quantity ?: 1);
        $scale = $outputPerBatch > 0 ? $outputQty / $outputPerBatch : $outputQty;
        $expectedScale = $expectedOutputQty !== null && $outputPerBatch > 0
            ? $expectedOutputQty / $outputPerBatch
            : $scale;

        $branchId = $order->branch_id ?: $order->outputWarehouse?->branch_id ?: $order->sourceWarehouse?->branch_id;
        $materialWarehouseId = $order->source_warehouse_id;
        $totalMaterialCost = 0.0;

        foreach ($bom->items as $item) {
            $isSmallestUnit = self::isComponentSmallestUnit($item);
            $qtyNeeded = ProductionQuantityNormalizer::snapDisplayQty((float) $item->quantity * $scale, $isSmallestUnit);
            if ($qtyNeeded <= 0) {
                continue;
            }

            $label = $item->componentVariant?->display_name
                ?? $item->componentProduct?->name
                ?? 'Raw material';

            StockAvailabilityService::assertSufficient(
                $item->component_variant_id,
                $branchId ?: $materialWarehouseId,
                $item->unit_id,
                $qtyNeeded,
                $label,
                $materialWarehouseId
            );
        }

        $order->materials()->delete();

        foreach ($bom->items as $item) {
            $isSmallestUnit = self::isComponentSmallestUnit($item);
            $expectedQty = ProductionQuantityNormalizer::snapDisplayQty((float) $item->quantity * $expectedScale, $isSmallestUnit);
            $qtyNeeded = ProductionQuantityNormalizer::snapDisplayQty((float) $item->quantity * $scale, $isSmallestUnit);
            if ($qtyNeeded <= 0) {
                continue;
            }

            $result = StockMutationService::outbound(
                $item->component_product_id,
                $item->component_variant_id,
                $order->company_id,
                $branchId ?: $materialWarehouseId,
                $item->unit_id,
                $qtyNeeded,
                'ProductionConsume',
                $order->id,
                $userId,
                'Material consumption for production ' . $order->order_number,
                $materialWarehouseId
            );

            $cogs = $result['total_cost'];
            $unitCost = $qtyNeeded > 0 ? round($cogs / $qtyNeeded, 4) : 0.0;
            $totalMaterialCost += $cogs;

            ProductionOrderMaterial::create([
                'production_order_id' => $order->id,
                'component_product_id' => $item->component_product_id,
                'component_variant_id' => $item->component_variant_id,
                'unit_id' => $item->unit_id,
                'qty_consumed' => $qtyNeeded,
                'expected_qty' => $expectedQty,
                'unit_cost' => $unitCost,
                'total_cost' => round($cogs, 4),
            ]);
        }

        $order->update([
            'total_material_cost' => round($totalMaterialCost, 4),
            'updated_by' => $userId,
        ]);

        return round($totalMaterialCost, 4);
    }

    /**
     * Kembalikan stok bahan baku yang sudah dikonsumsi (mis. saat hapus/edit draft).
     */
    public static function reverseMaterials(ProductionOrder $order, ?string $userId = null): void
    {
        $order->loadMissing('materials');

        if ($order->materials->isEmpty()) {
            return;
        }

        $branchId = $order->branch_id ?: $order->outputWarehouse?->branch_id ?: $order->sourceWarehouse?->branch_id;
        $materialWarehouseId = $order->source_warehouse_id;

        foreach ($order->materials as $material) {
            $qty = (float) $material->qty_consumed;
            if ($qty <= 0) {
                continue;
            }

            StockMutationService::inbound(
                $material->component_product_id,
                $material->component_variant_id,
                $order->company_id,
                $branchId ?: $materialWarehouseId,
                $material->unit_id,
                $qty,
                (float) $material->unit_cost,
                'ProductionConsumeReverse',
                $order->id,
                $userId,
                'Reverse material consumption for production ' . $order->order_number,
                optional($order->production_date)->toDateString(),
                null,
                $materialWarehouseId
            );
        }

        $order->materials()->delete();
        $order->update([
            'total_material_cost' => 0,
            'updated_by' => $userId,
        ]);
    }

    public static function receive(ProductionOrder $order, float $actualQty, ?string $userId = null): ProductionOrder
    {
        if ($order->status !== 'pending_receiving') {
            throw new \RuntimeException('Production order must be in Pending Receiving status before it can be received.');
        }

        if ($actualQty <= 0) {
            throw new \RuntimeException('Actual production quantity must be greater than 0.');
        }

        return DB::transaction(function () use ($order, $actualQty, $userId) {
            $bom = $order->bom()->with(['items.componentVariant.product', 'items.componentProduct'])->first();
            $plannedQty = (float) $order->planned_qty;
            $materialsAlreadyConsumed = $order->materials()->exists();

            $order->outputs()->delete();

            $branchId = $order->branch_id ?: $order->outputWarehouse?->branch_id ?: $order->sourceWarehouse?->branch_id;
            $outputWarehouseId = $order->output_warehouse_id ?: $order->branch_id;

            if ($materialsAlreadyConsumed) {
                $order->loadMissing('materials');
                $totalMaterialCost = (float) $order->total_material_cost;
            } elseif ($bom) {
                $totalMaterialCost = self::consumeMaterials(
                    $order,
                    $actualQty,
                    $userId,
                    $plannedQty
                );
            } else {
                $totalMaterialCost = 0.0;
            }

            $overhead = (float) $order->overhead_cost;
            $totalCost = $totalMaterialCost + $overhead;
            $outputUnitCost = $actualQty > 0 ? round($totalCost / $actualQty, 4) : 0.0;

            StockMutationService::inbound(
                $order->product_id,
                $order->product_variant_id,
                $order->company_id,
                $branchId ?: $outputWarehouseId,
                $order->output_unit_id,
                $actualQty,
                $outputUnitCost,
                'ProductionOutput',
                $order->id,
                $userId,
                'Production output ' . $order->order_number,
                optional($order->production_date)->toDateString(),
                optional($order->output_expiry_date)->toDateString(),
                $outputWarehouseId
            );

            ProductionOrderOutput::create([
                'production_order_id' => $order->id,
                'product_id' => $order->product_id,
                'product_variant_id' => $order->product_variant_id,
                'unit_id' => $order->output_unit_id,
                'qty_produced' => $actualQty,
                'unit_cost' => $outputUnitCost,
                'total_cost' => round($totalCost, 4),
            ]);

            $order->update([
                'produced_qty' => $actualQty,
                'total_material_cost' => round($totalMaterialCost, 4),
                'output_unit_cost' => $outputUnitCost,
                'status' => 'completed',
                'updated_by' => $userId,
            ]);

            return $order->fresh(['materials', 'outputs']);
        });
    }

    protected static function isComponentSmallestUnit($item): bool
    {
        $componentProduct = $item->componentVariant?->product ?? $item->componentProduct;

        return $componentProduct && $item->unit_id === $componentProduct->getSmallestUnitId();
    }

    /**
     * Generate nomor produksi: PRD-YYYYMM-XXXX
     */
    public static function generateNumber(): string
    {
        $prefix = 'PRD-' . date('Ym') . '-';
        $last = ProductionOrder::withTrashed()
            ->where('order_number', 'like', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->value('order_number');
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1) + 1) : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
