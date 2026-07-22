<?php

declare(strict_types=1);

use App\Exports\BarcodeTrackingExport;
use App\Models\Customer;
use App\Models\IamHasAccess;
use App\Models\Menu;
use App\Models\ProductLabelSerial;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SalesOrderBarcodeDispatch;
use App\Models\SalesOrderItem;
use App\Repositories\BarcodeTrackingReportRepository;
use App\Services\Reporting\BarcodeTrackingReportService;
use App\Services\Sales\BarcodeDispatchService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

function expectThrows(callable $callback, string $expectedMessage): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        expectTrue(
            str_contains($exception->getMessage(), $expectedMessage),
            "Expected exception containing '{$expectedMessage}', got '{$exception->getMessage()}'."
        );

        return;
    }

    throw new RuntimeException("Expected InvalidArgumentException containing '{$expectedMessage}'.");
}

function cloneOrder(SalesOrder $source, string $customerId): SalesOrder
{
    $order = $source->replicate();
    $order->sales_number = 'TEST-'.Str::uuid();
    $order->customer_id = $customerId;
    $order->save();

    return $order;
}

function cloneItem(SalesOrderItem $source, string $orderId, ?string $unitId = null): SalesOrderItem
{
    $item = $source->replicate();
    $item->sales_order_id = $orderId;
    $item->unit_id = $unitId ?: $source->unit_id;
    $item->quantity = 1;
    $item->save();

    return $item;
}

expectTrue(
    Schema::hasTable('transaction.sales_order_barcode_dispatches'),
    'Barcode dispatch table must exist.'
);
expectTrue(
    Schema::hasTable('transaction.sales_order_item_serial_assignments'),
    'Barcode serial assignment table must exist.'
);

$reportMenu = Menu::where('name', 'Barcode Dispatch')->firstOrFail();
expectTrue($reportMenu->parent?->name === 'Sales & Transaction', 'Barcode Dispatch report menu parent is incorrect.');
expectTrue(
    Menu::where('id', 'a0b1c2d3-0004-4a5b-8c9d-0e1f2a3b4c5e')->whereNull('deleted_at')->doesntExist(),
    'Legacy Point of Sales Barcode Dispatch operational menu must be removed.'
);
expectTrue(
    IamHasAccess::where('iam_access_id', 'b0763f22-c9d1-41de-b7b9-28b523a7a354')
        ->where('sidebar_menu_id', $reportMenu->id)
        ->where('is_read', true)
        ->exists(),
    'Administrator must receive default Barcode Dispatch report access.'
);
expectTrue(
    Route::has('transaction.pos.barcode-lookup'),
    'POS barcode lookup route must exist.'
);
expectTrue(
    in_array(
        'permission:Barcode Dispatch,is_read',
        Route::getRoutes()->getByName('reporting.barcode-dispatch.index')->gatherMiddleware(),
        true
    ),
    'Barcode Dispatch report route permission must exactly match the menu name.'
);

$service = app(BarcodeDispatchService::class);
$sourceOrder = null;
$sourceItem = null;
$serial = null;

foreach (
    SalesOrder::whereHas('customer.agent')
        ->with('items')
        ->orderByDesc('created_at')
        ->limit(50)
        ->get() as $candidateOrder
) {
    foreach ($candidateOrder->items as $candidateItem) {
        $candidateSerial = ProductLabelSerial::where('product_id', $candidateItem->product_id)
            ->where('unit_id', $candidateItem->unit_id)
            ->where(function ($query) use ($candidateItem): void {
                $query->whereNull('product_variant_id')
                    ->orWhere('product_variant_id', $candidateItem->product_variant_id);
            })
            ->whereDoesntHave('salesAssignments')
            ->first();

        if (! $candidateSerial || ! $candidateOrder->price_list_id || ! $candidateOrder->branch_id) {
            continue;
        }

        try {
            $service->lookupForPos(
                $candidateSerial->serial_number,
                $candidateOrder->branch_id,
                $candidateOrder->price_list_id
            );
        } catch (InvalidArgumentException) {
            continue;
        }

        $sourceOrder = $candidateOrder;
        $sourceItem = $candidateItem;
        $serial = $candidateSerial;
        break 2;
    }
}

expectTrue($sourceOrder !== null && $sourceItem !== null && $serial !== null, 'A priced Agent order item with an available serial is required.');

$mismatchedSerial = ProductLabelSerial::where('product_id', '!=', $sourceItem->product_id)
    ->whereNotIn(
        'serial_number',
        ProductLabelSerial::where('product_id', $sourceItem->product_id)->select('serial_number')
    )
    ->firstOrFail();
$untrackedUnit = ProductUnit::all()->first(
    fn (ProductUnit $unit): bool => ! ProductLabelSerial::where('product_id', $sourceItem->product_id)
        ->where('unit_id', $unit->id)
        ->exists()
);
$resellerCustomer = Customer::whereHas('reseller.agent')->firstOrFail();
$connection = DB::connection((new SalesOrder)->getConnectionName());

