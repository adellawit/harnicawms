<?php

namespace App\Support;

use App\Models\Warehouse;

/**
 * Gudang acuan tunggal (company-wide) untuk operasi manufaktur ketika user
 * tidak memilih gudang secara eksplisit. Prioritas berjenjang supaya tetap
 * berfungsi walau gudang bertipe tertentu belum ada di seed data.
 *
 * Auto-routing per-bahan berdasarkan lokasi stok sebenarnya (multi-gudang)
 * adalah task terpisah di masa depan — resolver ini sengaja hanya
 * mengembalikan SATU gudang per company per peran (bahan baku / produk jadi).
 */
class ManufacturingWarehouseResolver
{
    /**
     * @return array{0: ?string, 1: string} [warehouse_id, branch_id]
     */
    public static function resolveMaterialWarehouse(?string $companyId): array
    {
        return self::resolve($companyId, ['WIP', 'RAW_MATERIAL', 'FG']);
    }

    /**
     * @return array{0: ?string, 1: string} [warehouse_id, branch_id]
     */
    public static function resolveOutputWarehouse(?string $companyId): array
    {
        return self::resolve($companyId, ['FG', 'WIP', 'RAW_MATERIAL']);
    }

    /**
     * @param  list<string>  $typePriority
     * @return array{0: ?string, 1: string}
     */
    protected static function resolve(?string $companyId, array $typePriority): array
    {
        $cases = collect($typePriority)
            ->map(fn ($code, $i) => "WHEN '{$code}' THEN {$i}")
            ->implode(' ');
        $fallbackIndex = count($typePriority);

        $warehouse = Warehouse::inventoryActive()
            ->where('company_id', $companyId)
            ->orderByRaw("CASE warehouse_type_code {$cases} ELSE {$fallbackIndex} END")
            ->orderByDesc('is_default')
            ->first();

        $warehouseId = optional($warehouse)->id;
        $branchId = optional($warehouse)->branch_id ?? $warehouseId ?? $companyId;

        return [$warehouseId, $branchId];
    }
}
