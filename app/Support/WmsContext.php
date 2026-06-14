<?php

namespace App\Support;

use App\Models\BusinessUnit;
use App\Models\Product;
use App\Models\ProductVariant;
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
        $companyId = $user?->getCompanyIdForProduct();

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
     * Daftar Agen (branch) di bawah Distributor.
     *
     * @return \Illuminate\Support\Collection<int, BusinessUnit>
     */
    public static function agents(?string $distributorId = null)
    {
        $distributorId = $distributorId ?: optional(self::distributor())->id;

        return BusinessUnit::where('type_code', 'BRANCH')
            ->when($distributorId, fn ($q) => $q->where('parent_id', $distributorId))
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
