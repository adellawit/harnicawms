<?php

namespace App\Services\Manufacturing;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderMaterial;
use App\Models\ProductionOrderOutput;
use App\Models\Product;
use App\Services\StockAvailabilityService;
use App\Services\StockMutationService;
use Illuminate\Support\Facades\DB;

/**
 * Logika penyelesaian Production Order.
 *
 * Saat diselesaikan:
 *  1. Konsumsi bahan baku sesuai BOM (× planned_qty) secara FIFO → biaya bahan.
 *  2. HPP produk jadi = (total biaya bahan + overhead) / qty produksi.
 *  3. Produk jadi masuk stok dengan unit_cost = HPP tsb (membuat layer FIFO baru).
 */
class ProductionService
{
    public static function receive(ProductionOrder $order, float $actualQty, ?string $userId = null): ProductionOrder
    {
        if ($order->status !== 'pending_receiving') {
            throw new \RuntimeException('Production order harus berstatus "Menunggu Receiving" sebelum bisa diterima.');
        }

        if ($actualQty <= 0) {
            throw new \RuntimeException('Qty aktual produksi harus lebih dari 0.');
        }

        return DB::transaction(function () use ($order, $actualQty, $userId) {
            $bom = $order->bom()->with(['items.componentVariant.product', 'items.componentProduct'])->first();
            $plannedQty = (float) $order->planned_qty;

            $outputPerBatch = $bom ? (float) ($bom->output_quantity ?: 1) : 1;
            $plannedScale = $outputPerBatch > 0 ? $plannedQty / $outputPerBatch : $plannedQty;
            $actualScale = $outputPerBatch > 0 ? $actualQty / $outputPerBatch : $actualQty;

            $totalMaterialCost = 0.0;

            $order->materials()->delete();
            $order->outputs()->delete();

            $branchId = $order->branch_id ?: $order->outputWarehouse?->branch_id ?: $order->sourceWarehouse?->branch_id;
            $materialWarehouseId = $order->source_warehouse_id;
            $outputWarehouseId = $order->output_warehouse_id ?: $order->branch_id;

            if ($bom) {
                foreach ($bom->items as $item) {
                    $qtyNeeded = (float) $item->quantity * $actualScale;
                    if ($qtyNeeded <= 0) {
                        continue;
                    }

                    $label = $item->componentVariant?->display_name
                        ?? $item->componentProduct?->name
                        ?? 'Bahan baku';

                    StockAvailabilityService::assertSufficient(
                        $item->component_variant_id,
                        $branchId ?: $materialWarehouseId,
                        $item->unit_id,
                        $qtyNeeded,
                        $label,
                        $materialWarehouseId
                    );
                }

                foreach ($bom->items as $item) {
                    $expectedQty = (float) $item->quantity * $plannedScale;
                    $qtyNeeded = (float) $item->quantity * $actualScale;
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
                        'Konsumsi bahan baku produksi ' . $order->order_number,
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
                'Hasil produksi ' . $order->order_number,
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
