<?php

namespace App\Services\Telegram;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\ProductPriceList;
use App\Models\User;
use Illuminate\Support\Str;

class TelegramCustomerService
{
    /**
     * @return array<int, Customer>
     */
    public function search(string $name, string $branchId, int $limit = 5): array
    {
        $keyword = trim($name);

        if ($keyword === '') {
            return [];
        }

        return Customer::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('name', 'ilike', '%'.$keyword.'%')
            ->whereHas('customerGroup', fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * @return array{customer_id: string, customer_name: string, created: bool}
     */
    public function resolveOrCreate(string $name, string $branchId, ?string $companyId, User $user): array
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return [
                'customer_id' => '',
                'customer_name' => 'Walk-in Customer',
                'created' => false,
            ];
        }

        $matches = $this->search($trimmed, $branchId, 1);
        $exact = collect($matches)->first(
            fn (Customer $c) => mb_strtolower($c->name) === mb_strtolower($trimmed)
        );

        if ($exact !== null) {
            return [
                'customer_id' => $exact->id,
                'customer_name' => $exact->name,
                'created' => false,
            ];
        }

        if (count($matches) === 1) {
            return [
                'customer_id' => $matches[0]->id,
                'customer_name' => $matches[0]->name,
                'created' => false,
            ];
        }

        $priceListId = $this->resolveDefaultPriceListId($branchId, $companyId);
        $groupId = $this->resolveDefaultCustomerGroupId($branchId, $priceListId);
        $code = $this->generateCustomerCode($trimmed, $groupId);

        $customer = Customer::create([
            'customer_group_id' => $groupId,
            'code' => $code,
            'name' => $trimmed,
            'customer_type' => 'individual',
            'is_active' => true,
            'has_app_access' => false,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'created' => true,
        ];
    }

    protected function resolveDefaultPriceListId(string $branchId, ?string $companyId): string
    {
        $priceList = ProductPriceList::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->forBusinessContext($companyId, $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();

        if ($priceList !== null) {
            return $priceList->id;
        }

        $fallback = ProductPriceList::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('code', 'REGULER')
            ->value('id');

        if ($fallback === null) {
            throw new \RuntimeException('Price list default tidak ditemukan.');
        }

        return $fallback;
    }

    protected function resolveDefaultCustomerGroupId(string $branchId, string $priceListId): string
    {
        $code = config('telegram.default_customer_group_code', 'UMUM');

        $group = CustomerGroup::query()
            ->where('branch_id', $branchId)
            ->where('code', $code)
            ->first();

        if ($group !== null) {
            return $group->id;
        }

        $group = CustomerGroup::create([
            'branch_id' => $branchId,
            'code' => $code,
            'name' => 'Umum',
            'description' => 'Pelanggan umum — auto-created via Telegram POS',
            'price_list_id' => $priceListId,
            'default_discount' => 0,
            'allow_credit' => false,
            'payment_term_days' => 0,
            'earn_point' => true,
            'point_multiplier' => 1,
            'sort_order' => 20,
            'is_active' => true,
        ]);

        return $group->id;
    }

    protected function generateCustomerCode(string $name, string $customerGroupId): string
    {
        $base = Str::upper(Str::slug($name, '-'));

        if ($base === '') {
            $base = 'TG-CUSTOMER';
        }

        $base = Str::limit($base, 36, '');
        $code = $base;
        $suffix = 1;

        while (Customer::query()
            ->where('customer_group_id', $customerGroupId)
            ->where('code', $code)
            ->exists()) {
            $code = Str::limit($base, 30, '').'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
