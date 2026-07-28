<?php

namespace App\Repositories;

use App\Models\Partner\Agent;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AgentCuttingPriceReportRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateSummary(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->summaryQuery($filters)
            ->orderBy('compliance_percent')
            ->orderByDesc('total_gap_amount')
            ->orderBy('agent_code')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function summaryRows(array $filters): Collection
    {
        return $this->summaryQuery($filters)
            ->orderBy('compliance_percent')
            ->orderByDesc('total_gap_amount')
            ->orderBy('agent_code')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateDetails(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->detailQuery($filters)
            ->orderByDesc('sales_date')
            ->orderByDesc('sales_number')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function detailRows(array $filters): Collection
    {
        return $this->detailQuery($filters)
            ->orderByDesc('sales_date')
            ->orderByDesc('sales_number')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *   total_agents: int,
     *   agents_violating: int,
     *   transactions_violating: int,
     *   margin_loss: float
     * }
     */
    public function kpis(array $filters): array
    {
        $totalAgentsQuery = Agent::query()->whereNull('deleted_at');
        if (! empty($filters['agent_id'])) {
            $totalAgentsQuery->whereKey($filters['agent_id']);
        }
        $totalAgents = (int) $totalAgentsQuery->count();

        $cutting = $this->cuttingOnlyQuery($filters);

        return [
            'total_agents' => $totalAgents,
            'agents_violating' => (int) DB::query()
                ->fromSub(
                    DB::query()
                        ->fromRaw('('.$this->pricedLineSql($filters).') as lines', $this->lineBindings($filters))
                        ->where('is_cutting', true)
                        ->select('agent_id')
                        ->distinct(),
                    'violators'
                )
                ->count(),
            'transactions_violating' => (int) DB::query()
                ->fromSub(
                    DB::query()
                        ->fromRaw('('.$this->pricedLineSql($filters).') as lines', $this->lineBindings($filters))
                        ->where('is_cutting', true)
                        ->select('sales_order_id')
                        ->distinct(),
                    'trx'
                )
                ->count(),
            'margin_loss' => (float) (clone $cutting)->sum('gap_amount'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function summaryQuery(array $filters): Builder
    {
        $lineSql = $this->pricedLineSql($filters);
        $bindings = $this->lineBindings($filters);

        $sql = "
            SELECT
                d.agent_id,
                d.agent_code,
                d.agent_name,
                COUNT(*)::int AS total_items,
                COUNT(*) FILTER (WHERE d.is_cutting)::int AS cutting_items,
                COUNT(DISTINCT d.sales_order_id)::int AS transaction_count,
                COUNT(DISTINCT d.sales_order_id) FILTER (WHERE d.is_cutting)::int AS transactions_violating,
                COALESCE(SUM(d.quantity) FILTER (WHERE d.is_cutting), 0) AS total_qty,
                COALESCE(SUM(d.gap_amount) FILTER (WHERE d.is_cutting), 0) AS total_gap_amount,
                COALESCE(AVG(d.gap_percent) FILTER (WHERE d.is_cutting), 0) AS avg_gap_percent,
                CASE
                    WHEN COUNT(*) = 0 THEN 100
                    ELSE ROUND((
                        (COUNT(*) - COUNT(*) FILTER (WHERE d.is_cutting))::numeric
                        / COUNT(*)::numeric * 100
                    ), 2)
                END AS compliance_percent
            FROM ({$lineSql}) d
            GROUP BY d.agent_id, d.agent_code, d.agent_name
        ";

        return DB::query()
            ->fromRaw("({$sql}) as summary", $bindings)
            ->select('*');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function detailQuery(array $filters): Builder
    {
        return $this->cuttingOnlyQuery($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function cuttingOnlyQuery(array $filters): Builder
    {
        $minGapPercent = (float) ($filters['min_gap_percent'] ?? 0);

        $query = DB::query()
            ->fromRaw('('.$this->pricedLineSql($filters).') as detail', $this->lineBindings($filters))
            ->where('is_cutting', true)
            ->select('*');

        if ($minGapPercent > 0) {
            $query->where('gap_percent', '>=', $minGapPercent);
        }

        return $query;
    }

    /**
     * All comparable Agent/Reseller paid lines vs REGULER (includes non-cutting).
     *
     * @param  array<string, mixed>  $filters
     */
    private function pricedLineSql(array $filters): string
    {
        $sql = "
            SELECT
                so.id AS sales_order_id,
                so.sales_number,
                so.sales_date,
                so.branch_id,
                soi.id AS sales_order_item_id,
                soi.product_id,
                soi.product_variant_id,
                soi.unit_id,
                soi.quantity,
                soi.unit_price AS agent_unit_price,
                CASE
                    WHEN soi.quantity > 0 THEN ROUND((soi.subtotal / soi.quantity)::numeric, 4)
                    ELSE soi.unit_price
                END AS agent_net_price,
                pvp.selling_price AS distributor_price,
                GREATEST(
                    pvp.selling_price - CASE
                        WHEN soi.quantity > 0 THEN ROUND((soi.subtotal / soi.quantity)::numeric, 4)
                        ELSE soi.unit_price
                    END,
                    0
                ) AS gap_unit,
                (
                    GREATEST(
                        pvp.selling_price - CASE
                            WHEN soi.quantity > 0 THEN ROUND((soi.subtotal / soi.quantity)::numeric, 4)
                            ELSE soi.unit_price
                        END,
                        0
                    ) * soi.quantity
                ) AS gap_amount,
                CASE
                    WHEN pvp.selling_price > 0 THEN ROUND((
                        GREATEST(
                            pvp.selling_price - CASE
                                WHEN soi.quantity > 0 THEN ROUND((soi.subtotal / soi.quantity)::numeric, 4)
                                ELSE soi.unit_price
                            END,
                            0
                        ) / pvp.selling_price * 100
                    )::numeric, 2)
                    ELSE 0
                END AS gap_percent,
                (
                    CASE
                        WHEN soi.quantity > 0 THEN ROUND((soi.subtotal / soi.quantity)::numeric, 4)
                        ELSE soi.unit_price
                    END
                ) < pvp.selling_price AS is_cutting,
                p.code AS product_code,
                p.name AS product_name,
                pv.sku AS variant_sku,
                pu.name AS unit_name,
                pu.symbol AS unit_symbol,
                c.id AS customer_id,
                c.code AS customer_code,
                c.name AS customer_name,
                c.customer_type,
                COALESCE(a.id, ra.id) AS agent_id,
                COALESCE(a.code, ra.code) AS agent_code,
                COALESCE(a.name, ra.name) AS agent_name,
                r.code AS reseller_code,
                r.name AS reseller_name
            FROM transaction.sales_order_items soi
            INNER JOIN transaction.sales_orders so
                ON so.id = soi.sales_order_id
               AND so.deleted_at IS NULL
               AND so.payment_status = 'paid'
            INNER JOIN customer.customers c
                ON c.id = so.customer_id
               AND c.deleted_at IS NULL
            LEFT JOIN partner.agents a
                ON a.customer_id = c.id
               AND a.deleted_at IS NULL
            LEFT JOIN partner.resellers r
                ON r.customer_id = c.id
               AND r.deleted_at IS NULL
            LEFT JOIN partner.agents ra
                ON ra.id = r.agent_id
               AND ra.deleted_at IS NULL
            INNER JOIN product.products p
                ON p.id = soi.product_id
               AND p.deleted_at IS NULL
            LEFT JOIN product.product_variants pv
                ON pv.id = soi.product_variant_id
               AND pv.deleted_at IS NULL
            LEFT JOIN product.product_units pu
                ON pu.id = soi.unit_id
               AND pu.deleted_at IS NULL
            INNER JOIN product.product_price_lists pl
                ON pl.code = 'REGULER'
               AND pl.deleted_at IS NULL
               AND pl.is_active = true
            INNER JOIN product.product_variant_prices pvp
                ON pvp.variant_id = soi.product_variant_id
               AND pvp.unit_id = soi.unit_id
               AND pvp.price_list_id = pl.id
               AND pvp.deleted_at IS NULL
            WHERE soi.deleted_at IS NULL
              AND COALESCE(soi.is_promo_free, false) = false
              AND soi.quantity > 0
              AND soi.product_variant_id IS NOT NULL
              AND COALESCE(a.id, ra.id) IS NOT NULL
              AND so.sales_date >= ?
              AND so.sales_date <= ?
        ";

        if (! empty($filters['agent_id'])) {
            $sql .= ' AND COALESCE(a.id, ra.id) = ?';
        }
        if (! empty($filters['product_id'])) {
            $sql .= ' AND soi.product_id = ?';
        }
        if (! empty($filters['variant_id'])) {
            $sql .= ' AND soi.product_variant_id = ?';
        }
        if (! empty($filters['branch_id'])) {
            $sql .= ' AND so.branch_id = ?';
        }

        return $sql;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<mixed>
     */
    private function lineBindings(array $filters): array
    {
        $bindings = [
            $filters['date_from'],
            $filters['date_to'],
        ];

        if (! empty($filters['agent_id'])) {
            $bindings[] = $filters['agent_id'];
        }
        if (! empty($filters['product_id'])) {
            $bindings[] = $filters['product_id'];
        }
        if (! empty($filters['variant_id'])) {
            $bindings[] = $filters['variant_id'];
        }
        if (! empty($filters['branch_id'])) {
            $bindings[] = $filters['branch_id'];
        }

        return $bindings;
    }
}
