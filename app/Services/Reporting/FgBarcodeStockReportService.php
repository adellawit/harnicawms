<?php

namespace App\Services\Reporting;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Repositories\FgBarcodeStockReportRepository;
use App\Support\WmsContext;
use Illuminate\Support\Collection;

class FgBarcodeStockReportService
{
    public function __construct(
        private readonly FgBarcodeStockReportRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function report(array $input, ?string $companyId = null): array
    {
        $filters = $this->filters($input, $companyId);
        $perPage = (int) ($input['per_page'] ?? 20);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        return [
            'rows' => $this->repository->paginate($filters, $perPage),
            'kpis' => $this->repository->kpis($filters),
            'filters' => $filters,
            'perPage' => $perPage,
            'options' => $this->options($filters, $companyId),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function filters(array $input, ?string $companyId = null): array
    {
        $defaultWarehouse = WmsContext::finishedGoodsWarehouse($companyId);
        $warehouseId = $input['warehouse_id'] ?? $defaultWarehouse?->id;

        return [
            'warehouse_id' => $warehouseId,
            'product_id' => $input['product_id'] ?? null,
            'variant_id' => $input['variant_id'] ?? null,
            'unit_id' => $input['unit_id'] ?? null,
            'serial' => $this->cleanText($input['serial'] ?? null),
            'mismatch_only' => filter_var($input['mismatch_only'] ?? false, FILTER_VALIDATE_BOOLEAN),
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
    public function exportSerialRows(array $filters): Collection
    {
        return $this->repository->serialRows($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function serialDrilldown(array $filters, int $perPage = 50): array
    {
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 50;

        return [
            'serials' => $this->repository->paginateSerials($filters, $perPage),
            'filters' => $filters,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, Collection>
     */
    private function options(array $filters, ?string $companyId = null): array
    {
        $warehouses = Warehouse::query()
            ->inventoryActive()
            ->where('warehouse_type_code', 'FG')
            ->when($companyId, fn ($q, string $id) => $q->forCompany($id))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'warehouse_type_code']);

        if ($warehouses->isEmpty()) {
            $fallback = WmsContext::finishedGoodsWarehouse($companyId);
            $warehouses = $fallback ? collect([$fallback]) : collect();
        }

        return [
            'warehouses' => $warehouses,
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

    private function cleanText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
