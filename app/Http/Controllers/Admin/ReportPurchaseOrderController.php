<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\ProductPurchaseOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportPurchaseOrderController extends Controller
{
    /**
     * Statuses treated as "cancelled" (excluded from finance aggregations by default).
     */
    protected array $cancelledStatuses = ['cancelled', 'void'];

    protected function getBranchId(): ?string
    {
        return auth('web')->user()?->current_business_unit_id;
    }

    public function indexView(Request $request)
    {
        $defaultBranchId = $this->getBranchId();
        $branchId = $request->get('branch_id', $defaultBranchId);
        $supplierId = $request->get('supplier_id');
        $status = $request->get('status');

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (!$dateFrom || !$dateTo) {
            $today = now();
            $dateFrom = $today->copy()->startOfMonth()->toDateString();
            $dateTo = $today->toDateString();
        }

        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();
        $daysInRange = max(1, $from->diffInDays($to) + 1);

        $baseQuery = ProductPurchaseOrder::query()
            ->whereDate('purchase_date', '>=', $dateFrom)
            ->whereDate('purchase_date', '<=', $dateTo)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($status, fn ($q) => $q->where('status', $status));

        // KPI ----------------------------------------------------------------
        $financeQuery = (clone $baseQuery)->whereNotIn('status', $this->cancelledStatuses);

        $totalPoCount = (int) (clone $baseQuery)->count();
        $totalPoActive = (int) (clone $financeQuery)->count();
        $totalPurchase = (float) (clone $financeQuery)->sum('total');
        $totalSubtotal = (float) (clone $financeQuery)->sum('subtotal');
        $totalTax = (float) (clone $financeQuery)->sum('tax_amount');
        $totalDiscount = (float) (clone $financeQuery)->sum('discount_amount');
        $avgPoValue = $totalPoActive > 0 ? $totalPurchase / $totalPoActive : 0.0;

        $statusAgg = (clone $baseQuery)
            ->selectRaw("status, COUNT(*) as cnt, COALESCE(SUM(total), 0) as total")
            ->groupBy('status')
            ->orderByDesc('cnt')
            ->get();

        $totalCancelled = (int) $statusAgg->whereIn('status', $this->cancelledStatuses)->sum('cnt');
        $totalReceived = (int) $statusAgg->where('status', 'received')->sum('cnt');
        $totalPending = $totalPoCount - $totalReceived - $totalCancelled;

        // Daily trend --------------------------------------------------------
        $dailyTrend = (clone $financeQuery)
            ->select(
                DB::raw("DATE(purchase_date) as date"),
                DB::raw("COUNT(*) as po_count"),
                DB::raw("COALESCE(SUM(total), 0) as total_amount")
            )
            ->groupBy(DB::raw("DATE(purchase_date)"))
            ->orderBy('date')
            ->get();

        // Top suppliers ------------------------------------------------------
        $topSuppliers = DB::table('product.purchase_orders as po')
            ->leftJoin('master_data.suppliers as s', 's.id', '=', 'po.supplier_id')
            ->whereDate('po.purchase_date', '>=', $dateFrom)
            ->whereDate('po.purchase_date', '<=', $dateTo)
            ->when($branchId, fn ($q) => $q->where('po.branch_id', $branchId))
            ->when($supplierId, fn ($q) => $q->where('po.supplier_id', $supplierId))
            ->when($status, fn ($q) => $q->where('po.status', $status))
            ->whereNotIn('po.status', $this->cancelledStatuses)
            ->whereNull('po.deleted_at')
            ->select(
                DB::raw("COALESCE(s.name, po.supplier_name, '-') as supplier"),
                DB::raw("COUNT(po.id) as po_count"),
                DB::raw("COALESCE(SUM(po.total), 0) as total_amount"),
                DB::raw("COALESCE(AVG(po.total), 0) as avg_amount")
            )
            ->groupBy(DB::raw("COALESCE(s.name, po.supplier_name, '-')"))
            ->orderByDesc('total_amount')
            ->limit(15)
            ->get();

        // Top purchased products --------------------------------------------
        $topProducts = DB::table('product.purchase_order_items as poi')
            ->join('product.purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->leftJoin('product.products as p', 'p.id', '=', 'poi.product_id')
            ->whereDate('po.purchase_date', '>=', $dateFrom)
            ->whereDate('po.purchase_date', '<=', $dateTo)
            ->when($branchId, fn ($q) => $q->where('po.branch_id', $branchId))
            ->when($supplierId, fn ($q) => $q->where('po.supplier_id', $supplierId))
            ->when($status, fn ($q) => $q->where('po.status', $status))
            ->whereNotIn('po.status', $this->cancelledStatuses)
            ->whereNull('po.deleted_at')
            ->whereNull('poi.deleted_at')
            ->select(
                DB::raw("COALESCE(p.name, '-') as product"),
                DB::raw("SUM(poi.quantity) as qty"),
                DB::raw("SUM(poi.subtotal) as subtotal"),
                DB::raw("CASE WHEN SUM(poi.quantity) > 0 THEN SUM(poi.subtotal) / SUM(poi.quantity) ELSE 0 END as avg_unit_price")
            )
            ->groupBy(DB::raw("COALESCE(p.name, '-')"))
            ->orderByDesc('subtotal')
            ->limit(15)
            ->get();

        // Latest PO table ----------------------------------------------------
        $latestPO = (clone $baseQuery)
            ->with(['supplier:id,name', 'branch:id,name'])
            ->orderByDesc('purchase_date')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get([
                'id', 'purchase_number', 'purchase_date', 'expected_delivery_date',
                'supplier_id', 'supplier_name', 'branch_id', 'status',
                'subtotal', 'tax_amount', 'discount_amount', 'total',
            ]);

        // Support data -------------------------------------------------------
        $branches = BusinessUnit::where('is_active', true)
            ->where('type_code', 'BRANCH')
            ->orderBy('name')
            ->get(['id', 'name']);

        $suppliers = DB::table('master_data.suppliers')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $statusOptions = [
            'draft' => 'Draft',
            'process' => 'Process',
            'receiving' => 'Receiving',
            'payment' => 'Payment',
            'received' => 'Received',
            'cancelled' => 'Cancelled',
        ];

        return view('admin.reporting.purchase-order.index', [
            'branches' => $branches,
            'suppliers' => $suppliers,
            'statusOptions' => $statusOptions,
            'branchId' => $branchId,
            'supplierId' => $supplierId,
            'status' => $status,
            'defaultBranchId' => $defaultBranchId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'daysInRange' => $daysInRange,

            'totalPoCount' => $totalPoCount,
            'totalPoActive' => $totalPoActive,
            'totalPurchase' => $totalPurchase,
            'totalSubtotal' => $totalSubtotal,
            'totalTax' => $totalTax,
            'totalDiscount' => $totalDiscount,
            'avgPoValue' => $avgPoValue,

            'totalReceived' => $totalReceived,
            'totalPending' => $totalPending,
            'totalCancelled' => $totalCancelled,

            'statusAgg' => $statusAgg,
            'dailyTrend' => $dailyTrend,
            'topSuppliers' => $topSuppliers,
            'topProducts' => $topProducts,
            'latestPO' => $latestPO,
        ]);
    }
}
