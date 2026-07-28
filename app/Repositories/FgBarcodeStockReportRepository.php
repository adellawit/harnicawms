<?php

namespace App\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FgBarcodeStockReportRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->summaryQuery($filters)
            ->orderBy('product_name')
            ->orderBy('variant_sku')
            ->orderBy('unit_sort_level')
            ->orderBy('unit_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function summaryRows(array $filters): Collection
    {
        return $this->summaryQuery($filters)
            ->orderBy('product_name')
            ->orderBy('variant_sku')
            ->orderBy('unit_sort_level')
            ->orderBy('unit_name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function serialRows(array $filters): Collection
    {
        return $this->serialQuery($filters)
            ->orderBy('pls.unit_level')
            ->orderBy('pls.serial_number')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateSerials(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->serialQuery($filters)
            ->orderBy('pls.unit_level')
            ->orderBy('pls.serial_number')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: int, stock_qty: float, serial_ready: int, mismatch_rows: int}
     */
    public function kpis(array $filters): array
    {
        $base = $this->summaryQuery($filters);

        return [
            'rows' => (int) (clone $base)->count(),
            'stock_qty' => (float) (clone $base)->sum('stock_qty'),
            'serial_ready' => (int) (clone $base)->sum('serial_ready'),
            'mismatch_rows' => (int) (clone $base)->where('status', '!=', 'ok')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function summaryQuery(array $filters): Builder
    {
        $warehouseId = $filters['warehouse_id'] ?? null;

        $readySql = '
            SELECT
                pls.product_id,
                pls.product_variant_id,
                pls.unit_id,
                COUNT(*)::int AS serial_ready
            FROM product.product_label_serials pls
            WHERE NOT EXISTS (
                SELECT 1
                FROM transaction.sales_order_item_serial_assignments a
                WHERE a.product_label_serial_id = pls.id
            )
        ';
        $readyBindings = [];
        if (! empty($filters['product_id'])) {
            $readySql .= ' AND pls.product_id = ?';
            $readyBindings[] = $filters['product_id'];
        }
        if (! empty($filters['variant_id'])) {
            $readySql .= ' AND pls.product_variant_id = ?';
            $readyBindings[] = $filters['variant_id'];
        }
        if (! empty($filters['unit_id'])) {
            $readySql .= ' AND pls.unit_id = ?';
            $readyBindings[] = $filters['unit_id'];
        }
        $readySql .= ' GROUP BY pls.product_id, pls.product_variant_id, pls.unit_id';

        $stockSql = '
            SELECT
                pvs.product_id,
                pvs.product_variant_id,
                pvs.unit_id,
                pvs.warehouse_id,
                COALESCE(SUM(pvs.quantity), 0) AS stock_qty
            FROM product.product_variant_stock pvs
            WHERE pvs.deleted_at IS NULL
        ';
        $stockBindings = [];
        if ($warehouseId) {
            $stockSql .= ' AND pvs.warehouse_id = ?';
            $stockBindings[] = $warehouseId;
        }
        if (! empty($filters['product_id'])) {
            $stockSql .= ' AND pvs.product_id = ?';
            $stockBindings[] = $filters['product_id'];
        }
        if (! empty($filters['variant_id'])) {
            $stockSql .= ' AND pvs.product_variant_id = ?';
            $stockBindings[] = $filters['variant_id'];
        }
        if (! empty($filters['unit_id'])) {
            $stockSql .= ' AND pvs.unit_id = ?';
            $stockBindings[] = $filters['unit_id'];
        }
        $stockSql .= ' GROUP BY pvs.product_id, pvs.product_variant_id, pvs.unit_id, pvs.warehouse_id';

        $sql = "
            WITH ready AS ({$readySql}),
            stock AS ({$stockSql}),
            keys AS (
                SELECT product_id, product_variant_id, unit_id FROM ready
                UNION
                SELECT product_id, product_variant_id, unit_id FROM stock
            )
            SELECT
                k.product_id,
                k.product_variant_id,
                k.unit_id,
                p.code AS product_code,
                p.name AS product_name,
                pv.sku AS variant_sku,
                pu.name AS unit_name,
                pu.symbol AS unit_symbol,
                COALESCE(s.warehouse_id, ?) AS warehouse_id,
                w.code AS warehouse_code,
                w.name AS warehouse_name,
                COALESCE(s.stock_qty, 0) AS stock_qty,
                COALESCE(r.serial_ready, 0) AS serial_ready,
                (COALESCE(r.serial_ready, 0) - COALESCE(s.stock_qty, 0)) AS variance,
                CASE
                    WHEN COALESCE(r.serial_ready, 0) = COALESCE(s.stock_qty, 0) THEN 'ok'
                    WHEN COALESCE(r.serial_ready, 0) > COALESCE(s.stock_qty, 0) THEN 'surplus'
                    ELSE 'shortage'
                END AS status,
                CASE
                    WHEN k.unit_id IS NOT DISTINCT FROM p.default_unit_id THEN 0
                    ELSE COALESCE((
                        SELECT MIN(puc.conversion_level)
                        FROM product.product_unit_conversions puc
                        WHERE puc.product_id = p.id
                          AND puc.deleted_at IS NULL
                          AND puc.to_unit_id = k.unit_id
                    ), 9999)
                END AS unit_sort_level
            FROM keys k
            LEFT JOIN ready r
                ON r.product_id = k.product_id
               AND r.unit_id = k.unit_id
               AND r.product_variant_id IS NOT DISTINCT FROM k.product_variant_id
            LEFT JOIN stock s
                ON s.product_id = k.product_id
               AND s.unit_id = k.unit_id
               AND s.product_variant_id IS NOT DISTINCT FROM k.product_variant_id
            INNER JOIN product.products p ON p.id = k.product_id
            INNER JOIN product.product_natures pn
                ON pn.id = p.nature_id
               AND pn.code = 'FINISHED_GOOD'
               AND pn.deleted_at IS NULL
            LEFT JOIN product.product_variants pv ON pv.id = k.product_variant_id
            LEFT JOIN product.product_units pu ON pu.id = k.unit_id
            LEFT JOIN master_data.warehouses w ON w.id = COALESCE(s.warehouse_id, ?)
        ";

        $bindings = array_merge(
            $readyBindings,
            $stockBindings,
            [$warehouseId, $warehouseId]
        );

        if (($filters['mismatch_only'] ?? false) === true) {
            $sql .= ' WHERE COALESCE(r.serial_ready, 0) <> COALESCE(s.stock_qty, 0)';
        }

        return DB::query()
            ->fromRaw("({$sql}) as summary", $bindings)
            ->select('*');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function serialQuery(array $filters): Builder
    {
        return DB::table('product.product_label_serials as pls')
            ->join('product.products as p', 'p.id', '=', 'pls.product_id')
            ->join('product.product_natures as pn', function ($join): void {
                $join->on('pn.id', '=', 'p.nature_id')
                    ->where('pn.code', 'FINISHED_GOOD')
                    ->whereNull('pn.deleted_at');
            })
            ->leftJoin('product.product_variants as pv', 'pv.id', '=', 'pls.product_variant_id')
            ->leftJoin('product.product_units as pu', 'pu.id', '=', 'pls.unit_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('transaction.sales_order_item_serial_assignments as a')
                    ->whereColumn('a.product_label_serial_id', 'pls.id');
            })
            ->when($filters['product_id'] ?? null, fn (Builder $q, string $id) => $q->where('pls.product_id', $id))
            ->when($filters['variant_id'] ?? null, fn (Builder $q, string $id) => $q->where('pls.product_variant_id', $id))
            ->when(
                ($filters['null_variant'] ?? false) === true,
                fn (Builder $q) => $q->whereNull('pls.product_variant_id')
            )
            ->when($filters['unit_id'] ?? null, fn (Builder $q, string $id) => $q->where('pls.unit_id', $id))
            ->when($filters['serial'] ?? null, fn (Builder $q, string $value) => $q->where('pls.serial_number', 'ilike', "%{$value}%"))
            ->select([
                'pls.id',
                'pls.serial_number',
                'pls.unit_level',
                'pls.created_at',
                'pls.product_id',
                'pls.product_variant_id',
                'pls.unit_id',
                'p.code as product_code',
                'p.name as product_name',
                'pv.sku as variant_sku',
                'pu.name as unit_name',
                'pu.symbol as unit_symbol',
            ]);
    }
}