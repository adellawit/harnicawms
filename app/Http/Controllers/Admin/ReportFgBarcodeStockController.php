<?php

namespace App\Http\Controllers\Admin;

use App\Exports\FgBarcodeStockSerialExport;
use App\Exports\FgBarcodeStockSummaryExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\FgBarcodeStockReportRequest;
use App\Services\Reporting\FgBarcodeStockReportService;
use App\Support\WmsContext;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportFgBarcodeStockController extends Controller
{
    public function __construct(
        private readonly FgBarcodeStockReportService $service,
    ) {}

    public function index(FgBarcodeStockReportRequest $request): View
    {
        $companyId = optional(WmsContext::distributor())->id;

        return view(
            'admin.reporting.product.fg-barcode-stock.index',
            $this->service->report($request->validated(), $companyId)
        );
    }

    public function export(FgBarcodeStockReportRequest $request)
    {
        $companyId = optional(WmsContext::distributor())->id;
        $filters = $this->service->filters($request->validated(), $companyId);
        $type = $request->validated('export') ?: 'summary';

        if ($type === 'serials') {
            return Excel::download(
                new FgBarcodeStockSerialExport($this->service->exportSerialRows($filters)),
                'fg-barcode-stock-serials-'.now()->format('Ymd-His').'.xlsx'
            );
        }

        return Excel::download(
            new FgBarcodeStockSummaryExport($this->service->exportSummaryRows($filters)),
            'fg-barcode-stock-summary-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    public function serials(FgBarcodeStockReportRequest $request): JsonResponse
    {
        $companyId = optional(WmsContext::distributor())->id;
        $filters = $this->service->filters($request->validated(), $companyId);

        if (! empty($request->validated('null_variant'))) {
            $filters['null_variant'] = true;
            $filters['variant_id'] = null;
        }

        $payload = $this->service->serialDrilldown(
            $filters,
            (int) ($request->validated('per_page') ?? 50)
        );

        return response()->json([
            'success' => true,
            'data' => [
                'serials' => $payload['serials']->items(),
                'meta' => [
                    'current_page' => $payload['serials']->currentPage(),
                    'last_page' => $payload['serials']->lastPage(),
                    'total' => $payload['serials']->total(),
                ],
            ],
        ]);
    }
}
