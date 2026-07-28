<?php

namespace App\Services\Reporting;

use App\Models\Partner\Agent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\AgentCuttingPriceReportRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AgentCuttingPriceReportService
{
    public function __construct(
        private readonly AgentCuttingPriceReportRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function report(array $input): array
    {
        $filters = $this->filters($input);
        $perPage = (int) ($input['per_page'] ?? 20);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        return [
            'rows' => $this->repository->paginateSummary($filters, $perPage),
            'kpis' => $this->repository->kpis($filters),
            'filters' => $filters,
            'perPage' => $perPage,
            'options' => $this->options($filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function filters(array $input): array
    {
        return [
            'date_from' => $this->parseDate($input['date_from'] ?? null)
                ?? now()->startOfMonth()->toDateString(),
            'date_to' => $this->parseDate($input['date_to'] ?? null)
                ?? now()->toDateString(),
            'agent_id' => $input['agent_id'] ?? null,
            'product_id' => $input['product_id'] ?? null,
            'variant_id' => $input['variant_id'] ?? null,
            'branch_id' => $input['branch_id'] ?? null,
            'min_gap_percent' => max(0, (float) ($input['min_gap_percent'] ?? 0)),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportSummaryRows(array $filters): Collection
    {
        return $this->repository->summaryRows($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportDetailRows(array $filters): Collection
    {
        return $this->repository->detailRows($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function details(array $filters, int $perPage = 50): array
    {
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 50;

        return [
            'details' => $this->repository->paginateDetails($filters, $perPage),
            'filters' => $filters,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, Collection>
     */
    private function options(array $filters): array
    {
        return [
            'agents' => Agent::whereNull('deleted_at')
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'products' => Product::whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'variants' => ProductVariant::whereNull('deleted_at')
                ->when(
                    $filters['product_id'] ?? null,
                    fn ($query, string $productId) => $query->where('product_id', $productId)
                )
                ->orderBy('sku')
                ->get(['id', 'product_id', 'sku']),
        ];
    }

    private function parseDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
