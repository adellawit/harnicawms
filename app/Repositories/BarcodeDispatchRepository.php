<?php

namespace App\Repositories;

use App\Models\ProductLabelSerial;
use App\Models\SalesOrder;
use App\Models\SalesOrderBarcodeDispatch;
use App\Models\SalesOrderItem;
use App\Models\SalesOrderItemSerialAssignment;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BarcodeDispatchRepository
{
    public function transaction(Closure $callback): mixed
    {
        return DB::connection((new SalesOrder)->getConnectionName())->transaction($callback);
    }

    public function findOrder(string $orderId): SalesOrder
    {
        return $this->orderQuery()->findOrFail($orderId);
    }

    public function lockOrder(string $orderId): SalesOrder
    {
        return $this->orderQuery()->lockForUpdate()->findOrFail($orderId);
    }

    public function findOrCreateDispatch(string $orderId): SalesOrderBarcodeDispatch
    {
        return SalesOrderBarcodeDispatch::firstOrCreate(
            ['sales_order_id' => $orderId],
            ['status' => SalesOrderBarcodeDispatch::STATUS_DRAFT]
        );
    }

    public function lockSerialByNumber(string $serialNumber): ?ProductLabelSerial
    {
        return ProductLabelSerial::query()
            ->where('serial_number', $serialNumber)
            ->lockForUpdate()
            ->first();
    }

    public function findSerialByNumber(string $serialNumber): ?ProductLabelSerial
    {
        return $this->findSerialsByNumber($serialNumber)->first();
    }

    /**
     * @return Collection<int, ProductLabelSerial>
     */
    public function findSerialsByNumber(string $serialNumber): Collection
    {
        return ProductLabelSerial::query()
            ->with(['product:id,code,name,default_unit_id', 'variant:id,product_id,sku,image', 'unit:id,name,symbol'])
            ->where('serial_number', $serialNumber)
            ->orderByRaw('CASE WHEN product_variant_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('created_at')
            ->get();
    }

    public function productUnitHasTrackableSerials(
        string $productId,
        string $unitId,
        ?string $variantId = null
    ): bool {
        return ProductLabelSerial::query()
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->where(function (Builder $query) use ($variantId): void {
                $query->whereNull('product_variant_id');

                if ($variantId) {
                    $query->orWhere('product_variant_id', $variantId);
                }
            })
            ->exists();
    }

    /**
     * POS catalog flag: product has printable serials (any unit) for this variant.
     */
    public function productHasTrackableSerials(string $productId, ?string $variantId = null): bool
    {
        return ProductLabelSerial::query()
            ->where('product_id', $productId)
            ->where(function (Builder $query) use ($variantId): void {
                $query->whereNull('product_variant_id');

                if ($variantId) {
                    $query->orWhere('product_variant_id', $variantId);
                }
            })
            ->exists();
    }

    public function lockSerialForItem(SalesOrderItem $item, string $serialNumber): ?ProductLabelSerial
    {
        return $this->serialsForItem($item)
            ->where('serial_number', $serialNumber)
            ->lockForUpdate()
            ->first();
    }

    public function serialMatchesItem(SalesOrderItem $item, string $serialNumber): bool
    {
        return $this->serialsForItem($item)
            ->where('serial_number', $serialNumber)
            ->exists();
    }

    public function itemHasTrackableSerial(SalesOrderItem $item): bool
    {
        return $this->serialsForItem($item)->exists();
    }

    public function assignmentForSerial(string $serialId): ?SalesOrderItemSerialAssignment
    {
        return SalesOrderItemSerialAssignment::where('product_label_serial_id', $serialId)
            ->lockForUpdate()
            ->first();
    }

    public function lockAssignment(string $assignmentId): SalesOrderItemSerialAssignment
    {
        return SalesOrderItemSerialAssignment::lockForUpdate()->findOrFail($assignmentId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createAssignment(array $attributes): SalesOrderItemSerialAssignment
    {
        return SalesOrderItemSerialAssignment::create($attributes);
    }

    public function deleteAssignment(SalesOrderItemSerialAssignment $assignment): void
    {
        $assignment->delete();
    }

    /**
     * @return Collection<string, int>
     */
    public function assignmentCounts(string $dispatchId): Collection
    {
        return SalesOrderItemSerialAssignment::where('dispatch_id', $dispatchId)
            ->selectRaw('sales_order_item_id, COUNT(*) as aggregate')
            ->groupBy('sales_order_item_id')
            ->pluck('aggregate', 'sales_order_item_id')
            ->map(fn ($count): int => (int) $count);
    }

    private function orderQuery(): Builder
    {
        return SalesOrder::query()->with([
            'branch:id,name',
            'customer.agent:id,customer_id,code,name',
            'customer.reseller:id,customer_id,agent_id,code,name',
            'customer.reseller.agent:id,code,name',
            'items.product:id,code,name',
            'items.variant:id,product_id,sku',
            'items.unit:id,name,symbol',
            'barcodeDispatch.assignments.serial:id,serial_number,product_id,product_variant_id,unit_id,unit_level',
            'barcodeDispatch.assignments.scannedByUser:id,first_name,last_name',
        ]);
    }

    private function serialsForItem(SalesOrderItem $item): Builder
    {
        return ProductLabelSerial::query()
            ->where('product_id', $item->product_id)
            ->where('unit_id', $item->unit_id)
            ->where(function (Builder $query) use ($item): void {
                $query->whereNull('product_variant_id');

                if ($item->product_variant_id) {
                    $query->orWhere('product_variant_id', $item->product_variant_id);
                }
            });
    }
}
