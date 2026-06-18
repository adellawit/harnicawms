<?php

namespace App\Services\MasterData;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bootstrap & migrasi data master_data.warehouses dari legacy business_units.
 */
class WarehouseBootstrapService
{
    private const CONNECTION = 'master_data';

    private const TABLE_BUSINESS_UNITS = 'business_units';

    private const TABLE_BUSINESS_UNIT_BRANCHES = 'business_unit_branches';

    private const TABLE_WAREHOUSES = 'warehouses';

    private const TABLE_BRANCH_WAREHOUSE_ASSIGNMENTS = 'branch_warehouse_assignments';

    private const COL_ID = 'id';

    private const COL_TYPE_CODE = 'type_code';

    private const COL_BRANCH_ID = 'branch_id';

    private const COL_IS_DEFAULT = 'is_default';

    private const COL_CREATED_AT = 'created_at';

    private const COL_DELETED_AT = 'deleted_at';

    private const COL_LEGACY_BUSINESS_UNIT_ID = 'legacy_business_unit_id';

    private const COL_WAREHOUSE_ID = 'warehouse_id';

    private const COL_PRIORITY = 'priority';

    private const VALID_WAREHOUSE_TYPES = [
        'RAW_MATERIAL',
        'WIP',
        'PRODUCTION',
        'FG',
        'GENERAL',
        'TRANSIT',
        'QUARANTINE',
    ];

    public function migrateAll(): void
    {
        $this->migrateLegacyBusinessUnitWarehouses();
        $this->migrateLegacyBranchWarehousePivot();
        $this->ensureDefaultWarehousePerBranch();
    }

    public function syncForSeeding(): void
    {
        $this->migrateLegacyBusinessUnitWarehouses();
        $this->ensureDefaultWarehousePerBranch();
    }

    public function rollback(): void
    {
        $this->table(self::TABLE_BRANCH_WAREHOUSE_ASSIGNMENTS)->delete();
        $this->table(self::TABLE_WAREHOUSES)->delete();
    }

    private function db(): Connection
    {
        return DB::connection(self::CONNECTION);
    }

    private function table(string $table): Builder
    {
        return $this->db()->table($table);
    }

