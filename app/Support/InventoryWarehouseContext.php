<?php

namespace App\Support;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class InventoryWarehouseContext
{
    /**
     * @return array{
     *     warehouse_id: ?string,
     *     warehouse: ?Warehouse,
     *     branch_id: ?string,
     *     company_id: ?string,
     *     warehouses: \Illuminate\Support\Collection<int, Warehouse>
     * }
     */
    public static function resolve(Request $request, ?User $user = null, bool $requireWarehouse = false, bool $autoSelectDefault = true): array
    {
        $user ??= auth('web')->user();
        $accessibleWarehouses = WmsContext::accessibleWarehouses($user);
        $accessibleIds = $accessibleWarehouses->pluck('id')->all();

        $defaultBranchId = $user?->current_business_unit_id;
        $branchId = $request->get('branch_id', $defaultBranchId);

        $warehouseId = $request->get('warehouse_id');
        if (! $warehouseId && $request->filled('branch_id')) {
            $legacyId = $request->get('branch_id');
            if (in_array($legacyId, $accessibleIds, true)) {
                $warehouseId = $legacyId;
            }
        }

        if ($warehouseId && ! in_array($warehouseId, $accessibleIds, true)) {
            abort(403, 'Unauthorized warehouse.');
        }

        if ($autoSelectDefault && ! $warehouseId && $branchId) {
            $warehouseId = optional(WmsContext::defaultWarehouse($branchId))->id;
        }

        if ($requireWarehouse && ! $warehouseId) {
            abort(422, 'Pilih gudang terlebih dahulu.');
        }

        $warehouse = $warehouseId ? $accessibleWarehouses->firstWhere('id', $warehouseId) : null;
        $operationalBranchId = $warehouse?->branch_id ?: $warehouse?->company_id ?: $branchId;

        return [
            'warehouse_id' => $warehouseId,
            'warehouse' => $warehouse,
            'branch_id' => $operationalBranchId,
            'company_id' => $user?->getCompanyIdForProduct(),
            'warehouses' => $accessibleWarehouses,
            'filter_branch_id' => $branchId,
        ];
    }

    public static function assertAccessible(string $warehouseId, ?User $user = null): Warehouse
    {
        $user ??= auth('web')->user();
        $accessibleIds = WmsContext::accessibleWarehouseIds($user);

        if (! in_array($warehouseId, $accessibleIds, true)) {
            abort(403, 'Unauthorized warehouse.');
        }

        return Warehouse::query()->whereKey($warehouseId)->firstOrFail();
    }
}
