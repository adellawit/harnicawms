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
     * All comparable Agent/Reseller paid lines vs cutting price MAP floor
     * (partner.cutting_price_configs), with sold-unit → config-unit price conversion.
     *
     * @param  array<string, mixed>  $filters
     */
    private function pricedLineSql(array $filters): string
    {
        $sql = "
            WITH RECURSIVE conv_edges AS (
                SELECT
                    c.product_id,
                    c.from_unit_id AS src,
                    c.to_unit_id AS dst,
                    c.conversion_factor::numeric AS factor
                FROM product.product_unit_conversions c
                WHERE c.deleted_at IS NULL
                UNION ALL
                SELECT
                    c.product_id,
                    c.to_unit_id AS src,
                    c.from_unit_id AS dst,
                    CASE
                        WHEN c.conversion_factor > 0 THEN 1.0 / c.conversion_factor::numeric
                        ELSE NULL
                    END AS factor
                FROM product.product_unit_conversions c
                WHERE c.deleted_at IS NULL
            ),
            conv_paths AS (
                SELECT
                    product_id,
                    src AS start_unit,
                    dst AS end_unit,
                    factor,
                    ARRAY[src, dst]::uuid[] AS visited
                FROM conv_edges
                WHERE factor IS NOT NULL
                UNION ALL
                SELECT
                    e.product_id,
                    p.start_unit,
                    e.dst AS end_unit,
                    p.factor * e.factor AS factor,
                    p.visited || e.dst
                FROM conv_paths p
                INNER JOIN conv_edges e
                    ON e.product_id = p.product_id
                   AND e.src = p.end_unit
                   AND NOT (e.dst = ANY (p.visited))
                WHERE e.factor IS NOT NULL
            )
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
                line_net.agent_net_price,
                fac.to_map_unit_factor,
                cpc.unit_code AS map_unit_code,
                ROUND((line_net.agent_net_price / fac.to_map_unit_factor)::numeric, 4) AS agent_net_price_map_unit,
                cpc.map_price AS distributor_price,
                cpc.official_price AS official_price,
                cpc.map_price AS map_price,
                GREATEST(
                    cpc.map_price - ROUND((line_net.agent_net_price / fac.to_map_unit_factor)::numeric, 4),
                    0
                ) AS gap_unit,
                (
                    GREATEST(
                        cpc.map_price - ROUND((line_net.agent_net_price / fac.to_map_unit_factor)::numeric, 4),
                        0
                    ) * (soi.quantity * fac.to_map_unit_factor)
                ) AS gap_amount,
                CASE
                    WHEN cpc.map_price > 0 THEN ROUND((
                        GREATEST(
                            cpc.map_price - ROUND((line_net.agent_net_price / fac.to_map_unit_factor)::numeric, 4),
                            0
                        ) / cpc.map_price * 100
                    )::numeric, 2)
                    ELSE 0
                END AS gap_percent,
                (
                    ROUND((line_net.agent_net_price / fac.to_map_unit_factor)::numeric, 4) < cpc.map_price
                ) AS is_cutting,
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
            INNER JOIN LATERAL (
                SELECT cfg.*
                FROM partner.cutting_price_configs cfg
                WHERE cfg.category_id = p.category_id
                  AND cfg.deleted_at IS NULL
                  AND cfg.is_active = true
                ORDER BY cfg.sort_order ASC, cfg.created_at ASC
                LIMIT 1
            ) cpc ON true
            INNER JOIN LATERAL (
                SELECT mu.id
                FROM product.product_units mu
                WHERE mu.deleted_at IS NULL
                  AND (
                    UPPER(COALESCE(mu.code, '')) = UPPER(cpc.unit_code)
                    OR UPPER(COALESCE(mu.symbol, '')) = UPPER(cpc.unit_code)
                    OR UPPER(COALESCE(mu.name, '')) = UPPER(cpc.unit_code)
                  )
                ORDER BY
                    CASE
                        WHEN UPPER(COALESCE(mu.code, '')) = UPPER(cpc.unit_code) THEN 0
                        WHEN UPPER(COALESCE(mu.symbol, '')) = UPPER(cpc.unit_code) THEN 1
                        ELSE 2
                    END
                LIMIT 1
            ) map_unit ON true
            INNER JOIN LATERAL (
                SELECT CASE
                    WHEN soi.quantity > 0 THEN ROUND((soi.subtotal / soi.quantity)::numeric, 4)
                    ELSE soi.unit_price
                END AS agent_net_price
            ) line_net ON true
            INNER JOIN LATERAL (
                SELECT CASE
                    WHEN soi.unit_id IS NOT NULL AND soi.unit_id = map_unit.id THEN 1::numeric
                    ELSE (
                        SELECT cp.factor
                        FROM conv_paths cp
                        WHERE cp.product_id = p.id
                          AND cp.start_unit = soi.unit_id
                          AND cp.end_unit = map_unit.id
                        ORDER BY array_length(cp.visited, 1)
                        LIMIT 1
                    )
                END AS to_map_unit_factor
            ) fac ON fac.to_map_unit_factor IS NOT NULL AND fac.to_map_unit_factor > 0
            WHERE soi.deleted_at IS NULL
              AND COALESCE(soi.is_promo_free, false) = false
              AND soi.quantity > 0
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
