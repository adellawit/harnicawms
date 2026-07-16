<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerMembershipPoint;
use App\Models\MembershipPointConfiguration;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MembershipPointService
{
    private static ?bool $pointsSchemaReady = null;

    public function resolveDefaultConfig(?string $branchId): ?MembershipPointConfiguration
    {
        if (! $this->isPointsSchemaReady()) {
            return null;
        }

        $config = null;
        if ($branchId) {
            $config = MembershipPointConfiguration::query()
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->where('is_default', true)
                ->first();
        }

        if (! $config) {
            $config = MembershipPointConfiguration::query()
                ->whereNull('deleted_at')
                ->where('is_default', true)
                ->first();
        }

        return $config;
    }

    /**
     * @return array{points: int, discount_amount: float, redeem_value_per_point: int}
     */
    public function normalizeRedeemRequest(
        ?string $customerId,
        ?string $branchId,
        int $requestedPoints,
        float $maxDiscountAllowed
    ): array {
        $empty = ['points' => 0, 'discount_amount' => 0.0, 'redeem_value_per_point' => 0];

        if (! $this->isRedeemSchemaReady() || ! $customerId || $requestedPoints <= 0 || $maxDiscountAllowed <= 0) {
            return $empty;
        }

        $config = $this->resolveDefaultConfig($branchId);
        $valuePerPoint = (int) ($config?->redeem_value_per_point ?? 0);
        if (! $config || $valuePerPoint <= 0) {
            return $empty;
        }

        $customer = Customer::query()->find($customerId, ['id', 'points_balance']);
        if (! $customer) {
            return $empty;
        }

        $balance = max(0, (int) ($customer->points_balance ?? 0));
        $maxByBalance = $balance;
        $maxByPayable = (int) floor($maxDiscountAllowed / $valuePerPoint);
        $points = min($requestedPoints, $maxByBalance, $maxByPayable);

        if ($points <= 0) {
            return $empty;
        }

        return [
            'points' => $points,
            'discount_amount' => round($points * $valuePerPoint, 4),
            'redeem_value_per_point' => $valuePerPoint,
        ];
    }

    public function redeemForPaidOrder(SalesOrder $order, int $points, float $discountAmount, ?string $userId = null): int
    {
        if (! $this->isRedeemSchemaReady() || $points <= 0 || $discountAmount <= 0 || ! $order->customer_id) {
            return 0;
        }

        $config = $this->resolveDefaultConfig($order->branch_id);

        try {
            return (int) DB::transaction(function () use ($order, $points, $discountAmount, $config, $userId) {
                $customer = Customer::query()->lockForUpdate()->find($order->customer_id);
                if (! $customer) {
                    return 0;
                }

                $existing = CustomerMembershipPoint::query()
                    ->where('sales_order_id', $order->id)
                    ->where('type', 'redeem')
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return (int) $existing->points;
                }

                $balance = max(0, (int) ($customer->points_balance ?? 0));
                if ($balance < $points) {
                    throw new \RuntimeException(
                        'Saldo poin tidak cukup. Tersedia: '.$balance.', diminta: '.$points.'.'
                    );
                }

                $valuePerPoint = (int) ($config?->redeem_value_per_point ?? 0);
                if ($valuePerPoint > 0) {
                    $discountAmount = round($points * $valuePerPoint, 4);
                }

                CustomerMembershipPoint::create([
                    'customer_id' => $customer->id,
                    'branch_id' => $order->branch_id,
                    'sales_order_id' => $order->id,
                    'membership_configuration_id' => $config?->id,
                    'type' => 'redeem',
                    'points' => $points,
                    'reference' => $order->sales_number,
                    'description' => 'Redeem membership points on sales order '.$order->sales_number,
                    'created_by' => $userId ?? $order->created_by,
                    'updated_by' => $userId ?? $order->created_by,
                ]);

                Customer::where('id', $customer->id)->update([
                    'points_balance' => DB::raw('GREATEST(COALESCE(points_balance, 0) - '.$points.', 0)'),
                    'total_points_redeemed' => DB::raw('COALESCE(total_points_redeemed, 0) + '.$points),
                    'updated_by' => $userId ?? $order->created_by,
                ]);

                SalesOrder::where('id', $order->id)->update([
                    'membership_points_redeemed' => $points,
                    'membership_redeem_discount_amount' => $discountAmount,
                    'membership_configuration_id' => $config?->id ?? $order->membership_configuration_id,
                    'updated_by' => $userId ?? $order->created_by,
                ]);

                return $points;
            });
        } catch (\Throwable $e) {
            Log::warning('Membership point redeem skipped due runtime issue', [
                'sales_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function awardForPaidOrder(SalesOrder $order, ?string $userId = null): int
    {
        if (! $this->isPointsSchemaReady()) {
            return 0;
        }

        if (! $order->customer_id || (float) $order->total <= 0) {
            return 0;
        }

        $customer = Customer::with('customerGroup')->find($order->customer_id);
        if (! $customer) {
            return 0;
        }

        $customerGroup = $customer->customerGroup;
        if ($customerGroup && ! (bool) $customerGroup->earn_point) {
            return 0;
        }

        $config = $this->resolveDefaultConfig($order->branch_id);

        if (! $config || (int) $config->transaction_amount_step <= 0 || (int) $config->points_per_step <= 0) {
            return 0;
        }

        $basePoints = (int) floor(((float) $order->total) / (int) $config->transaction_amount_step) * (int) $config->points_per_step;
        if ($basePoints <= 0) {
            return 0;
        }

        $multiplier = (float) ($customerGroup?->point_multiplier ?? 1);
        $multiplier = $multiplier > 0 ? $multiplier : 1;
        $points = (int) floor($basePoints * $multiplier);

        if ($points <= 0) {
            return 0;
        }

        try {
            DB::transaction(function () use ($order, $customer, $config, $points, $userId) {
                $existing = CustomerMembershipPoint::query()
                    ->where('sales_order_id', $order->id)
                    ->where('type', 'earn')
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return;
                }

                CustomerMembershipPoint::create([
                    'customer_id' => $customer->id,
                    'branch_id' => $order->branch_id,
                    'sales_order_id' => $order->id,
                    'membership_configuration_id' => $config->id,
                    'type' => 'earn',
                    'points' => $points,
                    'reference' => $order->sales_number,
                    'description' => 'Membership points from sales order '.$order->sales_number,
                    'created_by' => $userId ?? $order->created_by,
                    'updated_by' => $userId ?? $order->created_by,
                ]);

                Customer::where('id', $customer->id)->update([
                    'points_balance' => DB::raw('COALESCE(points_balance, 0) + '.$points),
                    'total_points_earned' => DB::raw('COALESCE(total_points_earned, 0) + '.$points),
                    'updated_by' => $userId ?? $order->created_by,
                ]);

                SalesOrder::where('id', $order->id)->update([
                    'membership_points_earned' => $points,
                    'membership_configuration_id' => $config->id,
                    'updated_by' => $userId ?? $order->created_by,
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('Membership point awarding skipped due runtime issue', [
                'sales_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        return $points;
    }

    private function isPointsSchemaReady(): bool
    {
        if (self::$pointsSchemaReady !== null) {
            return self::$pointsSchemaReady;
        }

        try {
            self::$pointsSchemaReady =
                Schema::hasTable('crm.customer_membership_points')
                && Schema::hasColumns('customer.customers', ['points_balance', 'total_points_earned'])
                && Schema::hasColumns('transaction.sales_orders', ['membership_points_earned', 'membership_configuration_id']);
        } catch (\Throwable $e) {
            self::$pointsSchemaReady = false;
        }

        return self::$pointsSchemaReady;
    }

    private function isRedeemSchemaReady(): bool
    {
        if (! $this->isPointsSchemaReady()) {
            return false;
        }

        try {
            return Schema::hasColumns('customer.customers', ['total_points_redeemed'])
                && Schema::hasColumns('transaction.sales_orders', [
                    'membership_points_redeemed',
                    'membership_redeem_discount_amount',
                ])
                && Schema::hasColumn('crm.membership_point_configurations', 'redeem_value_per_point');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
