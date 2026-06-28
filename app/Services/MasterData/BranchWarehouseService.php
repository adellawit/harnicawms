<?php

namespace App\Services\MasterData;

use App\Models\BusinessUnit;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BranchWarehouseService
{
    public function __construct(
        protected BusinessUnitCodeService $businessUnitCodeService
    ) {
    }

    public function generateBranchCode(string $companyId): string
    {
        return $this->businessUnitCodeService->generateBranchCode($companyId);
    }

    public function generateWarehouseCode(BusinessUnit $branch): string
    {
        $prefix = $branch->code.'-WH-';

        $last = Warehouse::withTrashed()
            ->where('company_id', $branch->parent_id)
            ->where('branch_id', $branch->id)
            ->where('code', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(code) DESC, code DESC')
            ->value('code');

        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
    /**
     * @return array<string, string>
     */
    public function warehouseTypes(): array
    {
        return WarehouseType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'code')
            ->all();
    }

    /**
     * Shared / central warehouses under a company that can be assigned to a branch.
     */
    public function assignableWarehouses(?string $companyId, ?string $branchId = null): Collection
    {
        if (! $companyId) {
            return collect();
        }

        return Warehouse::query()
            ->where('company_id', $companyId)
            ->whereNull('branch_id')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'company_id', 'code', 'name', 'branch_id']);
    }

    public function defaultOwnedWarehouse(BusinessUnit $branch): ?Warehouse
    {
        return $branch->ownedWarehouses()
            ->whereNull('deleted_at')
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function warehouseValidationRules(Request $request, ?string $ownedWarehouseId = null): array
    {
        return [
            'warehouse_setup' => 'nullable|boolean',
            'warehouse_id' => 'nullable|uuid|exists:master_data.warehouses,id',
            'warehouse_name' => 'nullable|string|max:255',
            'warehouse_type_code' => 'nullable|exists:master_data.warehouse_types,code',
            'warehouse_is_inventory_active' => 'nullable|boolean',
            'warehouse_is_active' => 'nullable|boolean',
            'assigned_warehouse_ids' => 'nullable|array',
            'assigned_warehouse_ids.*' => 'uuid|exists:master_data.warehouses,id',
            'default_warehouse_id' => 'nullable|uuid|exists:master_data.warehouses,id',
        ];
    }

    public function syncFromRequest(BusinessUnit $branch, Request $request): void
    {
        if (! $branch->parent_id) {
            return;
        }

        if ($request->boolean('warehouse_setup')) {
            $this->upsertOwnedWarehouse($branch, $request);
        }

        $this->syncAssignedWarehouses($branch, $request);
        $this->applyDefaultWarehouse($branch, $request);
    }

    protected function upsertOwnedWarehouse(BusinessUnit $branch, Request $request): void
    {
        $owned = $this->defaultOwnedWarehouse($branch);

        $payload = [
            'company_id' => $branch->parent_id,
            'branch_id' => $branch->id,
            'warehouse_type_code' => $request->input('warehouse_type_code') ?: 'GENERAL',
            'code' => $owned?->code ?: $this->generateWarehouseCode($branch),
            'name' => $request->input('warehouse_name') ?: ('Gudang '.$branch->name),
            'short_name' => 'DEFAULT',
            'is_inventory_active' => $request->boolean('warehouse_is_inventory_active'),
            'is_active' => $request->boolean('warehouse_is_active'),
            'updated_by' => auth('web')->id(),
        ];

        if ($owned) {
            $owned->update($payload);

            return;
        }

        Warehouse::create(array_merge($payload, [
            'is_default' => true,
            'created_by' => auth('web')->id(),
        ]));
    }

    protected function syncAssignedWarehouses(BusinessUnit $branch, Request $request): void
    {
        $assignedIds = array_values(array_unique(array_filter(
            (array) $request->input('assigned_warehouse_ids', [])
        )));

        $defaultWarehouseId = $request->input('default_warehouse_id');
        $owned = $this->defaultOwnedWarehouse($branch);

        if ($defaultWarehouseId && $owned && $defaultWarehouseId !== $owned->id && ! in_array($defaultWarehouseId, $assignedIds, true)) {
            $assignedIds[] = $defaultWarehouseId;
        }

        $sync = [];
        foreach ($assignedIds as $warehouseId) {
            $sync[$warehouseId] = [
                'is_default' => false,
                'priority' => 0,
            ];
        }

        $branch->assignedWarehouses()->sync($sync);
    }

    protected function applyDefaultWarehouse(BusinessUnit $branch, Request $request): void
    {
        $defaultWarehouseId = $request->input('default_warehouse_id');
        $owned = $this->defaultOwnedWarehouse($branch);

        if (! $defaultWarehouseId && $owned) {
            $defaultWarehouseId = $owned->id;
        }

        if (! $defaultWarehouseId) {
            return;
        }

        $branch->ownedWarehouses()->update(['is_default' => false]);

        if ($owned && $owned->id === $defaultWarehouseId) {
            $owned->update(['is_default' => true]);
            DB::table('master_data.branch_warehouse_assignments')
                ->where('branch_id', $branch->id)
                ->update(['is_default' => false]);

            return;
        }

        DB::table('master_data.branch_warehouse_assignments')
            ->where('branch_id', $branch->id)
            ->update(['is_default' => false]);

        $branch->assignedWarehouses()->updateExistingPivot($defaultWarehouseId, ['is_default' => true]);
    }
}
