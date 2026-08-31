<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductLabelSerial;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class StockBarcodeDetailService
{
    /**
     * @param  list<string>|null  $includeSerialIds  null = all serials (admin). Empty = none.
     * @param  list<string>|null  $soldSerialIds  null = any sales assignment counts as keluar.
     * @return array<string, mixed>
     */
    public function detail(Product $product, ?string $variantId, ?array $includeSerialIds = null, ?array $soldSerialIds = null): array
    {
        $product->loadMissing([
            'defaultUnit:id,name,symbol',
            'unitConversions.fromUnit:id,name,symbol',
            'unitConversions.toUnit:id,name,symbol',
            'nature:id,name,code',
        ]);

        $variant = $variantId ? ProductVariant::find($variantId) : null;

        $soldFlip = $soldSerialIds !== null ? array_flip($soldSerialIds) : null;

        $summaryQuery = DB::table('product.product_label_serials as pls')
            ->leftJoin('product.product_units as pu', 'pu.id', '=', 'pls.unit_id')
            ->where('pls.product_id', $product->id)
            ->when(
                $variantId,
                fn ($q) => $q->where('pls.product_variant_id', $variantId),
                fn ($q) => $q->whereNull('pls.product_variant_id')
            )
            ->when($includeSerialIds !== null, function ($q) use ($includeSerialIds) {
                if ($includeSerialIds === []) {
                    $q->whereRaw('1 = 0');

                    return;
                }
                $q->whereIn('pls.id', $includeSerialIds);
            })
            ->groupBy('pls.unit_id', 'pls.unit_level', 'pu.name', 'pu.symbol')
            ->orderBy('pls.unit_level')
            ->selectRaw('
                pls.unit_id,
                pls.unit_level,
                pu.name as unit_name,
                pu.symbol as unit_symbol,
                COUNT(*)::int as total,
                COUNT(*) FILTER (
                    WHERE EXISTS (
                        SELECT 1 FROM transaction.sales_order_item_serial_assignments a
                        WHERE a.product_label_serial_id = pls.id
                    )
                )::int as dispatched
            ');

        $summary = $summaryQuery
            ->get()
            ->map(function ($row) use ($soldSerialIds, $includeSerialIds, $product, $variantId) {
                $total = (int) $row->total;
                $dispatched = (int) $row->dispatched;

                if ($soldSerialIds !== null) {
                    $dispatched = $soldSerialIds === []
                        ? 0
                        : $this->soldCountForUnit(
                            $product->id,
                            $variantId,
                            $row->unit_id,
                            (int) $row->unit_level,
                            $includeSerialIds,
                            $soldSerialIds
                        );
                }

                return [
                    'unit_id' => $row->unit_id,
                    'unit_level' => $row->unit_level,
                    'unit_label' => strtoupper($row->unit_symbol ?: ($row->unit_name ?: ('L'.$row->unit_level))),
                    'total' => $total,
                    'ready' => $total - $dispatched,
                    'dispatched' => $dispatched,
                ];
            })
            ->values();

        $tree = $this->buildTree($product, $variantId, $soldFlip);
        if ($includeSerialIds !== null) {
            $tree = $this->pruneTreeToSerials($tree, $includeSerialIds);
        }

        $kpiLevels = $this->levelsFromTree($product, $tree);
        if ($soldSerialIds !== null) {
            $tree = $this->dropDispatchedNodes($tree);
        }

        $levels = $this->levelsFromTree($product, $tree);
        if ($includeSerialIds !== null) {
            $summary = collect($kpiLevels)->map(fn (array $row) => [
                'unit_id' => $row['unit_id'],
                'unit_level' => $row['unit_level'],
                'unit_label' => $row['unit_label'],
                'total' => $row['total'],
                'ready' => $row['ready'],
                'dispatched' => $row['dispatched'],
            ])->values();
        }

        return [
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code ?? $product->sku,
            ],
            'variant' => $variant ? [
                'id' => $variant->id,
                'sku' => $variant->sku,
            ] : null,
            'conversion_chain' => $this->conversionChain($product),
            'summary' => $summary,
            'totals' => [
                'total' => (int) $summary->sum('total'),
                'ready' => (int) $summary->sum('ready'),
                'dispatched' => (int) $summary->sum('dispatched'),
            ],
            'tree' => $tree,
            'levels' => $levels,
        ];
    }

    /**
     * Serials received into the agent's warehouse (scanned on the agent's web-orders).
     *
     * @return list<string>
     */
    public function serialIdsReceivedByCustomer(string $customerId, ?string $productId = null, ?string $variantId = null): array
    {
        return $this->serialIdsForOrderType('web-order', [
            'customer_id' => $customerId,
            'product_id' => $productId,
            'variant_id' => $variantId,
        ]);
    }

    /**
     * Serials sold out of the agent's warehouse via Agent POS.
     *
     * @return list<string>
     */
    public function serialIdsSoldByAgentWarehouse(string $warehouseId, ?string $productId = null, ?string $variantId = null): array
    {
        return $this->serialIdsForOrderType('agent-pos', [
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'variant_id' => $variantId,
        ]);
    }

    /**
     * Serials already used after inbound (POS/sale), excluding receipt scans.
     * Agent stock page only — admin barcode view is unchanged.
     *
     * @param  list<string>  $inboundIds
     * @return list<string>
     */
    public function serialIdsUsedBeyondInbound(array $inboundIds, string $productId, ?string $variantId = null): array
    {
        $query = DB::table('transaction.sales_order_item_serial_assignments as a')
            ->join('product.product_label_serials as pls', 'pls.id', '=', 'a.product_label_serial_id')
            ->join('transaction.sales_order_items as soi', 'soi.id', '=', 'a.sales_order_item_id')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->where('pls.product_id', $productId)
            ->whereNull('so.deleted_at')
            ->whereNotIn('so.status', ['cancelled', 'void', 'failed']);

        if ($variantId) {
            $query->where('pls.product_variant_id', $variantId);
        }

        if ($inboundIds !== []) {
            $query->whereNotIn('a.product_label_serial_id', $inboundIds);
        }

        return $query->pluck('a.product_label_serial_id')->unique()->values()->all();
    }

    /**
     * @param  array{customer_id?: string, warehouse_id?: string, product_id?: ?string, variant_id?: ?string}  $filters
     * @return list<string>
     */
    protected function serialIdsForOrderType(string $orderType, array $filters): array
    {
        $query = DB::table('transaction.sales_order_item_serial_assignments as a')
            ->join('transaction.sales_order_items as soi', 'soi.id', '=', 'a.sales_order_item_id')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->where('so.order_type', $orderType)
            ->whereNull('so.deleted_at')
            ->whereIn('so.status', ['shipped', 'completed', 'verification']);

        if (! empty($filters['customer_id'])) {
            $query->where('so.customer_id', $filters['customer_id']);
        }
        if (! empty($filters['warehouse_id'])) {
            $query->where('so.warehouse_id', $filters['warehouse_id']);
        }
        if (! empty($filters['product_id'])) {
            $query->where('soi.product_id', $filters['product_id']);
        }
        if (! empty($filters['variant_id'])) {
            $query->where('soi.product_variant_id', $filters['variant_id']);
        }

        return $query->pluck('a.product_label_serial_id')->unique()->values()->all();
    }

    /**
     * @param  list<string>|null  $includeSerialIds
     * @param  list<string>  $soldSerialIds
     */
    protected function soldCountForUnit(
        string $productId,
        ?string $variantId,
        ?string $unitId,
        int $unitLevel,
        ?array $includeSerialIds,
        array $soldSerialIds,
    ): int {
        return (int) ProductLabelSerial::query()
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->where('unit_level', $unitLevel)
            ->when(
                $variantId,
                fn ($q) => $q->where('product_variant_id', $variantId),
                fn ($q) => $q->whereNull('product_variant_id')
            )
            ->when($includeSerialIds !== null, function ($q) use ($includeSerialIds) {
                if ($includeSerialIds === []) {
                    $q->whereRaw('1 = 0');

                    return;
                }
                $q->whereIn('id', $includeSerialIds);
            })
            ->whereIn('id', $soldSerialIds)
            ->count();
    }

    /**
     * @return list<string>
     */
    protected function conversionChain(Product $product): array
    {
        $chain = $product->getBarcodeUnits()->values();
        if ($chain->isEmpty()) {
            return [];
        }

        $parts = [];
        for ($j = 0; $j < $chain->count() - 1; $j++) {
            $from = $chain[$j];
            $to = $chain[$j + 1];
            $factor = null;
            foreach ($product->unitConversions as $conv) {
                if ($conv->from_unit_id === $from->id && $conv->to_unit_id === $to->id) {
                    $factor = (float) $conv->conversion_factor;
                    break;
                }
            }
            if ($factor === null || $factor <= 0) {
                break;
            }
            $parts[] = '1 '.strtoupper($from->symbol ?: $from->name)
                .' = '.(int) $factor.' '.strtoupper($to->symbol ?: $to->name);
        }

        return $parts;
    }

    /**
     * @param  array<string, int>|null  $soldFlip
     * @return list<array<string, mixed>>
     */
    protected function buildTree(Product $product, ?string $variantId, ?array $soldFlip): array
    {
        $units = $product->getBarcodeUnits()->values();
        if ($units->count() < 1) {
            return [];
        }

        $treeUnits = $units->take(3)->values();

        $dispatchedIds = DB::table('transaction.sales_order_item_serial_assignments as a')
            ->join('product.product_label_serials as pls', 'pls.id', '=', 'a.product_label_serial_id')
            ->where('pls.product_id', $product->id)
            ->when(
                $variantId,
                fn ($q) => $q->where('pls.product_variant_id', $variantId),
                fn ($q) => $q->whereNull('pls.product_variant_id')
            )
            ->pluck('a.product_label_serial_id')
            ->flip();

        $serialsByLevel = [];
        foreach ($treeUnits as $index => $unit) {
            $level = $index + 1;
            $rows = ProductLabelSerial::query()
                ->where('product_id', $product->id)
                ->where('unit_id', $unit->id)
                ->when(
                    $variantId,
                    fn ($q) => $q->where('product_variant_id', $variantId),
                    fn ($q) => $q->whereNull('product_variant_id')
                )
                ->orderBy('sequence')
                ->get(['id', 'serial_number', 'unit_level', 'sequence', 'created_at']);

            $serialsByLevel[$level] = $rows->map(function (ProductLabelSerial $s) use ($dispatchedIds, $soldFlip, $unit, $level) {
                if ($soldFlip !== null) {
                    $status = isset($soldFlip[$s->id]) ? 'dispatched' : 'ready';
                } else {
                    $status = isset($dispatchedIds[$s->id]) ? 'dispatched' : 'ready';
                }

                return [
                    'id' => $s->id,
                    'serial' => $s->serial_number,
                    'level' => $level,
                    'unit_label' => strtoupper($unit->symbol ?: $unit->name),
                    'status' => $status,
                    'created_at' => optional($s->created_at)->format('d M Y H:i'),
                    'children' => [],
                ];
            })->values()->all();
        }

        $levels = array_keys($serialsByLevel);
        sort($levels);
        if ($levels === []) {
            return [];
        }

        $childFactors = [];
        for ($i = 0; $i < count($levels) - 1; $i++) {
            $fromUnit = $treeUnits[$i] ?? null;
            $toUnit = $treeUnits[$i + 1] ?? null;
            $factor = 0;
            if ($fromUnit && $toUnit) {
                foreach ($product->unitConversions as $conv) {
                    if ($conv->from_unit_id === $fromUnit->id && $conv->to_unit_id === $toUnit->id) {
                        $factor = (int) $conv->conversion_factor;
                        break;
                    }
                }
            }
            $childFactors[$levels[$i]] = $factor;
        }

        $cursors = array_fill_keys($levels, 0);

        $build = function (int $level) use (&$build, &$cursors, $serialsByLevel, $childFactors, $levels): ?array {
            $list = $serialsByLevel[$level] ?? [];
            $idx = $cursors[$level] ?? 0;
            if (! isset($list[$idx])) {
                return null;
            }
            $node = $list[$idx];
            $cursors[$level] = $idx + 1;

            $levelPos = array_search($level, $levels, true);
            $hasChildLevel = $levelPos !== false && isset($levels[$levelPos + 1]);
            if ($hasChildLevel) {
                $childLevel = $levels[$levelPos + 1];
                $childrenCount = $childFactors[$level] ?? 0;
                for ($c = 0; $c < $childrenCount; $c++) {
                    $child = $build($childLevel);
                    if ($child === null) {
                        break;
                    }
                    $node['children'][] = $child;
                }
            }

            return $node;
        };

        $rootLevel = $levels[0];
        $tree = [];
        $rootCount = count($serialsByLevel[$rootLevel] ?? []);
        for ($i = 0; $i < $rootCount; $i++) {
            $node = $build($rootLevel);
            if ($node === null) {
                break;
            }
            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * Keep inbound serials and their reconstructed L2/L3 children.
     *
     * @param  list<array<string, mixed>>  $tree
     * @param  list<string>  $includeSerialIds
     * @return list<array<string, mixed>>
     */
    protected function pruneTreeToSerials(array $tree, array $includeSerialIds): array
    {
        if ($includeSerialIds === []) {
            return [];
        }

        return $this->pruneNodes($tree, array_flip($includeSerialIds));
    }

    /**
     * Barcode yang sudah keluar/terjual tidak ditampilkan lagi.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    protected function dropDispatchedNodes(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (($node['status'] ?? '') === 'dispatched') {
                continue;
            }

            $node['children'] = $this->dropDispatchedNodes($node['children'] ?? []);
            $out[] = $node;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     */
    public function treeContainsSerial(array $tree, string $serialId): bool
    {
        foreach ($tree as $node) {
            if (($node['id'] ?? '') === $serialId) {
                return true;
            }
            if ($this->treeContainsSerial($node['children'] ?? [], $serialId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, int>  $allowed
     * @return list<array<string, mixed>>
     */
    protected function pruneNodes(array $nodes, array $allowed): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (isset($allowed[$node['id']])) {
                $out[] = $node;

                continue;
            }

            foreach ($this->pruneNodes($node['children'] ?? [], $allowed) as $child) {
                $out[] = $child;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    protected function levelsFromTree(Product $product, array $tree): array
    {
        $flat = [];
        $walk = function (array $nodes) use (&$walk, &$flat): void {
            foreach ($nodes as $node) {
                $flat[] = [
                    'id' => $node['id'],
                    'serial' => $node['serial'],
                    'level' => (int) $node['level'],
                    'unit_label' => $node['unit_label'],
                    'status' => $node['status'],
                ];
                if (! empty($node['children'])) {
                    $walk($node['children']);
                }
            }
        };
        $walk($tree);

        $grouped = collect($flat)->groupBy('level');
        $units = $product->getBarcodeUnits()->take(3)->values();
        $levels = [];

        foreach ($units as $index => $unit) {
            $level = $index + 1;
            $serials = $grouped->get($level, collect())->values()->all();
            $ready = collect($serials)->where('status', 'ready')->count();
            $dispatched = collect($serials)->where('status', 'dispatched')->count();

            $levels[] = [
                'unit_id' => $unit->id,
                'unit_level' => $level,
                'unit_label' => strtoupper($unit->symbol ?: ($unit->name ?: ('L'.$level))),
                'total' => count($serials),
                'ready' => $ready,
                'dispatched' => $dispatched,
                'serials' => $serials,
            ];
        }

        return $levels;
    }
}
