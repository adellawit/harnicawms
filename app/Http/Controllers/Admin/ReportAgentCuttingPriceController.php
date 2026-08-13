<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AgentCuttingPriceDetailExport;
use App\Exports\AgentCuttingPriceSummaryExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgentCuttingPriceReportRequest;
use App\Services\Reporting\AgentCuttingPriceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportAgentCuttingPriceController extends Controller
{
    public function __construct(
        private readonly AgentCuttingPriceReportService $service,
    ) {}

    public function index(AgentCuttingPriceReportRequest $request): View
    {
        return view(
            'admin.reporting.sales.agent-cutting-price.index',
            $this->service->report($request->validated())
        );
    }

    public function export(AgentCuttingPriceReportRequest $request)
    {
        $filters = $this->service->filters($request->validated());
        $type = $request->validated('export') ?: 'summary';

        if ($type === 'detail') {
            return Excel::download(
                new AgentCuttingPriceDetailExport($this->service->exportDetailRows($filters)),
                'agent-cutting-price-detail-'.now()->format('Ymd-His').'.xlsx'
            );
        }

        return Excel::download(
            new AgentCuttingPriceSummaryExport($this->service->exportSummaryRows($filters)),
            'agent-cutting-price-summary-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    public function details(AgentCuttingPriceReportRequest $request): JsonResponse
    {
        $filters = $this->service->filters($request->validated());
        if (empty($filters['agent_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'agent_id wajib diisi.',
            ], 422);
        }

        $payload = $this->service->details($filters, (int) ($request->validated('per_page') ?? 50));
        $page = $payload['details'];

        return response()->json([
            'success' => true,
            'data' => [
                'rows' => collect($page->items())->map(fn ($row) => [
                    'sales_date' => $row->sales_date,
                    'sales_number' => $row->sales_number,
                    'seller' => $row->reseller_name
                        ? ('Reseller '.$row->reseller_code.' · '.$row->reseller_name)
                        : ('Agent '.$row->agent_code.' · '.$row->agent_name),
                    'product' => trim(($row->product_code ? $row->product_code.' · ' : '').$row->product_name),
                    'variant_sku' => $row->variant_sku,
                    'unit' => $row->unit_symbol ?: $row->unit_name,
                    'quantity' => (float) $row->quantity,
                    'agent_unit_price' => (float) $row->agent_unit_price,
                    'agent_net_price' => (float) $row->agent_net_price,
                    'agent_net_price_map_unit' => (float) ($row->agent_net_price_map_unit ?? $row->agent_net_price),
                    'map_unit_code' => $row->map_unit_code ?? null,
                    'distributor_price' => (float) $row->distributor_price,
                    'gap_amount' => (float) $row->gap_amount,
                    'gap_percent' => (float) $row->gap_percent,
                ])->values(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'total' => $page->total(),
                ],
            ],
        ]);
    }
}
