<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\ProductLabelSerial;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\SalesOrder;
use App\Models\SalesOrderBarcodeDispatch;
use App\Models\SalesOrderItem;
use App\Models\SalesOrderItemSerialAssignment;
use App\Repositories\BarcodeDispatchRepository;
use App\Services\Product\ProductSearchService;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BarcodeDispatchService
{
    public function __construct(
        private readonly BarcodeDispatchRepository $repository,
        private readonly ProductSearchService $productSearch,
    ) {}

    /**
     * Resolve a printed label serial into a POS cart payload.
     *
     * @return array<string, mixed>
     */
    public function lookupForPos(
        string $serialNumber,
        string $branchId,
        string $priceListId,
        ?string $pendingProductId = null,
        ?string $pendingVariantId = null,
        ?string $pendingUnitId = null,
        ?string $warehouseId = null,
    ): array {
        $serialNumber = trim($serialNumber);
        if ($serialNumber === '') {
            throw new InvalidArgumentException('Nomor serial barcode wajib diisi.');
        }

        $candidates = $this->repository->findSerialsByNumber($serialNumber);
        if ($candidates->isEmpty()) {
            throw new InvalidArgumentException('Barcode serial tidak ditemukan.');
        }

        $lastError = null;

        foreach ($candidates as $serial) {
            try {
                return $this->mapSerialCandidateForPos(
                    $serial,
                    $branchId,
                    $priceListId,
                    $pendingProductId,
                    $pendingVariantId,
                    $pendingUnitId,
                );
            } catch (InvalidArgumentException $exception) {
                $lastError = $exception;
            }
        }

        throw $lastError ?? new InvalidArgumentException('Barcode serial tidak ditemukan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSerialCandidateForPos(
        ProductLabelSerial $serial,
        string $branchId,
        string $priceListId,
        ?string $pendingProductId,
        ?string $pendingVariantId,
        ?string $pendingUnitId,
    ): array {
        if (SalesOrderItemSerialAssignment::where('product_label_serial_id', $serial->id)->exists()) {
            throw new InvalidArgumentException('Barcode sudah pernah dialokasikan ke sales order.');
        }

        if ($pendingProductId && $serial->product_id !== $pendingProductId) {
            throw new InvalidArgumentException('Barcode tidak cocok dengan product yang dipilih.');
        }

        if ($pendingUnitId && $serial->unit_id !== $pendingUnitId) {
            throw new InvalidArgumentException('Barcode tidak cocok dengan satuan product yang dipilih.');
        }

        if (
            $pendingVariantId
            && $serial->product_variant_id
            && $serial->product_variant_id !== $pendingVariantId
        ) {
            throw new InvalidArgumentException('Barcode tidak cocok dengan variant yang dipilih.');
        }

        $variant = $serial->variant;
        if (! $variant && $pendingVariantId) {
            $variant = $serial->product?->variants()
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('id', $pendingVariantId)
                ->first();
        }

        if (! $variant && $serial->product_id) {
            $variant = $this->resolvePricedVariantForSerial($serial, $branchId, $priceListId);
        }

        if (! $variant) {
            throw new InvalidArgumentException('Variant product untuk barcode ini tidak ditemukan.');
        }

        $variant->loadMissing('product');
        $mapped = $this->productSearch->mapVariantForPos($variant, $branchId, $priceListId, $warehouseId);
        if (! $mapped || empty($mapped['unit_id']) || (float) $mapped['selling_price'] <= 0) {
            throw new InvalidArgumentException('Harga product untuk barcode ini tidak tersedia pada price list aktif.');
        }

        $unitId = $serial->unit_id ?: $mapped['unit_id'];
        $unitLabel = $serial->unit?->symbol ?: ($serial->unit?->name ?: $mapped['unit_label']);

        if ($unitId !== $mapped['unit_id']) {
            $priceRow = ProductVariantPrice::query()
                ->where('variant_id', $variant->id)
                ->where('branch_id', $branchId)
                ->where('price_list_id', $priceListId)
                ->where('unit_id', $unitId)
                ->whereNull('deleted_at')
                ->first();

            if (! $priceRow || (float) $priceRow->selling_price <= 0) {
                throw new InvalidArgumentException('Harga satuan barcode tidak tersedia pada price list aktif.');
            }

            $mapped['selling_price'] = (float) $priceRow->selling_price;
            $mapped['unit_id'] = $unitId;
            $mapped['unit_label'] = $unitLabel;
        }

        return [
            'serial_id' => $serial->id,
            'serial_number' => $serial->serial_number,
            'product_id' => $serial->product_id,
            'variant_id' => $variant->id,
            'name' => $mapped['display_name'] ?? product_print_name($serial->product?->name),
            'price' => (float) $mapped['selling_price'],
            'unit_id' => $mapped['unit_id'],
            'unit_label' => $mapped['unit_label'] ?? $unitLabel,
            'image' => $variant->image,
            'stock' => (int) ($mapped['stock'] ?? 0),
        ];
    }

    private function resolvePricedVariantForSerial(
        ProductLabelSerial $serial,
        string $branchId,
        string $priceListId,
    ): ?ProductVariant {
        $variants = $serial->product?->variants()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get() ?? collect();

        foreach ($variants as $variant) {
            $mapped = $this->productSearch->mapVariantForPos($variant, $branchId, $priceListId);
            if (! $mapped || (float) ($mapped['selling_price'] ?? 0) <= 0) {
                continue;
            }

            $unitId = $serial->unit_id ?: ($mapped['unit_id'] ?? null);
            if (! $unitId || $unitId === ($mapped['unit_id'] ?? null)) {
                return $variant;
            }

            $unitPrice = ProductVariantPrice::query()
                ->where('variant_id', $variant->id)
                ->where('branch_id', $branchId)
                ->where('price_list_id', $priceListId)
                ->where('unit_id', $unitId)
                ->whereNull('deleted_at')
                ->where('selling_price', '>', 0)
                ->exists();

            if ($unitPrice) {
                return $variant;
            }
        }

        return $variants->first();
    }

    public function productUnitHasTrackableSerials(
        string $productId,
        string $unitId,
        ?string $variantId = null
    ): bool {
        return $this->repository->productUnitHasTrackableSerials($productId, $unitId, $variantId);
    }

    public function productHasTrackableSerials(string $productId, ?string $variantId = null): bool
    {
        return $this->repository->productHasTrackableSerials($productId, $variantId);
    }

    /**
     * @param  array<string, list<string>>  $serialsByItemId
     */
    public function assignSerialsForNewOrder(
        SalesOrder $order,
        array $serialsByItemId,
        ?string $userId,
        ?string $branchId = null
    ): void {
        if ($serialsByItemId === []) {
            return;
        }

        $this->repository->transaction(function () use ($order, $serialsByItemId, $userId, $branchId): void {
            $order = $this->repository->lockOrder($order->id);
            $this->assertBranch($order, $branchId);
            $this->resolveDestination($order);

            $dispatch = $this->repository->findOrCreateDispatch($order->id);
            $this->assertDraft($dispatch);

            foreach ($order->items as $item) {
                $serials = array_values(array_filter($serialsByItemId[$item->id] ?? []));
                if ($serials === []) {
                    continue;
                }

                if (! $this->repository->itemHasTrackableSerial($item)) {
                    throw new InvalidArgumentException(
                        "Item {$item->product?->name} tidak memiliki serial barcode."
                    );
                }

                $expected = $this->expectedQuantity($item);
                if (count($serials) !== $expected) {
                    throw new InvalidArgumentException(
                        "Jumlah barcode {$item->product?->name} harus {$expected}."
                    );
                }

                foreach ($serials as $serialNumber) {
                    $this->createAssignmentForItem($dispatch, $item, (string) $serialNumber, $userId);
                }
            }
        });
    }

    public function finalizeIfEligible(string $orderId, ?string $userId, ?string $branchId = null): ?SalesOrderBarcodeDispatch
    {
        $order = $this->repository->findOrder($orderId);
        $dispatch = $order->barcodeDispatch;

        if (! $dispatch || $dispatch->status === SalesOrderBarcodeDispatch::STATUS_COMPLETED) {
            return $dispatch;
        }

        try {
            $this->resolveDestination($order);
        } catch (InvalidArgumentException) {
            return null;
        }

        $hasTracked = $order->items->contains(
            fn (SalesOrderItem $item): bool => $this->repository->itemHasTrackableSerial($item)
        );

        if (! $hasTracked) {
            return null;
        }

        return $this->finalize($orderId, $userId, $branchId);
    }

    /**
     * @param  list<array<string, mixed>>  $itemsData
     */
    public function assertCartSerialsForDestination(
        ?string $customerId,
        array $itemsData,
        bool $requireScan = true,
    ): void {
        if (! $requireScan) {
            return;
        }
        if (! $customerId) {
            return;
        }

        $customer = Customer::with(['agent', 'reseller.agent'])->find($customerId);
        if (! $customer) {
            return;
        }

        $agent = $customer->agent ?: $customer->reseller?->agent;
        if (! $agent) {
            return;
        }

        foreach ($itemsData as $row) {
            if (! empty($row['is_promo_free'])) {
                continue;
            }

            $item = new SalesOrderItem([
                'product_id' => $row['product_id'],
                'product_variant_id' => $row['product_variant_id'],
                'unit_id' => $row['unit_id'],
                'quantity' => $row['quantity'],
            ]);

            if (! $this->repository->itemHasTrackableSerial($item)) {
                continue;
            }

            $serials = array_values(array_filter($row['serial_numbers'] ?? []));
            $expected = $this->expectedQuantity($item);

            if (count($serials) !== $expected) {
                throw new InvalidArgumentException(
                    'Scan barcode serial wajib untuk product Agent/Reseller yang memiliki label serial.'
                );
            }
        }
    }

    /**
     * @return array{order: SalesOrder, destination: array<string, mixed>, items: Collection, dispatch: ?SalesOrderBarcodeDispatch}
     */
    public function details(string $orderId, ?string $branchId = null): array
    {
        $order = $this->repository->findOrder($orderId);
        $this->assertBranch($order, $branchId);
        $destination = $this->resolveDestination($order);
        $dispatch = $order->barcodeDispatch;
        $counts = $dispatch
            ? $this->repository->assignmentCounts($dispatch->id)
            : collect();

        $items = $order->items->map(function (SalesOrderItem $item) use ($counts): array {
            $trackable = $this->repository->itemHasTrackableSerial($item);
            $expected = $trackable ? $this->expectedQuantity($item) : 0;

            return [
                'model' => $item,
                'trackable' => $trackable,
                'expected' => $expected,
                'scanned' => (int) ($counts[$item->id] ?? 0),
                'complete' => ! $trackable || (int) ($counts[$item->id] ?? 0) === $expected,
            ];
        });

        return compact('order', 'destination', 'items', 'dispatch');
    }

    public function scan(
        string $orderId,
        ?string $itemId,
        string $serialNumber,
        ?string $userId,
        ?string $branchId = null
    ): SalesOrderItemSerialAssignment {
        $serialNumber = trim($serialNumber);

        if ($serialNumber === '') {
            throw new InvalidArgumentException('Nomor serial barcode wajib diisi.');
        }

        return $this->repository->transaction(function () use ($orderId, $itemId, $serialNumber, $userId, $branchId) {
            $order = $this->repository->lockOrder($orderId);
            $this->assertBranch($order, $branchId);
            $this->resolveDestination($order);

            $dispatch = $this->repository->findOrCreateDispatch($order->id);
            $this->assertDraft($dispatch);

            $item = $this->resolveScanItem($order, $itemId, $serialNumber);

            return $this->createAssignmentForItem($dispatch, $item, $serialNumber, $userId);
        });
    }

    public function remove(string $orderId, string $assignmentId, ?string $branchId = null): void
    {
        $this->repository->transaction(function () use ($orderId, $assignmentId, $branchId): void {
            $order = $this->repository->lockOrder($orderId);
            $this->assertBranch($order, $branchId);
            $this->resolveDestination($order);

            $assignment = $this->repository->lockAssignment($assignmentId);
            $dispatch = $this->repository->findOrCreateDispatch($order->id);
            $this->assertDraft($dispatch);

            if ($assignment->dispatch_id !== $dispatch->id) {
                throw new InvalidArgumentException('Barcode assignment tidak termasuk sales order ini.');
            }

            $this->repository->deleteAssignment($assignment);
        });
    }

    public function finalize(
        string $orderId,
        ?string $userId,
        ?string $branchId = null
    ): SalesOrderBarcodeDispatch {
        return $this->repository->transaction(function () use ($orderId, $userId, $branchId) {
            $order = $this->repository->lockOrder($orderId);
            $this->assertBranch($order, $branchId);
            $this->resolveDestination($order);
            $dispatch = $this->repository->findOrCreateDispatch($order->id);
            $this->assertDraft($dispatch);
            $counts = $this->repository->assignmentCounts($dispatch->id);
            $trackedItems = 0;

            foreach ($order->items as $item) {
                if (! $this->repository->itemHasTrackableSerial($item)) {
                    continue;
                }

                $trackedItems++;
                $expected = $this->expectedQuantity($item);
                $scanned = (int) ($counts[$item->id] ?? 0);

                if ($scanned !== $expected) {
                    throw new InvalidArgumentException(
                        "Barcode {$item->product?->name} belum lengkap ({$scanned}/{$expected})."
                    );
                }
            }

            if ($trackedItems === 0) {
                throw new InvalidArgumentException('Sales order ini tidak memiliki item dengan serial barcode.');
            }

            $dispatch->update([
                'status' => SalesOrderBarcodeDispatch::STATUS_COMPLETED,
                'dispatched_by' => $userId,
                'dispatched_at' => now(),
            ]);

            return $dispatch->fresh();
        });
    }

    private function createAssignmentForItem(
        SalesOrderBarcodeDispatch $dispatch,
        SalesOrderItem $item,
        string $serialNumber,
        ?string $userId
    ): SalesOrderItemSerialAssignment {
        $serial = $this->repository->lockSerialForItem($item, $serialNumber);
        if (! $serial) {
            throw new InvalidArgumentException('Barcode tidak cocok dengan product, variant, atau satuan item.');
        }

        if ($this->repository->assignmentForSerial($serial->id)) {
            throw new InvalidArgumentException('Barcode sudah pernah dialokasikan ke sales order.');
        }

        $expected = $this->expectedQuantity($item);
        $count = (int) ($this->repository->assignmentCounts($dispatch->id)[$item->id] ?? 0);
        if ($count >= $expected) {
            throw new InvalidArgumentException('Jumlah barcode untuk item ini sudah terpenuhi.');
        }

        return $this->repository->createAssignment([
            'dispatch_id' => $dispatch->id,
            'sales_order_item_id' => $item->id,
            'product_label_serial_id' => $serial->id,
            'scanned_by' => $userId,
            'scanned_at' => now(),
        ]);
    }

    private function resolveScanItem(SalesOrder $order, ?string $itemId, string $serialNumber): SalesOrderItem
    {
        if ($itemId) {
            /** @var SalesOrderItem|null $item */
            $item = $order->items->firstWhere('id', $itemId);
            if (! $item) {
                throw new InvalidArgumentException('Item sales order tidak ditemukan.');
            }

            return $item;
        }

        $candidates = $order->items->filter(function (SalesOrderItem $item) use ($serialNumber): bool {
            return $this->repository->serialMatchesItem($item, $serialNumber);
        });

        if ($candidates->isEmpty()) {
            throw new InvalidArgumentException('Barcode tidak cocok dengan product, variant, atau satuan item.');
        }

        $dispatch = $order->barcodeDispatch;
        $counts = $dispatch
            ? $this->repository->assignmentCounts($dispatch->id)
            : collect();

        $incomplete = $candidates->first(function (SalesOrderItem $item) use ($counts): bool {
            $expected = $this->expectedQuantity($item);
            $scanned = (int) ($counts[$item->id] ?? 0);

            return $scanned < $expected;
        });

        if (! $incomplete) {
            throw new InvalidArgumentException('Jumlah barcode untuk item yang cocok sudah terpenuhi.');
        }

        return $incomplete;
    }

    /**
     * @return array{customer: mixed, reseller: mixed, agent: mixed}
     */
    private function resolveDestination(SalesOrder $order): array
    {
        $customer = $order->customer;
        $agent = $customer?->agent;
        $reseller = $customer?->reseller;

        if (! $agent && $reseller) {
            $agent = $reseller->agent;
        }

        if (! $customer || ! $agent) {
            throw new InvalidArgumentException('Sales order harus ditujukan ke Agent atau Reseller yang memiliki Agent induk.');
        }

        return compact('customer', 'reseller', 'agent');
    }

    private function expectedQuantity(SalesOrderItem $item): int
    {
        $quantity = (float) $item->quantity;
        $integerQuantity = (int) round($quantity);

        if ($integerQuantity < 1 || abs($quantity - $integerQuantity) > 0.000001) {
            throw new InvalidArgumentException('Quantity item barcode harus berupa bilangan bulat.');
        }

        return $integerQuantity;
    }

    private function assertDraft(SalesOrderBarcodeDispatch $dispatch): void
    {
        if ($dispatch->status !== SalesOrderBarcodeDispatch::STATUS_DRAFT) {
            throw new InvalidArgumentException('Barcode Dispatch yang sudah completed tidak dapat diubah.');
        }
    }

    private function assertBranch(SalesOrder $order, ?string $branchId): void
    {
        if ($branchId && $order->branch_id !== $branchId) {
            throw new InvalidArgumentException('Sales order tidak termasuk branch aktif.');
        }
    }
}
