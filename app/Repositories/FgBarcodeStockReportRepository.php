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
        $rows = $this->summaryRows($filters);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $slice = $rows->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * Summary with stock converted to each serial unit (same product + variant).
     *
     * @param  array<string, mixed>  $filters
     */
    public function summaryRows(array $filters): Collection
    {
        $warehouseId = $filters['warehouse_id'] ?? null;
        $stock = $this->stockAggregates($filters);
        $ready = $this->readyAggregates($filters);

        $productIds = $stock->pluck('product_id')
            ->merge($ready->pluck('product_id'))
            ->unique()
            ->filter()
            ->values();

        $products = DB::table('product.products as p')
            ->join('product.product_natures as pn', function ($join): void {
                $join->on('pn.id', '=', 'p.nature_id')
                    ->where('pn.code', 'FINISHED_GOOD')
                    ->whereNull('pn.deleted_at');
            })
            ->whereIn('p.id', $productIds)
            ->whereNull('p.deleted_at')
            ->get(['p.id', 'p.code', 'p.name', 'p.default_unit_id'])
            ->keyBy('id');

        $conversions = DB::table('product.product_unit_conversions')
            ->whereIn('product_id', $productIds)
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('product_id');

        $unitIds = $stock->pluck('unit_id')
            ->merge($ready->pluck('unit_id'))
            ->merge($products->pluck('default_unit_id'))
            ->unique()
            ->filter()
            ->values();

        $units = DB::table('product.product_units')
            ->whereIn('id', $unitIds)
            ->get(['id', 'name', 'symbol'])
            ->keyBy('id');

        $variantIds = $stock->pluck('product_variant_id')
            ->merge($ready->pluck('product_variant_id'))
            ->unique()
            ->filter()
            ->values();

        $variants = $variantIds->isEmpty()
            ? collect()
            : DB::table('product.product_variants')
                ->whereIn('id', $variantIds)
                ->get(['id', 'sku'])
                ->keyBy('id');

        $warehouse = $warehouseId
            ? DB::table('master_data.warehouses')->where('id', $warehouseId)->first(['id', 'code', 'name'])
            : null;

        $stockByProductVariant = $stock->groupBy(
            fn ($row) => $row->product_id.'|'.($row->product_variant_id ?? 'null')
        );

        $keys = collect();
        foreach ($ready as $row) {
            $keys->push([
                'product_id' => $row->product_id,
                'product_variant_id' => $row->product_variant_id,
                'unit_id' => $row->unit_id,
            ]);
        }
        foreach ($stock as $row) {
            $keys->push([
                'product_id' => $row->product_id,
                'product_variant_id' => $row->product_variant_id,
                'unit_id' => $row->unit_id,
            ]);
        }

        $keys = $keys->unique(fn ($k) => $k['product_id'].'|'.($k['product_variant_id'] ?? 'null').'|'.$k['unit_id']);

        $readyLookup = $ready->keyBy(
            fn ($row) => $row->product_id.'|'.($row->product_variant_id ?? 'null').'|'.$row->unit_id
        );

        $rows = collect();

        foreach ($keys as $key) {
            $product = $products->get($key['product_id']);
            if (! $product) {
                continue;
            }

            $pvKey = $key['product_id'].'|'.($key['product_variant_id'] ?? 'null');
            $stockLines = $stockByProductVariant->get($pvKey, collect());
            $readyKey = $key['product_id'].'|'.($key['product_variant_id'] ?? 'null').'|'.$key['unit_id'];
            $serialReady = (int) ($readyLookup->get($readyKey)->serial_ready ?? 0);

            $stockEquivalent = $this->equivalentStockQty(
                $stockLines,
                $key['unit_id'],
                $conversions->get($key['product_id'], collect())
            );

            // Native stock at this exact unit (before conversion), for transparency.
            $nativeStock = (float) $stockLines
                ->where('unit_id', $key['unit_id'])
                ->sum('stock_qty');

            $expected = $stockEquivalent ?? 0.0;
            $expectedRounded = (int) round($expected);
            $variance = $serialReady - $expectedRounded;

            if ($stockLines->isEmpty() && $serialReady > 0) {
                $status = 'orphan';
            } elseif ($variance === 0) {
                $status = 'ok';
            } elseif ($variance > 0) {
                $status = 'surplus';
            } else {
                $status = 'shortage';
            }

            if (($filters['mismatch_only'] ?? false) === true && $status === 'ok') {
                continue;
            }

            $unit = $units->get($key['unit_id']);
            $variant = $key['product_variant_id']
                ? $variants->get($key['product_variant_id'])
                : null;

            $rows->push((object) [
                'product_id' => $key['product_id'],
                'product_variant_id' => $key['product_variant_id'],
                'unit_id' => $key['unit_id'],
                'product_code' => $product->code,
                'product_name' => $product->name,
                'variant_sku' => $variant->sku ?? null,
                'unit_name' => $unit->name ?? null,
                'unit_symbol' => $unit->symbol ?? null,
                'warehouse_id' => $warehouse?->id ?? $warehouseId,
                'warehouse_code' => $warehouse?->code,
                'warehouse_name' => $warehouse?->name,
                'stock_qty_native' => $nativeStock,
                'stock_qty' => $expectedRounded,
                'serial_ready' => $serialReady,
                'variance' => $variance,
                'status' => $status,
                'unit_sort_level' => $this->unitSortLevel(
                    $product->default_unit_id,
                    $key['unit_id'],
                    $conversions->get($key['product_id'], collect())
                ),
            ]);
        }

        return $rows
            ->sortBy([
                ['product_name', 'asc'],
                ['variant_sku', 'asc'],
                ['unit_sort_level', 'asc'],
                ['unit_name', 'asc'],
            ])
            ->values();
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
        $summary = $this->summaryRows(array_merge($filters, ['mismatch_only' => false]));
        $stockNative = $this->stockAggregates($filters)->sum('stock_qty');

        return [
            'rows' => $summary->count(),
            // Warehouse stock once (do not sum converted equivalents across units).
            'stock_qty' => (float) $stockNative,
            'serial_ready' => (int) $summary->sum('serial_ready'),
            'mismatch_rows' => $summary->where('status', '!=', 'ok')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function stockAggregates(array $filters): Collection
    {
        $query = DB::table('product.product_variant_stock as pvs')
            ->join('product.products as p', 'p.id', '=', 'pvs.product_id')
            ->join('product.product_natures as pn', function ($join): void {
                $join->on('pn.id', '=', 'p.nature_id')
                    ->where('pn.code', 'FINISHED_GOOD')
                    ->whereNull('pn.deleted_at');
            })
            ->whereNull('pvs.deleted_at')
            ->when($filters['warehouse_id'] ?? null, fn (Builder $q, string $id) => $q->where('pvs.warehouse_id', $id))
            ->when($filters['product_id'] ?? null, fn (Builder $q, string $id) => $q->where('pvs.product_id', $id))
            ->when($filters['variant_id'] ?? null, fn (Builder $q, string $id) => $q->where('pvs.product_variant_id', $id))
            ->when($filters['unit_id'] ?? null, fn (Builder $q, string $id) => $q->where('pvs.unit_id', $id))
            ->groupBy('pvs.product_id', 'pvs.product_variant_id', 'pvs.unit_id', 'pvs.warehouse_id')
            ->selectRaw('
                pvs.product_id,
                pvs.product_variant_id,
                pvs.unit_id,
                pvs.warehouse_id,
                COALESCE(SUM(pvs.quantity), 0) AS stock_qty
            ');

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function readyAggregates(array $filters): Collection
    {
        $query = DB::table('product.product_label_serials as pls')
            ->join('product.products as p', 'p.id', '=', 'pls.product_id')
            ->join('product.product_natures as pn', function ($join): void {
                $join->on('pn.id', '=', 'p.nature_id')
                    ->where('pn.code', 'FINISHED_GOOD')
                    ->whereNull('pn.deleted_at');
            })
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('transaction.sales_order_item_serial_assignments as a')
                    ->whereColumn('a.product_label_serial_id', 'pls.id');
            })
            ->when($filters['product_id'] ?? null, fn (Builder $q, string $id) => $q->where('pls.product_id', $id))
            ->when($filters['variant_id'] ?? null, fn (Builder $q, string $id) => $q->where('pls.product_variant_id', $id))
            ->when($filters['unit_id'] ?? null, fn (Builder $q, string $id) => $q->where('pls.unit_id', $id))
            ->groupBy('pls.product_id', 'pls.product_variant_id', 'pls.unit_id')
            ->selectRaw('
                pls.product_id,
                pls.product_variant_id,
                pls.unit_id,
                COUNT(*)::int AS serial_ready
            ');

        return $query->get();
    }

    /**
     * @param  Collection<int, object>  $stockLines
     * @param  Collection<int, object>  $conversions
     */
    private function equivalentStockQty(Collection $stockLines, string $toUnitId, Collection $conversions): ?float
    {
        if ($stockLines->isEmpty()) {
            return null;
        }

        $total = 0.0;
        $convertedAny = false;

        foreach ($stockLines as $line) {
            $qty = (float) $line->stock_qty;
            $fromUnitId = (string) $line->unit_id;
            if ($fromUnitId === $toUnitId) {
                $total += $qty;
                $convertedAny = true;

                continue;
            }

            $converted = $this->convertQty($qty, $fromUnitId, $toUnitId, $conversions);
            if ($converted === null) {
                continue;
            }
            $total += $converted;
            $convertedAny = true;
        }

        return $convertedAny ? $total : null;
    }

    /**
     * @param  Collection<int, object>  $conversions
     */
    private function convertQty(float $quantity, string $fromUnitId, string $toUnitId, Collection $conversions): ?float
    {
        if ($fromUnitId === $toUnitId) {
            return $quantity;
        }

        $direct = $conversions->first(
            fn ($row) => $row->from_unit_id === $fromUnitId && $row->to_unit_id === $toUnitId
        );
        if ($direct) {
            return $quantity * (float) $direct->conversion_factor;
        }

        $reverse = $conversions->first(
            fn ($row) => $row->from_unit_id === $toUnitId && $row->to_unit_id === $fromUnitId
        );
        if ($reverse) {
            $factor = (float) $reverse->conversion_factor;

            return $factor > 0 ? $quantity / $factor : null;
        }

        // Multi-hop BFS
        $visited = [];
        $queue = [[$fromUnitId, $quantity]];

        while (! empty($queue)) {
            [$unitId, $qty] = array_shift($queue);
            if ($unitId === $toUnitId) {
                return $qty;
            }
            if (isset($visited[$unitId])) {
                continue;
            }
            $visited[$unitId] = true;

            foreach ($conversions as $conv) {
                $factor = (float) $conv->conversion_factor;
                if ($factor <= 0) {
                    continue;
                }
                if ($conv->from_unit_id === $unitId) {
                    $queue[] = [$conv->to_unit_id, $qty * $factor];
                }
                if ($conv->to_unit_id === $unitId) {
                    $queue[] = [$conv->from_unit_id, $qty / $factor];
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, object>  $conversions
     */
    private function unitSortLevel(?string $defaultUnitId, ?string $unitId, Collection $conversions): int
    {
        if (! $unitId) {
            return 9999;
        }
        if ($defaultUnitId && $unitId === $defaultUnitId) {
            return 0;
        }

        $level = $conversions
            ->where('to_unit_id', $unitId)
            ->min('conversion_level');

        return $level !== null ? (int) $level : 9999;
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
