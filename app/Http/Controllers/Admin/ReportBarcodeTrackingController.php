<?php

namespace App\Http\Controllers\Admin;

use App\Exports\BarcodeTrackingExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\BarcodeTrackingReportRequest;
use App\Services\Reporting\BarcodeTrackingReportService;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportBarcodeTrackingController extends Controller
{
    public function __construct(
        private readonly BarcodeTrackingReportService $service,
    ) {}

    public function index(BarcodeTrackingReportRequest $request): View
    {
        $defaultBranchId = auth('web')->user()?->current_business_unit_id;

        return view(
            'admin.reporting.product.barcode-tracking.index',
            $this->service->report($request->validated(), $defaultBranchId)
        );
    }

    public function export(BarcodeTrackingReportRequest $request)
    {
        $defaultBranchId = auth('web')->user()?->current_business_unit_id;
        $filters = $this->service->filters($request->validated(), $defaultBranchId);
        $filename = 'barcode-agent-tracking-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(
            new BarcodeTrackingExport($this->service->exportRows($filters)),
            $filename
        );
    }
}
