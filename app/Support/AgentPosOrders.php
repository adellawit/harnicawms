<?php

namespace App\Support;

use App\Models\Partner\Agent;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AgentPosOrders
{
    public static function warehouseIdForAgent(Agent $agent): ?string
    {
        return optional(WmsContext::defaultAgentWarehouse($agent->id))->id
            ?: $agent->default_warehouse_id;
    }

    public static function query(?string $branchId, ?string $warehouseId): Builder
    {
        return SalesOrder::query()
            ->where('order_type', 'agent-pos')
            ->when($branchId, fn (Builder $q) => $q->where('branch_id', $branchId))
            ->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId));
    }

    public static function unpaidResellerOrdersQuery(Agent $agent, ?string $branchId, ?string $warehouseId): Builder
    {
        return self::query($branchId, $warehouseId)
            ->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled'])
            ->whereIn(
                'customer_id',
                $agent->resellers()->whereNotNull('customer_id')->select('customer_id')
            );
    }

    public static function unpaidResellerCount(Agent $agent, ?string $branchId, ?string $warehouseId): int
    {
        $key = 'agent-pos-unpaid-reseller-count:'.$agent->id.':'.$branchId.':'.$warehouseId;

        return (int) self::remember($key, fn () => self::unpaidResellerOrdersQuery($agent, $branchId, $warehouseId)->count());
    }

    /**
     * @return Collection<string, int>
     */
    public static function unpaidCountByCustomer(Agent $agent, ?string $branchId, ?string $warehouseId): Collection
    {
        $key = 'agent-pos-unpaid-by-customer:'.$agent->id.':'.$branchId.':'.$warehouseId;

        return self::remember($key, function () use ($agent, $branchId, $warehouseId) {
            return self::unpaidResellerOrdersQuery($agent, $branchId, $warehouseId)
                ->selectRaw('customer_id, count(*) as unpaid_count')
                ->groupBy('customer_id')
                ->pluck('unpaid_count', 'customer_id');
        });
    }

    protected static function remember(string $key, \Closure $resolve): mixed
    {
        if (! app()->bound('request')) {
            return $resolve();
        }

        $request = request();
        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, $resolve());
        }

        return $request->attributes->get($key);
    }
}
