<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerMembershipPoint;
use App\Models\MembershipPointConfiguration;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MembershipPointService
{
    private static ?bool $pointsSchemaReady = null;

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

        $config = MembershipPointConfiguration::query()
            ->where('branch_id', $order->branch_id)
            ->whereNull('deleted_at')
            ->where('is_default', true)
            ->first();

        if (! $config) {
            $config = MembershipPointConfiguration::query()
                ->whereNull('deleted_at')
                ->where('is_default', true)
                ->first();
        }

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
                    'description' => 'Membership points from sales order ' . $order->sales_number,
                    'created_by' => $userId ?? $order->created_by,
                    'updated_by' => $userId ?? $order->created_by,
                ]);

                Customer::where('id', $customer->id)->update([
                    'points_balance' => DB::raw('COALESCE(points_balance, 0) + ' . $points),
                    'total_points_earned' => DB::raw('COALESCE(total_points_earned, 0) + ' . $points),
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
}

