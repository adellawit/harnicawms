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
        return self::receivableNatureCodes($purchase)->contains(self::RAW_MATERIAL_NATURE);
    }

    /**
     * Nature codes of PO lines that still have remaining qty to receive.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function receivableNatureCodes(ProductPurchaseOrder $purchase)
    {
        if (! $purchase->relationLoaded('items')) {
            $purchase->load(['items.product.nature', 'items.receiveItems']);
        } else {
            foreach ($purchase->items as $item) {
                $item->loadMissing(['product.nature', 'receiveItems']);
            }
        }

        return $purchase->items
            ->filter(fn (ProductPurchaseOrderItem $item) => self::remainingQuantity($item) > 0)
            ->map(fn (ProductPurchaseOrderItem $item) => $item->product?->nature?->code)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Warehouse types allowed for this receive, based on remaining line natures.
     *
     * @return list<string>
     */
    public static function allowedWarehouseTypeCodes(ProductPurchaseOrder $purchase): array
    {
        $natures = self::receivableNatureCodes($purchase);
        if ($natures->isEmpty()) {
            return [];
        }

        $types = [];
        foreach ($natures as $nature) {
            $types = array_merge($types, match ($nature) {
                'RAW_MATERIAL', 'SEMI_FINISHED' => ['RAW_MATERIAL', 'WIP'],
                'FINISHED_GOOD' => ['FG'],
                default => ['GENERAL', 'FG', 'RAW_MATERIAL', 'WIP'],
            });
        }

        return array_values(array_unique($types));
    }

    /**
     * @param  list<array{id: string, label: string}>  $options
     * @return list<array{id: string, label: string}>
     */
    public static function filterOptionsByPurchase(ProductPurchaseOrder $purchase, array $options): array
    {
        $allowedTypes = self::allowedWarehouseTypeCodes($purchase);
        if ($allowedTypes === [] || $options === []) {
            return $options;
        }

        $allowedIds = Warehouse::query()
            ->whereIn('id', collect($options)->pluck('id')->all())
            ->whereIn('warehouse_type_code', $allowedTypes)
            ->pluck('id')
            ->all();

        $filtered = array_values(array_filter(
            $options,
            fn (array $option) => in_array($option['id'], $allowedIds, true)
        ));

        // Fallback: jangan kosongkan dropdown jika mapping type tidak ketemu.
        return $filtered !== [] ? $filtered : $options;
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
            // Bahan baku: utamakan gudang RAW_MATERIAL, lalu WIP.
            $rmId = optional(
                Warehouse::inventoryActive()
                    ->where('warehouse_type_code', 'RAW_MATERIAL')
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                    ->orderByDesc('is_default')
                    ->orderBy('code')
                    ->first()
            )->id;

            if ($rmId && in_array($rmId, $allowed, true)) {
                return $rmId;
            }

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