    private function migrateLegacyBusinessUnitWarehouses(): void
    {
        $legacyWarehouses = $this->table(self::TABLE_BUSINESS_UNITS)
            ->where(self::COL_TYPE_CODE, 'WAREHOUSE')
            ->orderBy(self::COL_CREATED_AT)
            ->get();

        foreach ($legacyWarehouses as $legacy) {
            if ($this->table(self::TABLE_WAREHOUSES)->where(self::COL_LEGACY_BUSINESS_UNIT_ID, $legacy->id)->exists()) {
                continue;
            }

            [$companyId, $branchId] = $this->resolveCompanyAndBranch($legacy);

            if (! $companyId) {
                continue;
            }

            $this->table(self::TABLE_WAREHOUSES)->insert([
                'id' => $legacy->id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_type_code' => $this->resolveWarehouseType($legacy->brand_name),
                'code' => $legacy->code,
                'name' => $legacy->name,
                'short_name' => $legacy->brand_name,
                'legal_name' => $legacy->legal_name,
                'email' => $legacy->email,
                'phone' => $legacy->phone,
                'address' => $legacy->address,
                'city' => $legacy->city,
                'province' => $legacy->province,
                'postal_code' => $legacy->postal_code,
                'country' => $legacy->country,
                'is_default' => false,
                'is_inventory_active' => (bool) $legacy->is_inventory_active,
                'is_active' => (bool) $legacy->is_active,
                'legacy_business_unit_id' => $legacy->id,
                'created_by' => $legacy->created_by,
                'updated_by' => $legacy->updated_by,
                'deleted_by' => $legacy->deleted_by,
                'created_at' => $legacy->created_at ?? Carbon::now(),
                'updated_at' => $legacy->updated_at ?? Carbon::now(),
                'deleted_at' => $legacy->deleted_at,
            ]);
        }
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveCompanyAndBranch(object $legacy): array
    {
        if (! $legacy->parent_id) {
            return [null, null];
        }

        $parent = $this->table(self::TABLE_BUSINESS_UNITS)
            ->where(self::COL_ID, $legacy->parent_id)
            ->first();

        if (! $parent) {
            return [null, null];
        }

        return match ($parent->type_code) {
            'BRANCH' => [$parent->parent_id, $parent->id],
            'COMPANY' => [$parent->id, null],
            default => [null, null],
        };
    }

    private function resolveWarehouseType(?string $brandName): string
    {
        $type = strtoupper(trim((string) $brandName));

        return in_array($type, self::VALID_WAREHOUSE_TYPES, true) ? $type : 'GENERAL';
    }

    private function migrateLegacyBranchWarehousePivot(): void
    {
        if (! Schema::connection(self::CONNECTION)->hasTable(self::TABLE_BUSINESS_UNIT_BRANCHES)) {
            return;
        }

        $pivots = $this->table(self::TABLE_BUSINESS_UNIT_BRANCHES)->get();

        foreach ($pivots as $pivot) {
            $warehouse = $this->table(self::TABLE_WAREHOUSES)
                ->where(self::COL_LEGACY_BUSINESS_UNIT_ID, $pivot->warehouse_id)
                ->orWhere(self::COL_ID, $pivot->warehouse_id)
                ->first();

            if (! $warehouse) {
                continue;
            }

            if ($warehouse->branch_id === $pivot->branch_id) {
                if ($pivot->is_default) {
                    $this->setBranchOwnedDefault($warehouse->id, $pivot->branch_id);
                }

                continue;
            }

            $this->table(self::TABLE_BRANCH_WAREHOUSE_ASSIGNMENTS)->insertOrIgnore([
                'branch_id' => $pivot->branch_id,
                'warehouse_id' => $warehouse->id,
                'is_default' => (bool) $pivot->is_default,
                'priority' => 0,
            ]);
        }

        $this->normalizeDefaultWarehousesPerBranch();
    }

    private function setBranchOwnedDefault(string $warehouseId, string $branchId): void
    {
        $this->table(self::TABLE_WAREHOUSES)
            ->where(self::COL_BRANCH_ID, $branchId)
            ->where(self::COL_IS_DEFAULT, true)
            ->update(['is_default' => false]);

        $this->table(self::TABLE_WAREHOUSES)
            ->where(self::COL_ID, $warehouseId)
            ->update(['is_default' => true]);
    }

    private function normalizeDefaultWarehousesPerBranch(): void
    {
        $branchIds = $this->table(self::TABLE_BUSINESS_UNITS)
            ->where(self::COL_TYPE_CODE, 'BRANCH')
            ->whereNull(self::COL_DELETED_AT)
            ->pluck(self::COL_ID);

        foreach ($branchIds as $branchId) {
            $ownedDefault = $this->table(self::TABLE_WAREHOUSES)
                ->where(self::COL_BRANCH_ID, $branchId)
                ->where(self::COL_IS_DEFAULT, true)
                ->whereNull(self::COL_DELETED_AT)
                ->exists();

            $assignedDefault = $this->table(self::TABLE_BRANCH_WAREHOUSE_ASSIGNMENTS)
                ->where(self::COL_BRANCH_ID, $branchId)
                ->where(self::COL_IS_DEFAULT, true)
                ->exists();

            if ($ownedDefault || $assignedDefault) {
                continue;
            }

            $ownedWarehouse = $this->table(self::TABLE_WAREHOUSES)
                ->where(self::COL_BRANCH_ID, $branchId)
                ->whereNull(self::COL_DELETED_AT)
                ->orderBy(self::COL_CREATED_AT)
                ->first();

            if ($ownedWarehouse) {
                $this->table(self::TABLE_WAREHOUSES)
                    ->where(self::COL_ID, $ownedWarehouse->id)
                    ->update(['is_default' => true]);

                continue;
            }

            $assignedWarehouse = $this->table(self::TABLE_BRANCH_WAREHOUSE_ASSIGNMENTS)
                ->where(self::COL_BRANCH_ID, $branchId)
                ->orderByDesc(self::COL_PRIORITY)
                ->orderBy(self::COL_WAREHOUSE_ID)
                ->first();

            if ($assignedWarehouse) {
                $this->table(self::TABLE_BRANCH_WAREHOUSE_ASSIGNMENTS)
                    ->where(self::COL_BRANCH_ID, $branchId)
                    ->update(['is_default' => false]);

                $this->table(self::TABLE_BRANCH_WAREHOUSE_ASSIGNMENTS)
                    ->where(self::COL_BRANCH_ID, $branchId)
                    ->where(self::COL_WAREHOUSE_ID, $assignedWarehouse->warehouse_id)
                    ->update(['is_default' => true]);
            }
        }
    }

    private function ensureDefaultWarehousePerBranch(): void
    {
        $branches = $this->table(self::TABLE_BUSINESS_UNITS)
            ->where(self::COL_TYPE_CODE, 'BRANCH')
            ->whereNull(self::COL_DELETED_AT)
            ->orderBy(self::COL_CREATED_AT)
            ->get(['id', 'parent_id', 'code', 'name']);

        foreach ($branches as $branch) {
            if (! $branch->parent_id) {
                continue;
            }

            $hasOwnedWarehouse = $this->table(self::TABLE_WAREHOUSES)
                ->where(self::COL_BRANCH_ID, $branch->id)
                ->whereNull(self::COL_DELETED_AT)
                ->exists();

            $hasAssignedWarehouse = $this->table(self::TABLE_BRANCH_WAREHOUSE_ASSIGNMENTS)
                ->where(self::COL_BRANCH_ID, $branch->id)
                ->exists();

            if ($hasOwnedWarehouse || $hasAssignedWarehouse) {
                $this->ensureBranchHasDefault($branch->id);

                continue;
            }

            $this->table(self::TABLE_WAREHOUSES)->insert([
                'id' => DB::selectOne('SELECT public.uuid_generate_v7() AS id')->id,
                'company_id' => $branch->parent_id,
                'branch_id' => $branch->id,
                'warehouse_type_code' => 'GENERAL',
                'code' => $branch->code.'-WH-DEFAULT',
                'name' => 'Gudang '.$branch->name,
                'short_name' => 'DEFAULT',
                'is_default' => true,
                'is_inventory_active' => true,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    private function ensureBranchHasDefault(string $branchId): void
    {
        $hasDefault = $this->table(self::TABLE_WAREHOUSES)
            ->where(self::COL_BRANCH_ID, $branchId)
            ->where(self::COL_IS_DEFAULT, true)
            ->whereNull(self::COL_DELETED_AT)
            ->exists();

        if ($hasDefault) {
            return;
        }

        $first = $this->table(self::TABLE_WAREHOUSES)
            ->where(self::COL_BRANCH_ID, $branchId)
            ->whereNull(self::COL_DELETED_AT)
            ->orderBy(self::COL_CREATED_AT)
            ->first();

        if ($first) {
            $this->table(self::TABLE_WAREHOUSES)
                ->where(self::COL_ID, $first->id)
                ->update(['is_default' => true]);
        }
    }
}