expectTrue($untrackedUnit !== null, 'An untracked product unit fixture is required.');

$connection->beginTransaction();

try {
    $lookup = $service->lookupForPos(
        $serial->serial_number,
        $sourceOrder->branch_id,
        $sourceOrder->price_list_id
    );
    expectTrue($lookup['serial_number'] === $serial->serial_number, 'POS lookup must return the scanned serial.');
    expectTrue(! empty($lookup['variant_id']), 'POS lookup must resolve a variant.');

    expectThrows(
        fn () => $service->lookupForPos(
            $mismatchedSerial->serial_number,
            $sourceOrder->branch_id,
            $sourceOrder->price_list_id,
            $sourceItem->product_id
        ),
        'tidak cocok'
    );

    $order = cloneOrder($sourceOrder, $sourceOrder->customer_id);
    $trackedItem = cloneItem($sourceItem, $order->id);
    $untrackedItem = cloneItem($sourceItem, $order->id, $untrackedUnit->id);

    $details = $service->details($order->id);
    expectTrue($details['destination']['agent'] !== null, 'Direct Agent destination must resolve.');
    expectTrue(
        $details['items']->first(fn (array $row): bool => $row['model']->id === $untrackedItem->id)['trackable'] === false,
        'Items without matching serials must be marked untracked.'
    );

    expectThrows(
        fn () => $service->assertCartSerialsForDestination($sourceOrder->customer_id, [[
            'product_id' => $trackedItem->product_id,
            'product_variant_id' => $trackedItem->product_variant_id,
            'unit_id' => $trackedItem->unit_id,
            'quantity' => 1,
            'serial_numbers' => [],
            'is_promo_free' => false,
        ]]),
        'Scan barcode serial wajib'
    );

    $service->assignSerialsForNewOrder(
        $order,
        [$trackedItem->id => [$serial->serial_number]],
        null
    );

    $duplicateOrder = cloneOrder($sourceOrder, $sourceOrder->customer_id);
    $duplicateItem = cloneItem($sourceItem, $duplicateOrder->id);
    expectThrows(
        fn () => $service->assignSerialsForNewOrder(
            $duplicateOrder,
            [$duplicateItem->id => [$serial->serial_number]],
            null
        ),
        'sudah pernah'
    );

    $dispatch = $service->finalizeIfEligible($order->id, null);
    expectTrue(
        $dispatch?->status === SalesOrderBarcodeDispatch::STATUS_COMPLETED,
        'Paid Agent order with serials must finalize barcode dispatch.'
    );

    $resellerOrder = cloneOrder($sourceOrder, $resellerCustomer->id);
    $resellerItem = cloneItem($sourceItem, $resellerOrder->id);
    $resellerDetails = $service->details($resellerOrder->id);
    expectTrue($resellerDetails['destination']['reseller'] !== null, 'Reseller destination must resolve.');
    expectTrue($resellerDetails['destination']['agent'] !== null, 'Reseller must resolve its parent Agent.');

    $resellerSerial = $serial->replicate();
    $resellerSerial->serial_number = 'TEST'.substr(str_replace('-', '', (string) Str::uuid()), 0, 16);
    $resellerSerial->save();
    $service->assignSerialsForNewOrder(
        $resellerOrder,
        [$resellerItem->id => [$resellerSerial->serial_number]],
        null
    );
    $service->finalizeIfEligible($resellerOrder->id, null);

    $reportFilters = [
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->addDay()->toDateString(),
        'branch_id' => null,
        'agent_id' => null,
        'customer_id' => null,
        'product_id' => null,
        'variant_id' => null,
        'unit_id' => null,
        'serial' => null,
        'sales_number' => 'TEST-',
    ];
    $reportRepository = app(BarcodeTrackingReportRepository::class);
    $reportRows = $reportRepository->rows($reportFilters);
    expectTrue($reportRows->count() === 2, 'Report must include direct Agent and Reseller dispatches.');
    expectTrue(
        $reportRows->firstWhere('sales_order_id', $resellerOrder->id)?->agent_id
            === $resellerDetails['destination']['agent']->id,
        'Report must map a Reseller dispatch to its parent Agent.'
    );

    $filtered = $reportFilters;
    $filtered['serial'] = $serial->serial_number;
    $pageCount = $reportRepository->paginate($filtered, 10)->total();
    $exportRows = app(BarcodeTrackingReportService::class)->exportRows($filtered);
    $exportCount = $exportRows->count();
    expectTrue($pageCount === 1 && $exportCount === 1, 'Report and Excel export must use identical filters.');
    expectTrue(
        strlen(Excel::raw(new BarcodeTrackingExport($exportRows), ExcelWriter::XLSX)) > 1000,
        'Excel export must produce a valid XLSX payload.'
    );
} finally {
    $connection->rollBack();
}

echo "Barcode dispatch tests passed.\n";
