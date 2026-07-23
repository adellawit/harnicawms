<?php

namespace App\Support;

use App\Models\BusinessUnit;
use App\Models\Partner\Agent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Helper konteks rantai distribusi: menentukan Distributor (company),
 * daftar Agen (branch), dan opsi varian produk untuk dropdown.
 */
class WmsContext
{
    /**
     * Business unit Distributor (level COMPANY) untuk user saat ini.
     */
    public static function distributor(): ?BusinessUnit
    {
        $user = Auth::user();
        $companyId = $user instanceof User ? $user->getCompanyIdForProduct() : null;

        if ($companyId) {
            $bu = BusinessUnit::find($companyId);
            if ($bu) {
                return $bu;
            }
        }

        // Fallback: company pertama
        return BusinessUnit::where('type_code', 'COMPANY')->whereNull('deleted_at')->orderBy('created_at')->first();
    }

    /**
     * Gudang WIP (penerimaan bahan baku & pemrosesan) milik Distributor.
     */
    public static function wipWarehouse(?string $distributorId = null): ?Warehouse
    {
        return self::warehouseByType('WIP', $distributorId)
            ?? self::warehouseByCode('WH-WIP', $distributorId);
    }

    /**
     * Gudang Barang Jadi milik Distributor.
     */
    public static function finishedGoodsWarehouse(?string $distributorId = null): ?Warehouse
    {
        return self::warehouseByType('FG', $distributorId)
            ?? self::warehouseByCode('WH-FG', $distributorId);
    }

    /**
     * Daftar gudang untuk company/cabang konteks.
     *
     * @return \Illuminate\Support\Collection<int, Warehouse>
     */
    public static function warehouses(?string $contextId = null)
    {
        $authUser = Auth::user();
        $contextId = $contextId ?: ($authUser instanceof User ? $authUser->getBranchIdForTransaction() : null)
            ?: optional(self::distributor())->id;
        $businessUnit = $contextId ? BusinessUnit::find($contextId) : null;

        $query = Warehouse::inventoryActive();

        if ($businessUnit?->type_code === 'BRANCH') {
            $query->forBranchAccess($businessUnit->id);
        } else {
            $companyId = $businessUnit?->type_code === 'COMPANY'
                ? $businessUnit->id
                : optional(self::distributor())->id;

            $query->forCompany($companyId);
        }

        return $query
            ->orderBy('code')
            ->get();
    }

