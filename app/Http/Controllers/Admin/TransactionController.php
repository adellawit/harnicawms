<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItemSerialAssignment;
use App\Services\Sales\BarcodeDispatchService;
use App\Services\StockMutationService;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class TransactionController extends Controller
{
    protected function getBranchId(): ?string
    {
        return auth('web')->user()?->current_business_unit_id;
    }

    public function indexView(Request $request)
    {
        $defaultBranchId = $this->getBranchId();
        $branchId = $request->get('branch_id', $defaultBranchId);

        $status = $request->get('status', '');
        $paymentStatus = $request->get('payment_status', '');
        $orderType = $request->get('order_type', '');
        $dateFrom = $this->normalizeFilterDate($request->get('date_from'));
        $dateTo = $this->normalizeFilterDate($request->get('date_to'));
        $isFilter = $status !== ''
            || $paymentStatus !== ''
            || $orderType !== ''
            || $branchId !== $defaultBranchId
            || $dateFrom !== null
            || $dateTo !== null;

        return view('admin.transaction.index', compact(
            'status',
            'paymentStatus',
            'orderType',
            'isFilter',
            'branchId',
            'defaultBranchId',
            'dateFrom',
            'dateTo'
        ));
    }

    public function indexData(Request $request)
    {
        $defaultBranchId = $this->getBranchId();
        $branchId = $request->get('branch_id', $defaultBranchId);
        $dateFrom = $this->normalizeFilterDate($request->get('date_from'));
        $dateTo = $this->normalizeFilterDate($request->get('date_to'));

        $query = SalesOrder::with(['methodPayment:id,name', 'priceList:id,name,code'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($dateFrom, fn ($q) => $q->whereDate('sales_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sales_date', '<=', $dateTo));

        if ($request->status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->order_type) {
            $query->where('order_type', $request->order_type);
        }

        $query->orderByDesc('sales_date')->orderByDesc('created_at');

        return (new DataTables)->eloquent($query)
            ->addIndexColumn()
            ->addColumn('status_badge', function ($row) {
                if ($row->deleted_at) {
                    return '<span class="badge bg-label-danger">Deleted</span>';
                }
                $colors = [
                    'completed' => 'success', 'draft' => 'secondary',
                    'cancelled' => 'danger', 'pending' => 'warning',
                    'verification' => 'info',
                ];
                $color = $colors[$row->status] ?? 'info';
                return '<span class="badge bg-label-' . $color . '">' . ucfirst($row->status) . '</span>';
            })
            ->addColumn('payment_badge', function ($row) {
                $colors = ['paid' => 'success', 'unpaid' => 'danger', 'partial' => 'warning'];
                $color = $colors[$row->payment_status] ?? 'secondary';
                return '<span class="badge bg-label-' . $color . '">' . ucfirst($row->payment_status) . '</span>';
            })
            ->addColumn('total_fmt', fn ($row) => 'Rp '.format_number((float) $row->total, 2, true))
            ->addColumn('shipping_fmt', function ($row) {
                $html = 'Rp '.format_number((float) $row->shipping_amount, 2, true);
                $meta = $row->shippingMetaLabel();
                if ($meta) {
                    $html .= '<br><small class="text-muted">'.e($meta).'</small>';
                }

                return $html;
            })
            ->addColumn('method_payment_name', fn ($row) => $row->methodPayment?->name ?? '-')
            ->addColumn('customer_display', fn ($row) => $row->customer_name ?: '-')
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('sales_number', 'ilike', "%{$search}%")
                            ->orWhere('customer_name', 'ilike', "%{$search}%");
                    });
                }
            })
            ->rawColumns(['status_badge', 'payment_badge', 'shipping_fmt'])
            ->toJson();
    }

    public function verifyForm(string $id, BarcodeDispatchService $barcodeDispatch)
    {
        $order = SalesOrder::findOrFail($id);
        if ($order->status !== 'verification') {
            return redirect()->route('transaction.detail', $order->id)->with('error', 'This order is not awaiting verification.');
        }

        try {
            $details = $barcodeDispatch->details($order->id, $this->getBranchId());
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('transaction.detail', $order->id)->with('error', $e->getMessage());
        }

        $details['items'] = $this->withScannedSerials($details['items']);

        return view('admin.transaction.verify', $details);
    }

    public function verifyScan(Request $request, string $id, BarcodeDispatchService $barcodeDispatch)
    {
        $request->validate([
            'serial_number' => 'required|string|max:50',
        ]);

        try {
            $barcodeDispatch->scan($id, null, $request->serial_number, Auth::id(), $this->getBranchId());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return $this->verifyStateJson($id, $barcodeDispatch);
    }

    public function verifyScanRemove(string $id, string $assignmentId, BarcodeDispatchService $barcodeDispatch)
    {
        try {
            $barcodeDispatch->remove($id, $assignmentId, $this->getBranchId());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return $this->verifyStateJson($id, $barcodeDispatch);
    }

    public function verifySubmit(string $id, BarcodeDispatchService $barcodeDispatch)
    {
        $order = SalesOrder::with('items')->findOrFail($id);

        if ($order->status !== 'verification') {
            return back()->with('error', 'This order is not awaiting verification.');
        }

        $branchId = $this->getBranchId();
        $actorId = Auth::id();

        try {
            $details = $barcodeDispatch->details($order->id, $branchId);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('transaction.detail', $order->id)->with('error', $e->getMessage());
        }
        $hasTrackable = collect($details['items'])->contains(fn ($row) => $row['trackable']);

        // finishedGoodsWarehouse() is only a fallback source for $srcBranch —
        // web-order items already carry their own source_warehouse_id from
        // checkout (see PosCheckoutService::createSalesOrder()), so this is
        // deliberately NOT hard-required up front the way an earlier version
        // of this method did; requiring it here would 422 orders whose items
        // are already fully resolved. The actual, authoritative check is the
        // per-item abort_unless() inside the transaction below, exactly
        // mirroring AgentOrderController::receiveOrder()'s existing pattern.
        $fgWarehouse = WmsContext::finishedGoodsWarehouse($order->company_id);
        $sourceWhId = $fgWarehouse?->id;
        $srcBranch = $fgWarehouse?->branch_id ?: ($order->branch_id ?: $sourceWhId);

        try {
            DB::transaction(function () use ($order, $sourceWhId, $srcBranch, $actorId, $hasTrackable, $barcodeDispatch, $branchId) {
                $order = SalesOrder::lockForUpdate()->with('items')->findOrFail($order->id);

                if ($order->status !== 'verification') {
                    throw new \RuntimeException('This order is no longer awaiting verification.');
                }

                // finalize() lives INSIDE this transaction, not before it: it's
                // terminal/immutable once committed (BarcodeDispatchService
                // rejects any further scan/finalize on a completed dispatch),
                // so if anything below fails (most commonly insufficient stock
                // from StockMutationService::outbound()), the whole transaction
                // — finalize included — rolls back together, leaving the order
                // free to be retried from scratch instead of permanently stuck
                // with a completed dispatch but no stock movement.
                if ($hasTrackable) {
                    $barcodeDispatch->finalize($order->id, $actorId, $branchId);
                }

                foreach ($order->items as $item) {
                    $product = Product::find($item->product_id);
                    if (! $product?->is_stock_item) {
                        continue;
                    }

                    $outboundWarehouseId = $item->source_warehouse_id ?: ($sourceWhId ?: $order->warehouse_id);
                    abort_unless($outboundWarehouseId && $srcBranch, 422, 'Gudang asal pengiriman belum diset.');

                    $outbound = StockMutationService::outbound(
                        $item->product_id,
                        $item->product_variant_id,
                        $order->company_id,
                        (string) $srcBranch,
                        $item->unit_id,
                        (float) $item->quantity,
                        SalesOrder::class,
                        $order->id,
                        $actorId,
                        'Verifikasi & kirim - '.$order->sales_number,
                        $outboundWarehouseId
                    );

                    $item->update(['outbound_expiry_date' => $outbound['earliest_expiry']]);
                }

                $order->update(['status' => 'shipped', 'updated_by' => $actorId]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('transaction.detail', $order->id)->with('success', 'Order verified and marked as shipped.');
    }

    private function verifyStateJson(string $orderId, BarcodeDispatchService $barcodeDispatch)
    {
        $details = $barcodeDispatch->details($orderId, $this->getBranchId());

        return response()->json([
            'success' => true,
            'items' => $this->withScannedSerials($details['items']),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{model: \App\Models\SalesOrderItem, trackable: bool, expected: int, scanned: int, complete: bool}>  $items
     * @return array<int, array<string, mixed>>
     */
    private function withScannedSerials($items): array
    {
        $itemIds = collect($items)->pluck('model.id');
        $assignments = SalesOrderItemSerialAssignment::whereIn('sales_order_item_id', $itemIds)
            ->with('serial:id,serial_number')
            ->get()
            ->groupBy('sales_order_item_id');

        return collect($items)->map(function (array $row) use ($assignments) {
            $itemAssignments = $assignments->get($row['model']->id, collect());

            return [
                'id' => $row['model']->id,
                'trackable' => $row['trackable'],
                'expected' => $row['expected'],
                'scanned' => $row['scanned'],
                'complete' => $row['complete'],
                'serials' => $itemAssignments->map(fn ($a) => [
                    'assignment_id' => $a->id,
                    'serial_number' => $a->serial?->serial_number,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Accept Y-m-d or d/m/Y; return Y-m-d or null.
     */
    private function normalizeFilterDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $value;
            }

            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $value)) {
                return \Carbon\Carbon::createFromFormat('d/m/Y', $value)->toDateString();
            }

            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function detailView(string $id)
    {
        $order = SalesOrder::with([
            'items.product:id,name,sku',
            'items.variant:id,sku',
            'items.variant.variantAttributes.attributeValue',
            'items.variant.variantAttributes.attributeDefinition:id,name',
            'items.unit:id,name,symbol',
            'payments.methodPayment:id,name',
            'methodPayment:id,name',
            'priceList:id,name,code',
            'customer:id,name,code',
            'branch:id,name',
            'createdByUser:id,first_name,last_name',
            'paymentGatewayCallbacks' => fn ($q) => $q->orderByDesc('created_at'),
        ])->withTrashed()->findOrFail($id);

        return view('admin.transaction.detail', compact('order'));
    }

    public function printInvoice(string $id)
    {
        $order = $this->loadOrderForPrint($id);

        return view('admin.transaction.print-invoice', [
            'order' => $order,
            'backUrl' => route('transaction.detail', $order->id),
            'autoPrint' => request()->boolean('print'),
            'printButtonLabel' => 'Print Invoice',
        ]);
    }

    public function printShipping(string $id)
    {
        $order = $this->loadOrderForPrint($id);

        return view('admin.transaction.print-shipping', [
            'order' => $order,
            'backUrl' => route('transaction.detail', $order->id),
            'autoPrint' => request()->boolean('print'),
            'printButtonLabel' => 'Print Surat Jalan',
        ]);
    }

    private function loadOrderForPrint(string $id): SalesOrder
    {
        return SalesOrder::with([
            'items.product:id,name,sku',
            'items.variant:id,sku',
            'items.variant.variantAttributes.attributeValue',
            'items.variant.variantAttributes.attributeDefinition:id,name',
            'items.unit:id,name,symbol',
            'payments.methodPayment:id,name',
            'methodPayment:id,name',
            'customer:id,name,code,phone,address',
            'branch:id,name,brand_name,address,phone,email,parent_id',
            'branch.parent:id,name,brand_name',
            'warehouse:id,name,code',
        ])->withTrashed()->findOrFail($id);
    }
}
