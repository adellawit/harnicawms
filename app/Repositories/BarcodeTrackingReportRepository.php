<?php

namespace App\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BarcodeTrackingReportRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query($filters)
            ->orderByDesc('d.dispatched_at')
            ->orderBy('pls.serial_number')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function rows(array $filters): Collection
    {
        return $this->query($filters)
            ->orderByDesc('d.dispatched_at')
            ->orderBy('pls.serial_number')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{serials: int, orders: int, products: int, agents: int}
     */
    public function kpis(array $filters): array
    {
        $query = $this->query($filters);

        return [
            'serials' => (int) (clone $query)->count('a.id'),
            'orders' => (int) (clone $query)->distinct()->count('so.id'),
            'products' => (int) (clone $query)->distinct()->count('p.id'),
            'agents' => (int) (clone $query)
                ->whereNotNull(DB::raw('COALESCE(direct_agent.id, parent_agent.id)'))
                ->distinct()
                ->count(DB::raw('COALESCE(direct_agent.id, parent_agent.id)')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(array $filters): Builder
    {
        return DB::table('transaction.sales_order_item_serial_assignments as a')
            ->join('transaction.sales_order_barcode_dispatches as d', 'd.id', '=', 'a.dispatch_id')
            ->join('transaction.sales_order_items as soi', 'soi.id', '=', 'a.sales_order_item_id')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'd.sales_order_id')
            ->join('product.product_label_serials as pls', 'pls.id', '=', 'a.product_label_serial_id')
            ->join('product.products as p', 'p.id', '=', 'soi.product_id')
            ->leftJoin('product.product_variants as pv', 'pv.id', '=', 'soi.product_variant_id')
            ->leftJoin('product.product_units as pu', 'pu.id', '=', 'soi.unit_id')
            ->leftJoin('customer.customers as c', 'c.id', '=', 'so.customer_id')
            ->leftJoin('partner.agents as direct_agent', function ($join): void {
                $join->on('direct_agent.customer_id', '=', 'c.id')
                    ->whereNull('direct_agent.deleted_at');
            })
            ->leftJoin('partner.resellers as r', function ($join): void {
                $join->on('r.customer_id', '=', 'c.id')
                    ->whereNull('r.deleted_at');
            })
            ->leftJoin('partner.agents as parent_agent', function ($join): void {
                $join->on('parent_agent.id', '=', 'r.agent_id')
                    ->whereNull('parent_agent.deleted_at');
            })
            ->leftJoin('master_data.business_units as b', 'b.id', '=', 'so.branch_id')
            ->leftJoin('auth.users as u', 'u.id', '=', 'a.scanned_by')
            ->where('d.status', 'completed')
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('d.dispatched_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('d.dispatched_at', '<=', $date))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, string $id) => $query->where('so.branch_id', $id))
            ->when($filters['agent_id'] ?? null, function (Builder $query, string $id): void {
                $query->whereRaw('COALESCE(direct_agent.id, parent_agent.id) = ?', [$id]);
            })
            ->when($filters['customer_id'] ?? null, fn (Builder $query, string $id) => $query->where('so.customer_id', $id))
            ->when($filters['product_id'] ?? null, fn (Builder $query, string $id) => $query->where('soi.product_id', $id))
            ->when($filters['variant_id'] ?? null, fn (Builder $query, string $id) => $query->where('soi.product_variant_id', $id))
            ->when($filters['unit_id'] ?? null, fn (Builder $query, string $id) => $query->where('soi.unit_id', $id))
            ->when($filters['serial'] ?? null, fn (Builder $query, string $value) => $query->where('pls.serial_number', 'ilike', "%{$value}%"))
            ->when($filters['sales_number'] ?? null, fn (Builder $query, string $value) => $query->where('so.sales_number', 'ilike', "%{$value}%"))
            ->select([
                'a.id',
                'a.scanned_at',
                'pls.serial_number',
                'pls.unit_level',
                'p.id as product_id',
                'p.code as product_code',
                'p.name as product_name',
                'pv.id as variant_id',
                'pv.sku as variant_sku',
                'pu.id as unit_id',
                'pu.name as unit_name',
                'pu.symbol as unit_symbol',
                'so.id as sales_order_id',
                'so.sales_number',
                'so.sales_date',
                'so.customer_id',
                'so.customer_name',
                'so.branch_id',
                'b.name as branch_name',
                'd.dispatched_at',
                'r.id as reseller_id',
                'r.code as reseller_code',
                'r.name as reseller_name',
                DB::raw('COALESCE(direct_agent.id, parent_agent.id) as agent_id'),
                DB::raw('COALESCE(direct_agent.code, parent_agent.code) as agent_code'),
                DB::raw('COALESCE(direct_agent.name, parent_agent.name) as agent_name'),
                'u.first_name as scanned_by_first_name',
                'u.last_name as scanned_by_last_name',
            ]);
    }
}
