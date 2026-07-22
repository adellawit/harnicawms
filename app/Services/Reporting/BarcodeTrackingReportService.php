<?php

namespace App\Services\Reporting;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Partner\Agent;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Repositories\BarcodeTrackingReportRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BarcodeTrackingReportService
{
    public function __construct(
        private readonly BarcodeTrackingReportRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function report(array $input, ?string $defaultBranchId): array
    {
        $filters = $this->filters($input, $defaultBranchId);
        $perPage = (int) ($input['per_page'] ?? 20);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        return [
            'rows' => $this->repository->paginate($filters, $perPage),
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
    public function filters(array $input, ?string $defaultBranchId): array
    {
        return [
            'date_from' => $this->parseDate($input['date_from'] ?? null)
                ?? now()->startOfMonth()->toDateString(),
            'date_to' => $this->parseDate($input['date_to'] ?? null)
                ?? now()->toDateString(),
            'branch_id' => $input['branch_id'] ?? $defaultBranchId,
            'agent_id' => $input['agent_id'] ?? null,
            'customer_id' => $input['customer_id'] ?? null,
            'product_id' => $input['product_id'] ?? null,
            'variant_id' => $input['variant_id'] ?? null,
            'unit_id' => $input['unit_id'] ?? null,
            'serial' => $this->cleanText($input['serial'] ?? null),
            'sales_number' => $this->cleanText($input['sales_number'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportRows(array $filters): Collection
    {
        return $this->repository->rows($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, Collection>
     */
    private function options(array $filters): array
    {
        return [
            'branches' => BusinessUnit::where('type_code', 'BRANCH')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'agents' => Agent::whereNull('deleted_at')
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'customers' => Customer::where(function ($query): void {
                $query->whereHas('agent')
                    ->orWhereHas('reseller.agent');
            })
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'customer_type']),
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
            'units' => ProductUnit::whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name', 'symbol']),
        ];
    }

    private function parseDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, trim($value))->toDateString();
            } catch (\Throwable) {
                // Try the next supported format.
            }
        }

        return null;
    }

    private function cleanText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
