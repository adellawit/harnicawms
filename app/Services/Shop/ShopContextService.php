<?php

namespace App\Services\Shop;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\ProductPriceList;
use RuntimeException;

class ShopContextService
{
    protected ?BusinessUnit $branch = null;

    protected ?BusinessUnit $company = null;

    public function __construct(
        protected Customer $customer,
    ) {}

    public function customer(): Customer
    {
        return $this->customer->loadMissing('customerGroup');
    }

    public function branchId(): ?string
    {
        return $this->customer->getBranchId();
    }

    public function branch(): ?BusinessUnit
    {
        $branchId = $this->branchId();
        if (! $branchId) {
            return null;
        }

        return $this->branch ??= BusinessUnit::find($branchId);
    }

    public function companyId(): ?string
    {
        $branch = $this->branch();
        if (! $branch) {
            return null;
        }

        return match ($branch->type_code) {
            'COMPANY' => $branch->id,
            'BRANCH' => $branch->parent_id,
            default => null,
        };
    }

    public function company(): ?BusinessUnit
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return null;
        }

        return $this->company ??= BusinessUnit::find($companyId);
    }

    public function companyName(): string
    {
        $company = $this->company();

        return $company?->brand_name
            ?: $company?->name
            ?: (string) config('shop.default_company_name', config('app.name'));
    }

    /**
     * Contoh: Bandung (take out)
     */
    public function branchDisplayLabel(): ?string
    {
        $branch = $this->branch();
        if (! $branch) {
            return null;
        }

        $channel = trim((string) config('shop.channel_label', ''));
        $branchName = $branch->brand_name ?: $branch->name;

        if ($channel === '') {
            return $branchName;
        }

        return $branchName.' ('.$channel.')';
    }

    public function priceListId(): string
    {
        $companyId = $this->companyId();
        $branchId = $this->branchId();

        $priceList = ProductPriceList::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->forBusinessContext($companyId, $branchId)
            ->where('code', 'REGULER')
            ->first()
            ?? ProductPriceList::query()
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->forBusinessContext($companyId, $branchId)
                ->orderBy('sort_order')
                ->first();

        if (! $priceList) {
            throw new RuntimeException('Price list tidak tersedia untuk cabang ini.');
        }

        return $priceList->id;
    }

    public function taxRate(): float
    {
        return (float) config('shop.tax_rate', 11);
    }

    public function assertReady(): void
    {
        if (! $this->branchId()) {
            throw new RuntimeException('Customer tidak terhubung ke cabang.');
        }
    }
}
