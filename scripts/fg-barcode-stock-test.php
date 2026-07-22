<?php

declare(strict_types=1);

use App\Exports\FgBarcodeStockSerialExport;
use App\Exports\FgBarcodeStockSummaryExport;
use App\Models\IamHasAccess;
use App\Models\Menu;
use App\Services\Reporting\FgBarcodeStockReportService;
use App\Support\WmsContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function expectTrue(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$menu = Menu::where('name', 'FG Barcode & Stock')->firstOrFail();
expectTrue($menu->parent?->name === 'Inventory & Warehouse', 'FG Barcode & Stock menu parent is incorrect.');
expectTrue(
    IamHasAccess::where('iam_access_id', 'b0763f22-c9d1-41de-b7b9-28b523a7a354')
        ->where('sidebar_menu_id', $menu->id)
        ->where('is_read', true)
        ->exists(),
    'Administrator must receive FG Barcode & Stock report access.'
);
expectTrue(Route::has('reporting.fg-barcode-stock.index'), 'FG barcode stock index route must exist.');
expectTrue(Route::has('reporting.fg-barcode-stock.export'), 'FG barcode stock export route must exist.');
expectTrue(Route::has('reporting.fg-barcode-stock.serials'), 'FG barcode stock serials route must exist.');
expectTrue(
    in_array(
        'permission:FG Barcode & Stock,is_read',
        Route::getRoutes()->getByName('reporting.fg-barcode-stock.index')->gatherMiddleware(),
        true
    ),
    'FG Barcode & Stock route permission must exactly match the menu name.'
);

$service = app(FgBarcodeStockReportService::class);
$companyId = optional(WmsContext::distributor())->id;
$filters = $service->filters([], $companyId);
expectTrue(! empty($filters['warehouse_id']), 'Default FG warehouse must resolve.');

$report = $service->report([], $companyId);
expectTrue($report['rows']->total() >= 0, 'Summary pagination must run.');
expectTrue(array_key_exists('mismatch_rows', $report['kpis']), 'KPIs must include mismatch_rows.');

$summaryRows = $service->exportSummaryRows($filters);
$serialRows = $service->exportSerialRows($filters);
expectTrue(
    strlen(Excel::raw(new FgBarcodeStockSummaryExport($summaryRows), ExcelWriter::XLSX)) > 1000,
    'Summary Excel export must produce a valid XLSX payload.'
);
expectTrue(
    strlen(Excel::raw(new FgBarcodeStockSerialExport($serialRows->take(50)), ExcelWriter::XLSX)) > 1000,
    'Serial Excel export must produce a valid XLSX payload.'
);

$mismatch = $service->filters(['mismatch_only' => true], $companyId);
$mismatchRows = $service->exportSummaryRows($mismatch);
expectTrue(
    $mismatchRows->every(fn ($row): bool => ($row->status ?? 'ok') !== 'ok'),
    'Mismatch-only filter must exclude OK rows.'
);

echo "FG barcode stock tests passed.\n";
