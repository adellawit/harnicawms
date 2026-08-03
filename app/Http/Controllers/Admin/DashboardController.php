<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPurchaseOrder;
use App\Models\ProductPurchaseOrderReceive;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\DashboardConfiguration;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesOrderPayment;
use App\Models\Supplier;
use App\Support\WmsContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function indexView(Request $request)
    {
        $user = Auth::user();
        $roleId = $user->role_id;
        $defaultBranchId = $user->current_business_unit_id;
        $branchId = $request->get('branch_id', $defaultBranchId);

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $monthStart = $today->copy()->startOfMonth();

        // Hanya terima periode custom jika Apply sengaja mengirim period_custom=1.
        // Query date_from/date_to lama (tanpa flag) diabaikan & dibersihkan dari URL.
        $useCustomPeriod = $request->boolean('period_custom');
        if (! $useCustomPeriod && ($request->filled('date_from') || $request->filled('date_to'))) {
            return redirect()->route('dashboard', array_filter([
                'branch_id' => $request->query('branch_id'),
            ]));
        }

        // Default periode: tanggal 1 bulan berjalan → hari ini (besok otomatis 1 → 4, dst.)
        $periodEnd = $useCustomPeriod && $request->filled('date_to')
            ? $this->parseDashboardDate($request->get('date_to'), $today)
            : $today->copy();
        if ($periodEnd->gt($today)) {
            $periodEnd = $today->copy();
        }

        $periodStart = $useCustomPeriod && $request->filled('date_from')
            ? $this->parseDashboardDate($request->get('date_from'), $monthStart)
            : $monthStart->copy();
        if ($periodStart->gt($periodEnd)) {
            $periodStart = $periodEnd->copy()->startOfMonth();
        }

        $periodDays = $periodStart->diffInDays($periodEnd) + 1;
        $prevPeriodEnd = $periodStart->copy()->subDay();
        $prevPeriodStart = $prevPeriodEnd->copy()->subDays($periodDays - 1);

        $outlets = BusinessUnit::where('is_active', true)
            ->where('type_code', 'BRANCH')
            ->orderBy('name')
            ->get(['id', 'name', 'type_code']);

        $dashboardVisibility = DashboardConfiguration::getConfigForRole($roleId);

        try {
            $data = $this->buildDashboardData(
                $today,
                $yesterday,
                $periodStart,
                $prevPeriodStart,
                $prevPeriodEnd,
                $branchId,
                $periodEnd
            );
        } catch (\Throwable $e) {
            Log::error('Dashboard data error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $data = $this->emptyDashboardData();
        }

        return view('dashboard', array_merge($data, [
            'dashboardVisibility' => $dashboardVisibility,
            'roleId' => $roleId,
            'outlets' => $outlets,
            'currentBranchId' => $branchId,
            'defaultBranchId' => $defaultBranchId,
            'today' => $today,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
        ]));
    }

    private function parseDashboardDate(?string $value, Carbon $fallback): Carbon
    {
        if (! $value) {
            return $fallback->copy();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            try {
                return Carbon::parse($value)->startOfDay();
            } catch (\Throwable) {
                return $fallback->copy();
            }
        }
    }

    private function buildDashboardData(
        Carbon $today,
        Carbon $yesterday,
        Carbon $startOfMonth,
        Carbon $startOfLastMonth,
        Carbon $endOfLastMonth,
        ?string $branchId,
        ?Carbon $periodEnd = null
    ): array
    {
        $periodEnd = $periodEnd?->copy() ?? $today->copy();
        $validStatuses = ['completed', 'paid', 'fulfilled', 'process', 'payment'];
        $cancelledStatuses = ['cancelled', 'void', 'refund'];

        // ── SALES KPIs ──
        $revenueToday = SalesOrder::whereDate('sales_date', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', $cancelledStatuses)
            ->sum('total');

        $revenueYesterday = SalesOrder::whereDate('sales_date', $yesterday)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', $cancelledStatuses)
            ->sum('total');

        $revenueThisMonth = SalesOrder::whereDate('sales_date', '>=', $startOfMonth)
            ->whereDate('sales_date', '<=', $periodEnd)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', $cancelledStatuses)
            ->sum('total');

        $revenueLastMonth = SalesOrder::whereDate('sales_date', '>=', $startOfLastMonth)
            ->whereDate('sales_date', '<=', $endOfLastMonth)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', $cancelledStatuses)
            ->sum('total');

        $txToday = SalesOrder::whereDate('sales_date', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', $cancelledStatuses)
            ->count();

        $txYesterday = SalesOrder::whereDate('sales_date', $yesterday)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', $cancelledStatuses)
            ->count();

        $txThisMonth = SalesOrder::whereDate('sales_date', '>=', $startOfMonth)
            ->whereDate('sales_date', '<=', $periodEnd)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', $cancelledStatuses)
            ->count();

        $aov = $txToday > 0 ? $revenueToday / $txToday : 0;

        // ── SALES TREND (last 7 days) ──
        $dailySalesTrend = SalesOrder::select(
                DB::raw("DATE(sales_date) as date"),
                DB::raw("COALESCE(SUM(total), 0) as revenue"),
                DB::raw("COUNT(*) as transactions")
            )
            ->whereDate('sales_date', '>=', $today->copy()->subDays(6))
            ->whereDate('sales_date', '<=', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', $cancelledStatuses)
            ->groupBy(DB::raw('DATE(sales_date)'))
            ->orderBy('date')
            ->get();

        $dailySalesTrend = $this->padDailySalesTrend($dailySalesTrend, $today);

        // ── MONTHLY TREND (last 6 months) ──
        $monthlySalesTrend = SalesOrder::select(
                DB::raw("TO_CHAR(sales_date, 'YYYY-MM') as month"),
                DB::raw("COALESCE(SUM(total), 0) as revenue"),
                DB::raw("COUNT(*) as transactions")
            )
            ->whereDate('sales_date', '>=', $today->copy()->subMonths(5)->startOfMonth())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', $cancelledStatuses)
            ->groupBy(DB::raw("TO_CHAR(sales_date, 'YYYY-MM')"))
            ->orderBy('month')
            ->get();

        $monthlySalesTrend = $this->padMonthlySalesTrend($monthlySalesTrend, $today);

        // ── PAYMENT METHODS ──
        $paymentMethods = DB::table('transaction.sales_order_payments as sop')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'sop.sales_order_id')
            ->join('master_data.method_payments as mp', 'mp.id', '=', 'sop.method_payment_id')
            ->whereDate('so.sales_date', '>=', $startOfMonth)
            ->whereDate('so.sales_date', '<=', $periodEnd)
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->whereNotIn('so.status', $cancelledStatuses)
            ->whereNull('sop.deleted_at')
            ->whereNull('so.deleted_at')
            ->select('mp.name', DB::raw('COALESCE(SUM(sop.amount - sop.change_amount), 0) as total'))
            ->groupBy('mp.name')
            ->orderByDesc('total')
            ->get();

        $paymentTotal = $paymentMethods->sum('total');

        // ── TOP SELLING PRODUCTS ──
        $topProducts = DB::table('transaction.sales_order_items as soi')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->join('product.products as p', 'p.id', '=', 'soi.product_id')
            ->whereDate('so.sales_date', '>=', $startOfMonth)
            ->whereDate('so.sales_date', '<=', $periodEnd)
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->whereNotIn('so.status', $cancelledStatuses)
            ->whereNull('soi.deleted_at')
            ->whereNull('so.deleted_at')
            ->select('p.name', DB::raw('SUM(soi.quantity) as qty'), DB::raw('SUM(soi.subtotal) as revenue'))
            ->groupBy('p.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        // ── TOP CATEGORIES ──
        $topCategories = DB::table('transaction.sales_order_items as soi')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->join('product.products as p', 'p.id', '=', 'soi.product_id')
            ->leftJoin('product.product_categories as pc', 'pc.id', '=', 'p.category_id')
            ->whereDate('so.sales_date', '>=', $startOfMonth)
            ->whereDate('so.sales_date', '<=', $periodEnd)
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->whereNotIn('so.status', $cancelledStatuses)
            ->whereNull('soi.deleted_at')
            ->whereNull('so.deleted_at')
            ->select(DB::raw("COALESCE(pc.name, 'Uncategorized') as category"), DB::raw('SUM(soi.quantity) as qty'), DB::raw('SUM(soi.subtotal) as revenue'))
            ->groupBy('pc.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        // ── SALES PER OUTLET ──
        $salesPerOutlet = DB::table('transaction.sales_orders as so')
            ->join('master_data.business_units as bu', 'bu.id', '=', 'so.branch_id')
            ->whereDate('so.sales_date', '>=', $startOfMonth)
            ->whereDate('so.sales_date', '<=', $periodEnd)
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->whereNotIn('so.status', $cancelledStatuses)
            ->whereNull('so.deleted_at')
            ->select('bu.name', DB::raw('COALESCE(SUM(so.total), 0) as revenue'), DB::raw('COUNT(*) as transactions'))
            ->groupBy('bu.name')
            ->orderByDesc('revenue')
            ->get();

        // ── BEST OUTLET & PRODUCT (Executive) ──
        $bestOutlet = $salesPerOutlet->first();
        $bestProduct = $topProducts->first();

        // ── INVENTORY KPIs ──
        $totalSku = Product::where('is_stock_item', true)->count();
        $warehouseId = $branchId ? optional(WmsContext::defaultWarehouse($branchId))->id : null;
        $totalStock = (float) ProductVariantStock::when($branchId, function ($query) use ($branchId, $warehouseId) {
            $query->when(
                $warehouseId,
                fn ($q) => $q->where('warehouse_id', $warehouseId),
                fn ($q) => $q->where('branch_id', $branchId)
            );
        })->sum('quantity');

        $inventoryValue = (float) DB::table('product.product_variant_stock as pvs')
            ->join('product.product_variants as pv', 'pv.id', '=', 'pvs.product_variant_id')
            ->whereNull('pvs.deleted_at')
            ->when($branchId, function ($query) use ($branchId, $warehouseId) {
                $query->when(
                    $warehouseId,
                    fn ($q) => $q->where('pvs.warehouse_id', $warehouseId),
                    fn ($q) => $q->where('pvs.branch_id', $branchId)
                );
            })
            ->select(DB::raw('COALESCE(SUM(pvs.quantity * pv.purchase_price), 0) as value'))
            ->value('value');

        // Low stock: products where total stock < min_stock
        $lowStockProducts = DB::table('product.products as p')
            ->join('product.product_variant_stock as pvs', 'pvs.product_id', '=', 'p.id')
            ->leftJoin('master_data.business_units as bu', 'bu.id', '=', 'pvs.branch_id')
            ->whereNull('p.deleted_at')
            ->whereNull('pvs.deleted_at')
            ->where('p.is_stock_item', true)
            ->when($branchId, function ($query) use ($branchId, $warehouseId) {
                $query->when(
                    $warehouseId,
                    fn ($q) => $q->where('pvs.warehouse_id', $warehouseId),
                    fn ($q) => $q->where('pvs.branch_id', $branchId)
                );
            })
            ->whereRaw('pvs.quantity <= p.min_stock')
            ->where('pvs.quantity', '>', 0)
            ->select('p.sku', 'p.name', DB::raw("COALESCE(bu.name, '-') as warehouse"), 'pvs.quantity as on_hand', 'p.min_stock')
            ->orderBy('pvs.quantity')
            ->limit(10)
            ->get();

        $lowStockCount = $lowStockProducts->count();

        $outOfStockCount = DB::table('product.products as p')
            ->join('product.product_variant_stock as pvs', 'pvs.product_id', '=', 'p.id')
            ->whereNull('p.deleted_at')
            ->whereNull('pvs.deleted_at')
            ->where('p.is_stock_item', true)
            ->when($branchId, function ($query) use ($branchId, $warehouseId) {
                $query->when(
                    $warehouseId,
                    fn ($q) => $q->where('pvs.warehouse_id', $warehouseId),
                    fn ($q) => $q->where('pvs.branch_id', $branchId)
                );
            })
            ->where('pvs.quantity', '<=', 0)
            ->distinct('p.id')
            ->count('p.id');

        // Stock by category
        $stockByCategory = DB::table('product.product_variant_stock as pvs')
            ->join('product.products as p', 'p.id', '=', 'pvs.product_id')
            ->leftJoin('product.product_categories as pc', 'pc.id', '=', 'p.category_id')
            ->whereNull('pvs.deleted_at')
            ->whereNull('p.deleted_at')
            ->when($branchId, function ($query) use ($branchId, $warehouseId) {
                $query->when(
                    $warehouseId,
                    fn ($q) => $q->where('pvs.warehouse_id', $warehouseId),
                    fn ($q) => $q->where('pvs.branch_id', $branchId)
                );
            })
            ->select(DB::raw("COALESCE(pc.name, 'Uncategorized') as category"), DB::raw('SUM(pvs.quantity) as qty'))
            ->groupBy('pc.name')
            ->orderByDesc('qty')
            ->limit(8)
            ->get();

        // Dead stock (no movement in 30 days)
        $deadStockSql = "
            SELECT p.sku, p.name, COALESCE(bu.name, '-') as warehouse,
                   pvs.quantity as on_hand,
                   EXTRACT(DAY FROM NOW() - COALESCE(last_move.last_date, pvs.created_at))::int as aging_days
            FROM product.product_variant_stock pvs
            JOIN product.products p ON p.id = pvs.product_id
            LEFT JOIN master_data.business_units bu ON bu.id = pvs.branch_id
            LEFT JOIN LATERAL (
                SELECT MAX(psm.created_at) as last_date
                FROM product.product_stock_movements psm
                WHERE psm.product_variant_stock_id = pvs.id
                  AND psm.deleted_at IS NULL
            ) last_move ON true
            WHERE pvs.deleted_at IS NULL
              AND p.deleted_at IS NULL
              AND p.is_stock_item = true
              AND pvs.quantity > 0
              AND (last_move.last_date IS NULL OR last_move.last_date < NOW() - INTERVAL '30 days')
            ORDER BY aging_days DESC
            LIMIT 10
        ";
        if ($branchId) {
            if ($warehouseId) {
                $deadStockSql = str_replace('WHERE pvs.deleted_at IS NULL', "WHERE pvs.deleted_at IS NULL AND pvs.warehouse_id = :warehouse_id", $deadStockSql);
                $deadStockProducts = DB::select($deadStockSql, ['warehouse_id' => $warehouseId]);
            } else {
                $deadStockSql = str_replace('WHERE pvs.deleted_at IS NULL', "WHERE pvs.deleted_at IS NULL AND pvs.branch_id = :branch_id", $deadStockSql);
                $deadStockProducts = DB::select($deadStockSql, ['branch_id' => $branchId]);
            }
        } else {
            $deadStockProducts = DB::select($deadStockSql);
        }

        // ── PROCUREMENT KPIs ──
        $totalPO = ProductPurchaseOrder::whereDate('purchase_date', '>=', $startOfMonth)
            ->whereDate('purchase_date', '<=', $periodEnd)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('deleted_at')
            ->count();

        $pendingPO = ProductPurchaseOrder::whereIn('status', ['draft', 'process', 'receiving'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('deleted_at')
            ->count();

        $receivedToday = ProductPurchaseOrderReceive::whereDate('receive_date', $today)
            ->when($branchId, fn ($q) => $q->whereHas('purchaseOrder', fn ($po) => $po->where('branch_id', $branchId)))
            ->whereNull('deleted_at')
            ->count();

        $poByStatus = ProductPurchaseOrder::select('status', DB::raw('COUNT(*) as count'))
            ->whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('purchase_date', '>=', $startOfMonth)
            ->whereDate('purchase_date', '<=', $periodEnd)
            ->groupBy('status')
            ->get();

        $topSuppliers = DB::table('product.purchase_orders as po')
            ->join('master_data.suppliers as s', 's.id', '=', 'po.supplier_id')
            ->whereNull('po.deleted_at')
            ->whereDate('po.purchase_date', '>=', $today->copy()->subMonths(3))
            ->when($branchId, fn ($q) => $q->where('po.branch_id', $branchId))
            ->select('s.name', DB::raw('COUNT(*) as po_count'), DB::raw('COALESCE(SUM(po.total), 0) as total_purchase'))
            ->groupBy('s.name')
            ->orderByDesc('total_purchase')
            ->limit(5)
            ->get();

        // ── WAREHOUSE / WMS KPIs ──
        $inboundToday = ProductStockMovement::where('type', 'in')
            ->whereDate('created_at', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('deleted_at')
            ->sum('quantity');

        $outboundToday = ProductStockMovement::where('type', 'out')
            ->whereDate('created_at', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('deleted_at')
            ->sum('quantity');

        $inboundTodayCount = ProductStockMovement::where('type', 'in')
            ->whereDate('created_at', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('deleted_at')
            ->count();

        $outboundTodayCount = ProductStockMovement::where('type', 'out')
            ->whereDate('created_at', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('deleted_at')
            ->count();

        $wmsWeeklyTrend = ProductStockMovement::select(
                DB::raw("DATE(created_at) as date"),
                'type',
                DB::raw("COALESCE(SUM(quantity), 0) as qty")
            )
            ->whereDate('created_at', '>=', $today->copy()->subDays(6))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('deleted_at')
            ->groupBy(DB::raw('DATE(created_at)'), 'type')
            ->orderBy('date')
            ->get();

        $recentActivities = ProductStockMovement::with(['product', 'stockMutationType'])
            ->whereDate('created_at', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // ── OUTLET OPERATIONS ──
        $refundCount = SalesOrder::whereDate('sales_date', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'refund')
            ->count();

        $voidCount = SalesOrder::whereDate('sales_date', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'void')
            ->count();

        $hourlySales = SalesOrder::select(
                DB::raw("EXTRACT(HOUR FROM sales_date::timestamp) as hour"),
                DB::raw("COALESCE(SUM(total), 0) as revenue"),
                DB::raw("COUNT(*) as transactions")
            )
            ->whereDate('sales_date', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', $cancelledStatuses)
            ->groupBy(DB::raw("EXTRACT(HOUR FROM sales_date::timestamp)"))
            ->orderBy('hour')
            ->get();

        // ── FINANCE ──
        $cogsThisMonth = (float) DB::table('transaction.sales_order_items as soi')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->join('product.product_variants as pv', 'pv.id', '=', 'soi.product_variant_id')
            ->whereDate('so.sales_date', '>=', $startOfMonth)
            ->whereDate('so.sales_date', '<=', $periodEnd)
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->whereNotIn('so.status', $cancelledStatuses)
            ->whereNull('soi.deleted_at')
            ->whereNull('so.deleted_at')
            ->select(DB::raw('COALESCE(SUM(soi.quantity * pv.purchase_price), 0) as cogs'))
            ->value('cogs');

        $grossProfit = (float) $revenueThisMonth - $cogsThisMonth;
        $profitMargin = $revenueThisMonth > 0 ? ($grossProfit / (float) $revenueThisMonth) * 100 : 0;

        $topProfitProducts = DB::table('transaction.sales_order_items as soi')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->join('product.products as p', 'p.id', '=', 'soi.product_id')
            ->leftJoin('product.product_variants as pv', 'pv.id', '=', 'soi.product_variant_id')
            ->whereDate('so.sales_date', '>=', $startOfMonth)
            ->whereDate('so.sales_date', '<=', $periodEnd)
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->whereNotIn('so.status', $cancelledStatuses)
            ->whereNull('soi.deleted_at')
            ->whereNull('so.deleted_at')
            ->select(
                'p.name',
                DB::raw('SUM(soi.subtotal) as revenue'),
                DB::raw('COALESCE(SUM(soi.quantity * pv.purchase_price), 0) as cogs'),
                DB::raw('SUM(soi.subtotal) - COALESCE(SUM(soi.quantity * pv.purchase_price), 0) as profit')
            )
            ->groupBy('p.name')
            ->orderByDesc('profit')
            ->limit(5)
            ->get();

        // ── CUSTOMER KPIs ──
        $totalCustomers = Customer::whereNull('deleted_at')->count();
        $newCustomersMonth = Customer::whereDate('created_at', '>=', $startOfMonth)
            ->whereDate('created_at', '<=', $periodEnd)
            ->whereNull('deleted_at')
            ->count();

        $customerWithOrders = SalesOrder::whereDate('sales_date', '>=', $startOfMonth)
            ->whereDate('sales_date', '<=', $periodEnd)
            ->whereNotIn('status', $cancelledStatuses)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');

        $returningCustomers = DB::table('transaction.sales_orders as so')
            ->whereDate('so.sales_date', '>=', $startOfMonth)
            ->whereDate('so.sales_date', '<=', $periodEnd)
            ->whereNotIn('so.status', $cancelledStatuses)
            ->whereNull('so.deleted_at')
            ->whereNotNull('so.customer_id')
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->select('so.customer_id')
            ->groupBy('so.customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $returningPct = $customerWithOrders > 0 ? round(($returningCustomers / $customerWithOrders) * 100, 1) : 0;

        $avgPurchasePerCustomer = $customerWithOrders > 0 ? (float) $revenueThisMonth / $customerWithOrders : 0;

        $topCustomers = DB::table('transaction.sales_orders as so')
            ->join('customer.customers as c', 'c.id', '=', 'so.customer_id')
            ->whereDate('so.sales_date', '>=', $startOfMonth)
            ->whereDate('so.sales_date', '<=', $periodEnd)
            ->whereNotIn('so.status', $cancelledStatuses)
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->whereNull('so.deleted_at')
            ->select('c.name', DB::raw('COUNT(*) as orders'), DB::raw('COALESCE(SUM(so.total), 0) as revenue'))
            ->groupBy('c.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        // ── ALERTS ──
        $deadStockAlertCount = count($deadStockProducts);

        return [
            'revenueToday' => (float) $revenueToday,
            'revenueYesterday' => (float) $revenueYesterday,
            'revenueThisMonth' => (float) $revenueThisMonth,
            'revenueLastMonth' => (float) $revenueLastMonth,
            'txToday' => $txToday,
            'txYesterday' => $txYesterday,
            'txThisMonth' => $txThisMonth,
            'aov' => $aov,
            'dailySalesTrend' => $dailySalesTrend,
            'monthlySalesTrend' => $monthlySalesTrend,
            'paymentMethods' => $paymentMethods,
            'paymentTotal' => $paymentTotal,
            'topProducts' => $topProducts,
            'topCategories' => $topCategories,
            'salesPerOutlet' => $salesPerOutlet,
            'bestOutlet' => $bestOutlet,
            'bestProduct' => $bestProduct,
            'totalSku' => $totalSku,
            'totalStock' => $totalStock,
            'inventoryValue' => $inventoryValue,
            'lowStockProducts' => $lowStockProducts,
            'lowStockCount' => $lowStockCount,
            'outOfStockCount' => $outOfStockCount,
            'stockByCategory' => $stockByCategory,
            'deadStockProducts' => $deadStockProducts,
            'deadStockAlertCount' => $deadStockAlertCount,
            'totalPO' => $totalPO,
            'pendingPO' => $pendingPO,
            'receivedToday' => $receivedToday,
            'poByStatus' => $poByStatus,
            'topSuppliers' => $topSuppliers,
            'inboundToday' => (float) $inboundToday,
            'outboundToday' => (float) $outboundToday,
            'inboundTodayCount' => $inboundTodayCount,
            'outboundTodayCount' => $outboundTodayCount,
            'wmsWeeklyTrend' => $wmsWeeklyTrend,
            'recentActivities' => $recentActivities,
            'refundCount' => $refundCount,
            'voidCount' => $voidCount,
            'hourlySales' => $hourlySales,
            'cogsThisMonth' => $cogsThisMonth,
            'grossProfit' => $grossProfit,
            'profitMargin' => $profitMargin,
            'topProfitProducts' => $topProfitProducts,
            'totalCustomers' => $totalCustomers,
            'newCustomersMonth' => $newCustomersMonth,
            'returningPct' => $returningPct,
            'avgPurchasePerCustomer' => $avgPurchasePerCustomer,
            'topCustomers' => $topCustomers,
        ];
    }

    /**
     * Fill missing days in the last 7-day window so area charts always have a visible series.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function padDailySalesTrend($rows, Carbon $today)
    {
        $byDate = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->date)->toDateString();
            $byDate[$date] = (object) [
                'date' => $date,
                'revenue' => (float) $row->revenue,
                'transactions' => (int) $row->transactions,
            ];
        }

        $padded = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i)->toDateString();
            $padded->push($byDate[$date] ?? (object) [
                'date' => $date,
                'revenue' => 0.0,
                'transactions' => 0,
            ]);
        }

        return $padded;
    }

    /**
     * Fill missing months in the last 6-month window so area charts always have a visible series.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function padMonthlySalesTrend($rows, Carbon $today)
    {
        $byMonth = [];
        foreach ($rows as $row) {
            $byMonth[$row->month] = (object) [
                'month' => $row->month,
                'revenue' => (float) $row->revenue,
                'transactions' => (int) $row->transactions,
            ];
        }

        $padded = collect();
        for ($i = 5; $i >= 0; $i--) {
            $month = $today->copy()->subMonths($i)->format('Y-m');
            $padded->push($byMonth[$month] ?? (object) [
                'month' => $month,
                'revenue' => 0.0,
                'transactions' => 0,
            ]);
        }

        return $padded;
    }

    private function emptyDashboardData(): array
    {
        return [
            'revenueToday' => 0, 'revenueYesterday' => 0,
            'revenueThisMonth' => 0, 'revenueLastMonth' => 0,
            'txToday' => 0, 'txYesterday' => 0, 'txThisMonth' => 0, 'aov' => 0,
            'dailySalesTrend' => collect(), 'monthlySalesTrend' => collect(),
            'paymentMethods' => collect(), 'paymentTotal' => 0,
            'topProducts' => collect(), 'topCategories' => collect(),
            'salesPerOutlet' => collect(), 'bestOutlet' => null, 'bestProduct' => null,
            'totalSku' => 0, 'totalStock' => 0, 'inventoryValue' => 0,
            'lowStockProducts' => collect(), 'lowStockCount' => 0, 'outOfStockCount' => 0,
            'stockByCategory' => collect(), 'deadStockProducts' => [],
            'deadStockAlertCount' => 0,
            'totalPO' => 0, 'pendingPO' => 0, 'receivedToday' => 0,
            'poByStatus' => collect(), 'topSuppliers' => collect(),
            'inboundToday' => 0, 'outboundToday' => 0,
            'inboundTodayCount' => 0, 'outboundTodayCount' => 0,
            'wmsWeeklyTrend' => collect(), 'recentActivities' => collect(),
            'refundCount' => 0, 'voidCount' => 0, 'hourlySales' => collect(),
            'cogsThisMonth' => 0, 'grossProfit' => 0, 'profitMargin' => 0,
            'topProfitProducts' => collect(),
            'totalCustomers' => 0, 'newCustomersMonth' => 0,
            'returningPct' => 0, 'avgPurchasePerCustomer' => 0,
            'topCustomers' => collect(),
        ];
    }

    public function getTaskListData(Request $request)
    {
        return response()->json(['message' => 'Task list endpoint']);
    }

    public function getClientListData(Request $request)
    {
        return response()->json(['message' => 'Client list endpoint']);
    }

    public function getSubscriptionListData(Request $request)
    {
        return response()->json(['message' => 'Subscription list endpoint']);
    }
}
