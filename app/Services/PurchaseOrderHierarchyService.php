<?php

namespace App\Services;

use App\Models\ProductPurchaseOrder;
use App\Models\ProductPurchaseOrderItem;
use App\Models\ProductPurchaseOrderReceiveItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PurchaseOrderHierarchyService
{
    public const KIND_STANDALONE = 'standalone';

    public const KIND_MASTER = 'master';

    public const KIND_SUB = 'sub';

    public static function itemKey(?string $productId, ?string $variantId, string $unitId): string
    {
        return implode('|', [$productId, $variantId ?: 'null', $unitId]);
    }

    public static function isMaster(ProductPurchaseOrder $purchase): bool
    {
        return ($purchase->po_kind ?? self::KIND_STANDALONE) === self::KIND_MASTER;
    }

    public static function isSub(ProductPurchaseOrder $purchase): bool
    {
        return ($purchase->po_kind ?? self::KIND_STANDALONE) === self::KIND_SUB;
    }

    public static function isEditable(ProductPurchaseOrder $purchase): bool
    {
        if (self::isMaster($purchase)) {
            return false;
        }

        $status = $purchase->status_key ?? $purchase->status;

        return $status === 'draft' && ! $purchase->trashed();
    }

    public static function canReceive(ProductPurchaseOrder $purchase): bool
    {
        return ! self::isMaster($purchase);
    }

    /**
     * @return Collection<int, ProductPurchaseOrder>
     */
    public static function eligibleMastersForUser(array $accessibleBranchIds): Collection
    {
        return ProductPurchaseOrder::query()
            ->with(['items.product', 'items.unit', 'items.variant'])
            ->where('po_kind', self::KIND_MASTER)
            ->whereNull('deleted_at')
            ->when(! empty($accessibleBranchIds), fn ($q) => $q->whereIn('branch_id', $accessibleBranchIds))
            ->whereNotIn('status', ['cancelled'])
            ->orderByDesc('purchase_date')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (ProductPurchaseOrder $master) => self::masterHasRemainingRelease($master))
            ->values();
    }

    public static function masterHasRemainingRelease(ProductPurchaseOrder $master): bool
    {
        if (! self::isMaster($master)) {
            return false;
        }

        $master->loadMissing('items');

        foreach ($master->items as $item) {
            if (self::remainingQuantityForParentItem($master, $item) > 0) {
                return true;
            }
        }

        return false;
    }

    public static function canCreateSubPurchaseOrder(ProductPurchaseOrder $purchase): bool
    {
        if (! self::isMaster($purchase) || $purchase->trashed()) {
            return false;
        }

        if (in_array($purchase->status_key ?? $purchase->status, ['cancelled'], true)) {
            return false;
        }

        return self::masterHasRemainingRelease($purchase);
    }

    /**
     * Qty dialokasikan ke Sub-PO (belum tentu sudah diterima fisik).
     */
    public static function allocatedQuantityForParentItem(
        ProductPurchaseOrder $master,
        ProductPurchaseOrderItem $parentItem,
        ?string $excludeSubPurchaseOrderId = null
    ): float {
        return (float) self::subItemsMatchingParentItemQuery($master, $parentItem, $excludeSubPurchaseOrderId)
            ->sum('quantity');
    }

    /**
     * Sub-PO line items yang mengikat ke baris PO utama (by parent_item_id atau product key).
     */
    public static function subItemsMatchingParentItemQuery(
        ProductPurchaseOrder $master,
        ProductPurchaseOrderItem $parentItem,
        ?string $excludeSubPurchaseOrderId = null
    ) {
        return ProductPurchaseOrderItem::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($parentItem) {
                $q->where('parent_item_id', $parentItem->id)
                    ->orWhere(function ($q2) use ($parentItem) {
                        $q2->whereNull('parent_item_id')
                            ->where('product_id', $parentItem->product_id)
                            ->where('unit_id', $parentItem->unit_id)
                            ->where(function ($q3) use ($parentItem) {
                                if ($parentItem->variant_id) {
                                    $q3->where('variant_id', $parentItem->variant_id);
                                } else {
                                    $q3->whereNull('variant_id');
                                }
                            });
                    });
            })
            ->whereHas('purchaseOrder', function ($q) use ($master, $excludeSubPurchaseOrderId) {
                $q->where('parent_id', $master->id)
                    ->where('po_kind', self::KIND_SUB)
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', ['cancelled']);

                if ($excludeSubPurchaseOrderId) {
                    $q->where('id', '!=', $excludeSubPurchaseOrderId);
                }
            });
    }

    /**
     * Qty yang sudah diterima fisik lewat Sub-PO (masuk gudang).
     */
    public static function receivedQuantityForParentItem(ProductPurchaseOrder $master, ProductPurchaseOrderItem $parentItem): float
    {
        $subItemIds = self::subItemsMatchingParentItemQuery($master, $parentItem)->pluck('id');

        if ($subItemIds->isEmpty()) {
            return 0.0;
        }

        return (float) ProductPurchaseOrderReceiveItem::query()
            ->whereIn('purchase_order_item_id', $subItemIds)
            ->sum('quantity_received');
    }

    /** @deprecated Use allocatedQuantityForParentItem() */
    public static function releasedQuantityForParentItem(ProductPurchaseOrder $master, ProductPurchaseOrderItem $parentItem): float
    {
        return self::allocatedQuantityForParentItem($master, $parentItem);
    }

    public static function remainingQuantityForParentItem(
        ProductPurchaseOrder $master,
        ProductPurchaseOrderItem $parentItem,
        ?string $excludeSubPurchaseOrderId = null
    ): float {
        $committed = (float) $parentItem->quantity;
        $allocated = self::allocatedQuantityForParentItem($master, $parentItem, $excludeSubPurchaseOrderId);

        return max(0, $committed - $allocated);
    }

    public static function unfulfilledQuantityForParentItem(ProductPurchaseOrder $master, ProductPurchaseOrderItem $parentItem): float
    {
        $committed = (float) $parentItem->quantity;
        $received = self::receivedQuantityForParentItem($master, $parentItem);

        return max(0, $committed - $received);
    }

    /**
     * Persentase penerimaan fisik: total received / total ordered (0–100).
     * CPO dihitung dari penerimaan Sub-PO terkait.
     */
    public static function receiveProgressPercent(ProductPurchaseOrder $purchase): int
    {
        $purchase->loadMissing(['items' => fn ($q) => $q->whereNull('deleted_at')]);

        $ordered = (float) $purchase->items->sum('quantity');
        if ($ordered <= 0) {
            return 0;
        }

        if (self::isMaster($purchase)) {
            $received = (float) $purchase->items->sum(
                fn (ProductPurchaseOrderItem $item) => self::receivedQuantityForParentItem($purchase, $item)
            );
        } else {
            $purchase->loadMissing('items.receiveItems');
            $received = (float) $purchase->items->sum(
                fn (ProductPurchaseOrderItem $item) => (float) $item->receiveItems->sum('quantity_received')
            );
        }

        return (int) min(100, round($received / $ordered * 100));
    }

    public static function receiveProgressHtml(ProductPurchaseOrder $purchase): string
    {
        $pct = self::receiveProgressPercent($purchase);
        $color = $pct >= 100 ? 'success' : ($pct > 0 ? 'info' : 'secondary');

        return '<div class="d-flex align-items-center gap-2 po-progress-cell">'
            .'<div class="progress flex-grow-1 po-progress-bar">'
            .'<div class="progress-bar bg-'.$color.'" style="width:'.$pct.'%"></div>'
            .'</div>'
            .'<small class="text-muted text-nowrap">'.$pct.'%</small>'
            .'</div>';
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function validateSubPurchaseItems(
        ProductPurchaseOrder $master,
        array $items,
        ?string $excludeSubPurchaseOrderId = null
    ): void {
        $master->loadMissing('items.product', 'items.unit', 'items.variant');

        $parentItemsById = $master->items->keyBy('id');
        $parentItemsByKey = $master->items->keyBy(
            fn (ProductPurchaseOrderItem $item) => self::itemKey($item->product_id, $item->variant_id, $item->unit_id)
        );

        $errors = [];
        $requestedByParentId = [];

        foreach ($items as $index => $item) {
            $qty = (float) (normalize_number_input($item['quantity'] ?? 0) ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $parentItem = null;
            if (! empty($item['parent_item_id']) && $parentItemsById->has($item['parent_item_id'])) {
                $parentItem = $parentItemsById->get($item['parent_item_id']);
            } else {
                $key = self::itemKey(
                    $item['product_id'] ?? null,
                    $item['variant_id'] ?? null,
                    $item['unit_id'] ?? ''
                );
                $parentItem = $parentItemsByKey->get($key);
            }

            if (! $parentItem) {
                $errors["items.{$index}.product_id"] = 'Produk tidak ada di PO utama.';

                continue;
            }

            if ($parentItem->product_id !== ($item['product_id'] ?? null)
                || ($parentItem->variant_id ?: null) !== ($item['variant_id'] ?: null)
                || $parentItem->unit_id !== ($item['unit_id'] ?? null)) {
                $errors["items.{$index}.product_id"] = 'Baris item harus sama dengan PO utama (produk, varian, satuan).';

                continue;
            }

            $parentId = $parentItem->id;
            if (! isset($requestedByParentId[$parentId])) {
                $requestedByParentId[$parentId] = ['qty' => 0, 'index' => $index, 'parentItem' => $parentItem];
            }
            $requestedByParentId[$parentId]['qty'] += $qty;
        }

        foreach ($requestedByParentId as $payload) {
            $parentItem = $payload['parentItem'];
            $totalQty = $payload['qty'];
            $index = $payload['index'];
            $remaining = self::remainingQuantityForParentItem($master, $parentItem, $excludeSubPurchaseOrderId);

            if ($totalQty > $remaining + 1e-6) {
                $label = $parentItem->product?->name ?? 'Item';
                $unit = $parentItem->unit?->symbol ?: $parentItem->unit?->name ?: '';
                $errors["items.{$index}.quantity"] = "Qty melebihi sisa release PO utama untuk {$label}. Sisa: {$remaining}".($unit ? " {$unit}" : '').'.';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Backfill parent_item_id pada Sub-PO lama yang belum terhubung.
     */
    public static function backfillParentItemLinks(ProductPurchaseOrder $master): int
    {
        if (! self::isMaster($master)) {
            return 0;
        }

        $master->loadMissing('items');
        $updated = 0;

        $subItems = ProductPurchaseOrderItem::query()
            ->whereNull('parent_item_id')
            ->whereNull('deleted_at')
            ->whereHas('purchaseOrder', function ($q) use ($master) {
                $q->where('parent_id', $master->id)
                    ->where('po_kind', self::KIND_SUB)
                    ->whereNull('deleted_at');
            })
            ->get();

        $itemsByKey = $master->items->keyBy(
            fn (ProductPurchaseOrderItem $item) => self::itemKey($item->product_id, $item->variant_id, $item->unit_id)
        );

        foreach ($subItems as $subItem) {
            $key = self::itemKey($subItem->product_id, $subItem->variant_id, $subItem->unit_id);
            $parentItem = $itemsByKey->get($key);
            if (! $parentItem) {
                continue;
            }

            $subItem->update(['parent_item_id' => $parentItem->id]);
            $updated++;
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function resolveParentItemId(ProductPurchaseOrder $master, array $item): ?string
    {
        if (! empty($item['parent_item_id'])) {
            return $item['parent_item_id'];
        }

        if (! $master->relationLoaded('items')) {
            $master->loadMissing('items');
        }

        $key = self::itemKey(
            $item['product_id'] ?? null,
            $item['variant_id'] ?? null,
            $item['unit_id'] ?? ''
        );

        $parentItem = $master->items->first(
            fn (ProductPurchaseOrderItem $row) => self::itemKey($row->product_id, $row->variant_id, $row->unit_id) === $key
        );

        return $parentItem?->id;
    }

    public static function syncParentReleaseStatus(ProductPurchaseOrder $master): void
    {
        if (! self::isMaster($master)) {
            return;
        }

        self::backfillParentItemLinks($master);
        $master->loadMissing('items');

        $hasAllocated = ProductPurchaseOrder::query()
            ->where('parent_id', $master->id)
            ->where('po_kind', self::KIND_SUB)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        $hasReceived = false;
        $fullyFulfilled = true;

        foreach ($master->items as $item) {
            $received = self::receivedQuantityForParentItem($master, $item);
            if ($received > 1e-6) {
                $hasReceived = true;
            }
            if ($received + 1e-6 < (float) $item->quantity) {
                $fullyFulfilled = false;
            }
        }

        $releaseStatus = match (true) {
            ! $hasAllocated && ! $hasReceived => 'open',
            $fullyFulfilled => 'closed',
            default => 'partial',
        };

        $master->update([
            'release_status' => $releaseStatus,
        ]);
    }

    public static function generateSubPurchaseNumber(ProductPurchaseOrder $master): string
    {
        $prefix = 'RO-'.date('Ym').'-';
        $last = ProductPurchaseOrder::withTrashed()
            ->where('purchase_number', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(purchase_number) DESC, purchase_number DESC')
            ->value('purchase_number');

        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public static function nextReleaseSequence(ProductPurchaseOrder $master): int
    {
        $lastSequence = ProductPurchaseOrder::withTrashed()
            ->where('parent_id', $master->id)
            ->max('release_sequence');

        return ((int) $lastSequence) + 1;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function masterItemsPayload(ProductPurchaseOrder $master): array
    {
        $master->loadMissing(['items.product', 'items.unit', 'items.variant']);

        return $master->items->map(function (ProductPurchaseOrderItem $item) use ($master) {
            $allocated = self::allocatedQuantityForParentItem($master, $item);
            $received = self::receivedQuantityForParentItem($master, $item);
            $remaining = self::remainingQuantityForParentItem($master, $item);

            return [
                'parent_item_id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'unit_id' => $item->unit_id,
                'product_name' => $item->product?->name,
                'product_code' => $item->product?->code,
                'variant_sku' => $item->variant?->sku,
                'unit_label' => $item->unit?->symbol ?: $item->unit?->name,
                'committed_qty' => (float) $item->quantity,
                'allocated_qty' => $allocated,
                'received_qty' => $received,
                'released_qty' => $allocated,
                'remaining_qty' => $remaining,
                'unfulfilled_qty' => self::unfulfilledQuantityForParentItem($master, $item),
                'unit_price' => (float) $item->unit_price,
                'discount_amount' => (float) $item->discount_amount,
            ];
        })->values()->all();
    }
}
