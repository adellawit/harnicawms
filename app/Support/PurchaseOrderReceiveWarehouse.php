<?php

namespace App\Support;

use App\Models\ProductPurchaseOrder;
use App\Models\ProductPurchaseOrderItem;
use App\Models\Warehouse;

class PurchaseOrderReceiveWarehouse
{
    public const RAW_MATERIAL_NATURE = 'RAW_MATERIAL';

    public static function hasReceivableRawMaterial(ProductPurchaseOrder $purchase): bool
    {
        if (! $purchase->relationLoaded('items')) {
            $purchase->load(['items.product.nature', 'items.receiveItems']);
        } else {
            foreach ($purchase->items as $item) {
                $item->loadMissing(['product.nature', 'receiveItems']);
            }
        }

        return $purchase->items->contains(
            fn (ProductPurchaseOrderItem $item) => self::remainingQuantity($item) > 0
                && $item->product?->nature?->code === self::RAW_MATERIAL_NATURE
        );
    }

    private static function remainingQuantity(ProductPurchaseOrderItem $item): float
    {
        $received = $item->relationLoaded('receiveItems')
            ? (float) $item->receiveItems->sum('quantity_received')
            : (float) $item->quantity_received;

        return max(0, (float) $item->quantity - $received);
    }

    /**
     * @param  list<array{id: string, label: string}>  $options
     * @param  array<string, true>  $seen
     * @return list<array{id: string, label: string}>
     */
    public static function appendWarehouseOption(array $options, array &$seen, Warehouse $warehouse): array
    {
        if (isset($seen[$warehouse->id])) {
            return $options;
        }

        $warehouse->loadMissing('branch');

        $type = $warehouse->warehouse_type_code ? " ({$warehouse->warehouse_type_code})" : '';
        $branch = $warehouse->branch?->name ? " - {$warehouse->branch->name}" : '';

        $options[] = [
            'id' => $warehouse->id,
            'label' => trim("{$warehouse->code} - {$warehouse->name}{$type}{$branch}"),
        ];
        $seen[$warehouse->id] = true;

        return $options;
    }

    /**
     * @param  list<array{id: string, label: string}>  $warehouses
     */
    public static function defaultWarehouseId(
        ProductPurchaseOrder $purchase,
        array $warehouses,
        ?string $companyId = null,
        ?string $wipWarehouseId = null
    ): ?string {
        $companyId = $companyId ?: $purchase->company_id ?: optional(WmsContext::distributor())->id;
        $allowed = collect($warehouses)->pluck('id')->all();

        if (self::hasReceivableRawMaterial($purchase)) {
            $wipId = $wipWarehouseId ?? optional(WmsContext::wipWarehouse($companyId))->id;
            if ($wipId && in_array($wipId, $allowed, true)) {
                return $wipId;
            }
        }

        if ($purchase->warehouse_id && in_array($purchase->warehouse_id, $allowed, true)) {
            return $purchase->warehouse_id;
        }

        $defaultWarehouseId = optional(WmsContext::defaultWarehouse($purchase->branch_id))->id;
        if ($defaultWarehouseId && in_array($defaultWarehouseId, $allowed, true)) {
            return $defaultWarehouseId;
        }

        return $allowed[0] ?? null;
    }
}
