<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Support\WmsContext;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PosWarehouseService
{
    public const UNIT_LARGE_ONLY = 'large_only';

    public const UNIT_FREE = 'free';

    /**
     * @return Collection<int, Warehouse>
     */
    public function configurableWarehouses(?string $branchId, ?string $companyId = null): Collection
    {
        return Warehouse::query()
            ->inventoryActive()
            ->when(
                $branchId,
                fn ($q) => $q->forBranchAccess($branchId),
                fn ($q) => $q->forCompany($companyId)
            )
            ->with(['warehouseType', 'branch'])
            ->orderBy('name')
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, Warehouse>
     */
    public function posEnabledWarehouses(?string $branchId, ?string $companyId = null): Collection
    {
        return $this->configurableWarehouses($branchId, $companyId)
            ->where('is_pos_active', true)
            ->values();
    }

    public function defaultWarehouse(?string $branchId, ?string $companyId = null): ?Warehouse
    {
        $enabled = $this->posEnabledWarehouses($branchId, $companyId);
        if ($enabled->isEmpty()) {
            return null;
        }

        $preferred = WmsContext::salesSourceWarehouse($branchId);
        if ($preferred && $enabled->contains(fn (Warehouse $wh) => $wh->id === $preferred->id)) {
            return $enabled->firstWhere('id', $preferred->id);
        }

        return $enabled->first();
    }

    public function resolveForPos(?string $warehouseId, ?string $branchId, ?string $companyId = null): Warehouse
    {
        if (! $warehouseId) {
            throw new InvalidArgumentException('Pilih gudang POS terlebih dahulu.');
        }

        $warehouse = $this->posEnabledWarehouses($branchId, $companyId)
            ->firstWhere('id', $warehouseId);

        if (! $warehouse) {
            throw new InvalidArgumentException('Gudang tidak diizinkan untuk transaksi POS.');
        }

        return $warehouse;
    }

    public function requiresSerialScan(Warehouse $warehouse): bool
    {
        return (bool) $warehouse->pos_require_serial_scan;
    }

    public function allowsFreeUnits(Warehouse $warehouse): bool
    {
        return $warehouse->pos_unit_mode === self::UNIT_FREE;
    }
}
