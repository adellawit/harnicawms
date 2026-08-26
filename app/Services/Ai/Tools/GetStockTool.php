<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\AgentContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class GetStockTool extends AbstractAgentTool
{
    protected const PREVIEW_LIMIT = 10;

    public function name(): string
    {
        return 'get_stock';
    }

    public function description(): string
    {
        return 'ALWAYS call this for stock questions in the active branch. '
            .'"tampilkan stok", "seluruh stok", "stok semua", "daftar stok", or any stock request without a product name: '
            .'pass query as empty string or null. That is a supported overview (summary + up to 10 SKUs), not an error. '
            .'Never refuse. Never tell the user to open the Stok page instead. '
            .'A name or SKU string filters the list.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['query'],
            'properties' => [
                'query' => [
                    'type' => ['string', 'null'],
                    'description' => 'Empty string or null = branch overview (use this for tampilkan/seluruh/stok semua). '
                        .'Name or SKU = filter. Do not omit the tool call when the user did not name a product.',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?array
    {
        return ['menu' => 'Stock', 'action' => 'is_read'];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $branchId = $context->requireBranch();
        $query = trim((string) ($arguments['query'] ?? ''));
        $grouped = $this->groupedStockQuery($query, $branchId, $context);

        $stats = DB::connection('pgsql')
            ->query()
            ->fromSub($grouped, 'stock_rows')
            ->selectRaw('COUNT(*) as sku_count')
            ->selectRaw('COALESCE(SUM(qty), 0) as total_qty')
            ->selectRaw('COALESCE(SUM(CASE WHEN qty <= 0 THEN 1 ELSE 0 END), 0) as zero_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN qty > 0 AND min_stock > 0 AND qty <= min_stock THEN 1 ELSE 0 END), 0) as low_count')
            ->first();

        $skuCount = (int) ($stats->sku_count ?? 0);
        $totalQty = (float) ($stats->total_qty ?? 0);
        $zeroCount = (int) ($stats->zero_count ?? 0);
        $lowCount = (int) ($stats->low_count ?? 0);

        $preview = (clone $grouped)
            ->orderBy('qty')
            ->orderBy('label')
            ->limit(self::PREVIEW_LIMIT + 1)
            ->get();

        $hasMore = $preview->count() > self::PREVIEW_LIMIT;
        $items = $preview
            ->take(self::PREVIEW_LIMIT)
            ->map(fn ($row) => [
                'label' => (string) ($row->label ?? '-'),
                'sku' => (string) ($row->sku ?? ''),
                'stock' => $this->formatQty((float) ($row->qty ?? 0)),
                'unit' => (string) ($row->unit ?? '-'),
            ])
            ->values()
            ->all();

        $summary = [
            'sku_count' => $skuCount,
            'total_qty' => $this->formatQty($totalQty),
            'zero_count' => $zeroCount,
            'low_count' => $lowCount,
        ];

        return [
            'success' => true,
            'branch' => $this->branchLabel($context),
            'query' => $query,
            'overview' => $query === '',
            'summary' => $summary,
            'count' => $skuCount,
            'shown' => count($items),
            'has_more' => $hasMore,
            'items' => $items,
            'message' => $this->message($query, $skuCount, $summary, count($items), $hasMore, $context),
        ];
    }

    protected function groupedStockQuery(string $query, string $branchId, AgentContext $context): Builder
    {
        $builder = DB::connection('pgsql')
            ->table('product.product_variant_stock as pvs')
            ->join('product.product_variants as pv', function ($join) {
                $join->on('pv.id', '=', 'pvs.product_variant_id')
                    ->whereNull('pv.deleted_at')
                    ->where('pv.is_active', true);
            })
            ->join('product.products as p', function ($join) {
                $join->on('p.id', '=', 'pvs.product_id')
                    ->whereNull('p.deleted_at')
                    ->where('p.is_stock_item', true);
            })
            ->leftJoin('product.product_units as u', function ($join) {
                $join->on('u.id', '=', 'pvs.unit_id')
                    ->whereNull('u.deleted_at');
            })
            ->whereNull('pvs.deleted_at')
            ->where('pvs.branch_id', $branchId);

        if ($context->companyId) {
            $builder->where('pvs.company_id', $context->companyId);
        }

        if ($query !== '') {
            $builder->where(function ($inner) use ($query) {
                $inner->where('p.name', 'ilike', '%'.$query.'%')
                    ->orWhere('p.sku', 'ilike', '%'.$query.'%')
                    ->orWhere('p.code', 'ilike', '%'.$query.'%')
                    ->orWhere('pv.sku', 'ilike', '%'.$query.'%')
                    ->orWhere('pv.barcode', 'ilike', '%'.$query.'%');
            });
        }

        return $builder
            ->select('pvs.product_variant_id as variant_id')
            ->selectRaw('MAX(p.name) as label')
            ->selectRaw("MAX(COALESCE(NULLIF(pv.sku, ''), p.sku, '')) as sku")
            ->selectRaw('SUM(pvs.quantity) as qty')
            ->selectRaw("MAX(COALESCE(NULLIF(u.symbol, ''), NULLIF(u.name, ''), '-')) as unit")
            ->selectRaw('MAX(COALESCE(p.min_stock, 0)) as min_stock')
            ->groupBy('pvs.product_variant_id');
    }

    /**
     * @param  array{sku_count: int, total_qty: string, zero_count: int, low_count: int}  $summary
     */
    protected function message(
        string $query,
        int $skuCount,
        array $summary,
        int $shown,
        bool $hasMore,
        AgentContext $context,
    ): string {
        if ($skuCount === 0) {
            return $query === ''
                ? 'Belum ada data stok di cabang aktif.'
                : 'Stok untuk "'.$query.'" tidak ditemukan.';
        }

        $branch = $this->branchLabel($context);
        $text = $query === ''
            ? 'Ringkasan stok '.$branch.': '.$summary['sku_count'].' SKU, total qty '.$summary['total_qty']
                .'. Habis: '.$summary['zero_count'].', stok rendah: '.$summary['low_count'].'.'
            : 'Stok "'.$query.'" di '.$branch.': '.$summary['sku_count'].' SKU, total qty '.$summary['total_qty'].'.';

        if ($hasMore) {
            $remaining = $skuCount - $shown;
            $text .= ' Menampilkan '.$shown.' SKU (stok rendah dulu). Ada '.$remaining
                .' lainnya; sebut nama atau SKU untuk filter.';
        }

        return $text;
    }

    protected function formatQty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, ',', '.'), '0'), ',');
    }
}