    /**
     * Gudang yang boleh dilihat user (cabang + gudang company WIP/FG + assignment).
     *
     * @return Collection<int, Warehouse>
     */
    public static function accessibleWarehouses(?User $user = null): Collection
    {
        $user ??= Auth::user();
        if (! $user) {
            return collect();
        }

        $accessibleIds = $user->getAccessibleBusinessUnitIdsForQuery();
        $companyIds = self::companyIdsForUser($user);

        return Warehouse::query()
            ->with('branch:id,name')
            ->inventoryActive()
            ->when(empty($accessibleIds) && empty($companyIds), fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->when(! empty($accessibleIds) || ! empty($companyIds), function (Builder $q) use ($accessibleIds, $companyIds) {
                $q->where(function (Builder $inner) use ($accessibleIds, $companyIds) {
                    if (! empty($accessibleIds)) {
                        $inner->whereIn('branch_id', $accessibleIds)
                            ->orWhereHas(
                                'assignedBranches',
                                fn (Builder $assigned) => $assigned->whereIn('master_data.business_units.id', $accessibleIds)
                            );
                    }

                    if (! empty($companyIds)) {
                        $inner->orWhereIn('company_id', $companyIds);
                    }
                });
            })
            ->orderBy('code')
            ->get();
    }

    /**
     * @return list<string>
     */
    public static function accessibleWarehouseIds(?User $user = null): array
    {
        return self::accessibleWarehouses($user)->pluck('id')->all();
    }

    /**
     * @return list<string>
     */
    protected static function companyIdsForUser(User $user): array
    {
        $businessUnit = $user->businessUnit;
        if (! $businessUnit) {
            return [];
        }

        return match ($businessUnit->type_code) {
            'HOLDING' => BusinessUnit::query()
                ->where('type_code', 'COMPANY')
                ->whereNull('deleted_at')
                ->pluck('id')
                ->all(),
            'COMPANY' => [$businessUnit->id],
            'BRANCH' => $businessUnit->parent_id ? [$businessUnit->parent_id] : [],
            default => [],
        };
    }

    public static function defaultWarehouse(?string $branchId = null): ?Warehouse
    {
        $authUser = Auth::user();
        $branchId = $branchId ?: ($authUser instanceof User ? $authUser->getBranchIdForTransaction() : null);

        if (! $branchId) {
            return null;
        }

        return Warehouse::defaultForBranch($branchId);
    }

    public static function defaultAgentWarehouse(?string $agentId = null): ?Warehouse
    {
        if (! $agentId) {
            return null;
        }

        $agent = Agent::with('defaultWarehouse')->find($agentId);

        return $agent?->defaultWarehouse ?: Warehouse::defaultForAgent($agentId);
    }

    protected static function warehouseByCode(string $code, ?string $distributorId): ?Warehouse
    {
        $distributorId = $distributorId ?: optional(self::distributor())->id;

        return Warehouse::inventoryActive()
            ->where('code', $code)
            ->when($distributorId, fn ($q) => $q->where('company_id', $distributorId))
            ->first()
            ?? Warehouse::inventoryActive()->where('code', $code)->first();
    }

    protected static function warehouseByType(string $typeCode, ?string $distributorId): ?Warehouse
    {
        $distributorId = $distributorId ?: optional(self::distributor())->id;

        return Warehouse::inventoryActive()
            ->where('warehouse_type_code', $typeCode)
            ->when($distributorId, fn ($q) => $q->where('company_id', $distributorId))
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->first();
    }

    /**
     * Daftar Agen partner di bawah Distributor.
     *
     * @return \Illuminate\Support\Collection<int, Agent>
     */
    public static function agents(?string $distributorId = null)
    {
        $distributorId = $distributorId ?: optional(self::distributor())->id;

        return Agent::active()
            ->when($distributorId, fn ($q) => $q->where('company_id', $distributorId))
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    /**
     * Opsi varian produk untuk dropdown.
     *
     * @param  string|null  $natureCode  filter berdasarkan kode nature (RAW_MATERIAL, FINISHED_GOOD, dst)
     * @return array<int, array{id:string,label:string,product_id:string,unit_id:?string,nature:?string}>
     */
    public static function variantOptions(?string $natureCode = null): array
    {
        $query = ProductVariant::query()
            ->with(['product.nature', 'product.defaultUnit'])
            ->whereHas('product', function ($q) use ($natureCode) {
                $q->where('is_stock_item', true)->whereNull('deleted_at');
                if ($natureCode) {
                    $q->whereHas('nature', fn ($n) => $n->where('code', $natureCode));
                }
            })
            ->whereNull('deleted_at')
            ->orderBy('created_at');

        return $query->get()->map(function (ProductVariant $v) {
            $product = $v->product;
            return [
                'id' => $v->id,
                'label' => $v->display_name,
                'product_id' => $v->product_id,
                'unit_id' => $product?->default_unit_id,
                'unit_label' => $product?->defaultUnit?->code,
                'nature' => $product?->nature?->code,
            ];
        })->filter(fn ($o) => ! empty($o['unit_id']))->values()->all();
    }

    /**
     * Cari produk + varian default untuk sebuah nature (dipakai seeder/util).
     */
    public static function firstVariantOf(string $natureCode): ?ProductVariant
    {
        return ProductVariant::whereHas('product.nature', fn ($n) => $n->where('code', $natureCode))->first();
    }
}
