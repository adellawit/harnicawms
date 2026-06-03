<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportAdvancedController extends Controller
{
    protected function getBranchId(): ?string
    {
        return auth('web')->user()?->current_business_unit_id;
    }

    protected function baseFilters(Request $request): array
    {
        $defaultBranchId = $this->getBranchId();
        $branchId = $request->get('branch_id', $defaultBranchId);
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (! $dateFrom || ! $dateTo) {
            $today = now();
            $dateFrom = $today->copy()->startOfMonth()->toDateString();
            $dateTo = $today->toDateString();
        }

        $branches = BusinessUnit::where('is_active', true)
            ->where('type_code', 'BRANCH')
            ->orderBy('name')
            ->get(['id', 'name']);

        return compact('defaultBranchId', 'branchId', 'dateFrom', 'dateTo', 'branches');
    }

    protected function renderReport(Request $request, string $title, string $subtitle, string $routeName, array $cards, array $columns, array $rows)
    {
        $filters = $this->baseFilters($request);

        return view('admin.reporting.advanced.index', array_merge($filters, [
            'title' => $title,
            'subtitle' => $subtitle,
            'routeName' => $routeName,
            'cards' => $cards,
            'columns' => $columns,
            'rows' => $rows,
        ]));
    }

    public function kpiDashboard(Request $request)
    {
        $filters = $this->baseFilters($request);
        $base = DB::table('transaction.sales_orders as so')
            ->whereDate('so.sales_date', '>=', $filters['dateFrom'])
            ->whereDate('so.sales_date', '<=', $filters['dateTo'])
            ->whereNull('so.deleted_at')
            ->when($filters['branchId'], fn ($q) => $q->where('so.branch_id', $filters['branchId']));

        $totalRevenue = (float) (clone $base)->sum('so.total');
        $totalTransactions = (int) (clone $base)->count();
        $avgOrderValue = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;
        $paidTransactions = (int) (clone $base)->where('so.payment_status', 'paid')->count();

        $cards = [
            ['label' => 'Total Revenue', 'value' => $totalRevenue, 'type' => 'currency'],
            ['label' => 'Total Transactions', 'value' => $totalTransactions, 'type' => 'number'],
            ['label' => 'Average Order Value', 'value' => $avgOrderValue, 'type' => 'currency'],
            ['label' => 'Paid Transactions', 'value' => $paidTransactions, 'type' => 'number'],
        ];

        $rows = (clone $base)
            ->selectRaw("DATE(so.sales_date) as period, COUNT(*) as transactions, COALESCE(SUM(so.total), 0) as revenue")
            ->groupBy(DB::raw("DATE(so.sales_date)"))
            ->orderBy('period')
            ->get()
            ->map(fn ($r) => [
                'period' => $r->period,
                'transactions' => (int) $r->transactions,
                'revenue' => (float) $r->revenue,
            ])
            ->toArray();

        return $this->renderReport(
            $request,
            'KPI Dashboard',
            'KPI utama penjualan berdasarkan branch dan periode.',
            'reporting.kpi-dashboard.index',
            $cards,
            ['period' => 'Date', 'transactions' => 'Transactions', 'revenue' => 'Revenue'],
            $rows
        );
    }

    public function profitabilityOverview(Request $request)
    {
        $filters = $this->baseFilters($request);
        $base = DB::table('transaction.sales_order_items as soi')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->whereDate('so.sales_date', '>=', $filters['dateFrom'])
            ->whereDate('so.sales_date', '<=', $filters['dateTo'])
            ->whereNull('so.deleted_at')
            ->whereNull('soi.deleted_at')
            ->when($filters['branchId'], fn ($q) => $q->where('so.branch_id', $filters['branchId']));

        $sales = (float) (clone $base)->sum('soi.subtotal');
        $estimatedCost = (float) (clone $base)->sum(DB::raw('soi.quantity * soi.unit_price * 0.7'));
        $grossProfit = $sales - $estimatedCost;
        $marginPct = $sales > 0 ? ($grossProfit / $sales) * 100 : 0;

        $cards = [
            ['label' => 'Sales', 'value' => $sales, 'type' => 'currency'],
            ['label' => 'Estimated Cost', 'value' => $estimatedCost, 'type' => 'currency'],
            ['label' => 'Gross Profit', 'value' => $grossProfit, 'type' => 'currency'],
            ['label' => 'Gross Margin %', 'value' => $marginPct, 'type' => 'percent'],
        ];

        $rows = (clone $base)
            ->join('product.products as p', 'p.id', '=', 'soi.product_id')
            ->selectRaw("p.name as product_name, COALESCE(SUM(soi.subtotal),0) as sales, COALESCE(SUM(soi.quantity * soi.unit_price * 0.7),0) as estimated_cost")
            ->groupBy('p.name')
            ->orderByDesc('sales')
            ->limit(50)
            ->get()
            ->map(function ($r) {
                $sales = (float) $r->sales;
                $cost = (float) $r->estimated_cost;
                return [
                    'product_name' => $r->product_name,
                    'sales' => $sales,
                    'estimated_cost' => $cost,
                    'gross_profit' => $sales - $cost,
                ];
            })
            ->toArray();

        return $this->renderReport(
            $request,
            'Profitability Overview',
            'Ringkasan profitabilitas per produk (estimasi cost).',
            'reporting.profitability-overview.index',
            $cards,
            ['product_name' => 'Product', 'sales' => 'Sales', 'estimated_cost' => 'Estimated Cost', 'gross_profit' => 'Gross Profit'],
            $rows
        );
    }

    public function purchaseBySupplier(Request $request)
    {
        $filters = $this->baseFilters($request);
        $base = DB::table('product.purchase_orders as po')
            ->leftJoin('master_data.suppliers as s', 's.id', '=', 'po.supplier_id')
            ->whereDate('po.purchase_date', '>=', $filters['dateFrom'])
            ->whereDate('po.purchase_date', '<=', $filters['dateTo'])
            ->whereNull('po.deleted_at')
            ->whereNotIn('po.status', ['cancelled', 'void'])
            ->when($filters['branchId'], fn ($q) => $q->where('po.branch_id', $filters['branchId']));

        $totalPurchase = (float) (clone $base)->sum('po.total');
        $totalPO = (int) (clone $base)->count();

        $rows = (clone $base)
            ->selectRaw("COALESCE(s.name, po.supplier_name, '-') as supplier_name, COUNT(po.id) as po_count, COALESCE(SUM(po.total), 0) as total_purchase, COALESCE(AVG(po.total), 0) as avg_po")
            ->groupBy(DB::raw("COALESCE(s.name, po.supplier_name, '-')"))
            ->orderByDesc('total_purchase')
            ->limit(100)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        $cards = [
            ['label' => 'Total Purchase', 'value' => $totalPurchase, 'type' => 'currency'],
            ['label' => 'Total PO', 'value' => $totalPO, 'type' => 'number'],
            ['label' => 'Supplier Count', 'value' => count($rows), 'type' => 'number'],
        ];

        return $this->renderReport(
            $request,
            'Purchase by Supplier',
            'Analisa pembelian berdasarkan supplier.',
            'reporting.purchase-by-supplier.index',
            $cards,
            ['supplier_name' => 'Supplier', 'po_count' => 'PO Count', 'total_purchase' => 'Total Purchase', 'avg_po' => 'Avg PO'],
            $rows
        );
    }

    public function purchasePriceHistory(Request $request)
    {
        $filters = $this->baseFilters($request);
        $rows = DB::table('product.purchase_order_items as poi')
            ->join('product.purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->leftJoin('product.products as p', 'p.id', '=', 'poi.product_id')
            ->whereDate('po.purchase_date', '>=', $filters['dateFrom'])
            ->whereDate('po.purchase_date', '<=', $filters['dateTo'])
            ->whereNull('po.deleted_at')
            ->whereNull('poi.deleted_at')
            ->when($filters['branchId'], fn ($q) => $q->where('po.branch_id', $filters['branchId']))
            ->selectRaw("
                COALESCE(p.name, '-') as product_name,
                COALESCE(MIN(poi.unit_price), 0) as min_price,
                COALESCE(MAX(poi.unit_price), 0) as max_price,
                COALESCE(AVG(poi.unit_price), 0) as avg_price,
                MAX(po.purchase_date) as last_purchase_date
            ")
            ->groupBy(DB::raw("COALESCE(p.name, '-')"))
            ->orderByDesc('last_purchase_date')
            ->limit(100)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        $cards = [
            ['label' => 'Product Count', 'value' => count($rows), 'type' => 'number'],
            ['label' => 'Latest Date', 'value' => $rows[0]['last_purchase_date'] ?? '-', 'type' => 'text'],
        ];

        return $this->renderReport(
            $request,
            'Purchase Price History',
            'Riwayat harga beli per produk dalam periode terpilih.',
            'reporting.purchase-price-history.index',
            $cards,
            ['product_name' => 'Product', 'min_price' => 'Min Price', 'max_price' => 'Max Price', 'avg_price' => 'Avg Price', 'last_purchase_date' => 'Last Purchase Date'],
            $rows
        );
    }

    public function supplierPerformance(Request $request)
    {
        $filters = $this->baseFilters($request);
        $rows = DB::table('product.purchase_orders as po')
            ->leftJoin('master_data.suppliers as s', 's.id', '=', 'po.supplier_id')
            ->whereDate('po.purchase_date', '>=', $filters['dateFrom'])
            ->whereDate('po.purchase_date', '<=', $filters['dateTo'])
            ->whereNull('po.deleted_at')
            ->when($filters['branchId'], fn ($q) => $q->where('po.branch_id', $filters['branchId']))
            ->selectRaw("
                COALESCE(s.name, po.supplier_name, '-') as supplier_name,
                COUNT(po.id) as po_count,
                SUM(CASE WHEN po.status = 'received' THEN 1 ELSE 0 END) as received_count,
                COALESCE(SUM(po.total), 0) as total_purchase
            ")
            ->groupBy(DB::raw("COALESCE(s.name, po.supplier_name, '-')"))
            ->orderByDesc('total_purchase')
            ->limit(100)
            ->get()
            ->map(function ($r) {
                $poCount = (int) $r->po_count;
                $received = (int) $r->received_count;
                return [
                    'supplier_name' => $r->supplier_name,
                    'po_count' => $poCount,
                    'received_count' => $received,
                    'receive_rate' => $poCount > 0 ? round(($received / $poCount) * 100, 2) : 0,
                    'total_purchase' => (float) $r->total_purchase,
                ];
            })
            ->toArray();

        $cards = [
            ['label' => 'Supplier Count', 'value' => count($rows), 'type' => 'number'],
            ['label' => 'Total PO', 'value' => array_sum(array_column($rows, 'po_count')), 'type' => 'number'],
        ];

        return $this->renderReport(
            $request,
            'Supplier Performance',
            'Performa supplier berdasarkan jumlah PO dan tingkat penerimaan.',
            'reporting.supplier-performance.index',
            $cards,
            ['supplier_name' => 'Supplier', 'po_count' => 'PO Count', 'received_count' => 'Received', 'receive_rate' => 'Receive Rate %', 'total_purchase' => 'Total Purchase'],
            $rows
        );
    }

    public function poReceivingGrn(Request $request)
    {
        $filters = $this->baseFilters($request);
        $rows = DB::table('product.purchase_orders as po')
            ->leftJoin('master_data.suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('product.purchase_order_receives as por', 'por.purchase_order_id', '=', 'po.id')
            ->leftJoin('product.purchase_order_receive_items as pori', 'pori.receive_id', '=', 'por.id')
            ->leftJoin('product.purchase_order_items as poi', 'poi.id', '=', 'pori.purchase_order_item_id')
            ->whereDate('po.purchase_date', '>=', $filters['dateFrom'])
            ->whereDate('po.purchase_date', '<=', $filters['dateTo'])
            ->whereNull('po.deleted_at')
            ->when($filters['branchId'], fn ($q) => $q->where('po.branch_id', $filters['branchId']))
            ->groupBy('po.id', 'po.purchase_number', 'po.purchase_date', 's.name', 'po.supplier_name', 'po.status', 'po.total')
            ->selectRaw("
                po.purchase_number,
                po.purchase_date,
                COALESCE(s.name, po.supplier_name, '-') as supplier_name,
                po.status,
                po.total,
                COUNT(DISTINCT por.id) as grn_count,
                COALESCE(SUM(COALESCE(pori.quantity_received, 0) * COALESCE(poi.unit_price, 0)), 0) as total_received_value
            ")
            ->orderByDesc('po.purchase_date')
            ->limit(200)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        return $this->renderReport(
            $request,
            'PO Receiving / GRN',
            'Monitoring penerimaan barang dari purchase order.',
            'reporting.po-receiving-grn.index',
            [
                ['label' => 'PO Rows', 'value' => count($rows), 'type' => 'number'],
                ['label' => 'Total Received Value', 'value' => array_sum(array_column($rows, 'total_received_value')), 'type' => 'currency'],
            ],
            [
                'purchase_number' => 'PO Number',
                'purchase_date' => 'PO Date',
                'supplier_name' => 'Supplier',
                'status' => 'PO Status',
                'grn_count' => 'GRN Count',
                'total' => 'PO Total',
                'total_received_value' => 'Received Value',
            ],
            $rows
        );
    }

    public function poAgingOpenPo(Request $request)
    {
        $filters = $this->baseFilters($request);
        $rows = DB::table('product.purchase_orders as po')
            ->leftJoin('master_data.suppliers as s', 's.id', '=', 'po.supplier_id')
            ->whereDate('po.purchase_date', '>=', $filters['dateFrom'])
            ->whereDate('po.purchase_date', '<=', $filters['dateTo'])
            ->whereNull('po.deleted_at')
            ->whereNotIn('po.status', ['received', 'cancelled', 'void'])
            ->when($filters['branchId'], fn ($q) => $q->where('po.branch_id', $filters['branchId']))
            ->selectRaw("
                po.purchase_number,
                po.purchase_date,
                po.expected_delivery_date,
                COALESCE(s.name, po.supplier_name, '-') as supplier_name,
                po.status,
                po.total
            ")
            ->orderBy('po.purchase_date')
            ->limit(200)
            ->get()
            ->map(function ($r) {
                $poDate = \Carbon\Carbon::parse($r->purchase_date);
                $agingDays = now()->diffInDays($poDate);
                $expected = $r->expected_delivery_date ? \Carbon\Carbon::parse($r->expected_delivery_date) : null;
                $overdueDays = $expected && now()->greaterThan($expected) ? now()->diffInDays($expected) : 0;
                return [
                    'purchase_number' => $r->purchase_number,
                    'purchase_date' => $r->purchase_date,
                    'expected_delivery_date' => $r->expected_delivery_date,
                    'supplier_name' => $r->supplier_name,
                    'status' => $r->status,
                    'total' => (float) $r->total,
                    'aging_days' => $agingDays,
                    'overdue_days' => $overdueDays,
                ];
            })
            ->toArray();

        return $this->renderReport(
            $request,
            'PO Aging / Open PO',
            'Analisa umur PO yang masih open dan keterlambatan delivery.',
            'reporting.po-aging-open-po.index',
            [
                ['label' => 'Open PO Count', 'value' => count($rows), 'type' => 'number'],
                ['label' => 'Open PO Value', 'value' => array_sum(array_column($rows, 'total')), 'type' => 'currency'],
            ],
            [
                'purchase_number' => 'PO Number',
                'purchase_date' => 'PO Date',
                'expected_delivery_date' => 'Expected Delivery',
                'supplier_name' => 'Supplier',
                'status' => 'PO Status',
                'aging_days' => 'Aging Days',
                'overdue_days' => 'Overdue Days',
                'total' => 'PO Total',
            ],
            $rows
        );
    }

    public function stockValuation(Request $request)
    {
        $filters = $this->baseFilters($request);
        $rows = DB::table('product.product_variant_stock as pvs')
            ->join('product.products as p', 'p.id', '=', 'pvs.product_id')
            ->whereNull('pvs.deleted_at')
            ->when($filters['branchId'], fn ($q) => $q->where('pvs.branch_id', $filters['branchId']))
            ->selectRaw("p.name as product_name, COALESCE(SUM(pvs.quantity), 0) as qty, COALESCE(AVG(pvs.quantity), 0) as avg_qty")
            ->groupBy('p.name')
            ->orderByDesc('qty')
            ->limit(100)
            ->get()
            ->map(function ($r) {
                $qty = (float) $r->qty;
                $estUnitValue = 1; // Placeholder conservative estimation
                return [
                    'product_name' => $r->product_name,
                    'qty' => $qty,
                    'estimated_unit_value' => $estUnitValue,
                    'estimated_stock_value' => $qty * $estUnitValue,
                ];
            })
            ->toArray();

        $cards = [
            ['label' => 'SKU Count', 'value' => count($rows), 'type' => 'number'],
            ['label' => 'Estimated Value', 'value' => array_sum(array_column($rows, 'estimated_stock_value')), 'type' => 'currency'],
        ];

        return $this->renderReport(
            $request,
            'Stock Valuation',
            'Valuasi stok berbasis kuantitas saat ini.',
            'reporting.stock-valuation.index',
            $cards,
            ['product_name' => 'Product', 'qty' => 'Qty', 'estimated_unit_value' => 'Estimated Unit Value', 'estimated_stock_value' => 'Estimated Stock Value'],
            $rows
        );
    }

    public function inventoryAging(Request $request)
    {
        $filters = $this->baseFilters($request);
        $rows = DB::table('product.product_variant_stock as pvs')
            ->join('product.products as p', 'p.id', '=', 'pvs.product_id')
            ->leftJoin('product.product_stock_movements as psm', function ($join) use ($filters) {
                $join->on('psm.product_variant_stock_id', '=', 'pvs.id')
                    ->whereDate('psm.created_at', '<=', $filters['dateTo'])
                    ->whereNull('psm.deleted_at');
            })
            ->whereNull('pvs.deleted_at')
            ->when($filters['branchId'], fn ($q) => $q->where('pvs.branch_id', $filters['branchId']))
            ->groupBy('p.name')
            ->selectRaw("p.name as product_name, COALESCE(SUM(pvs.quantity), 0) as qty, MAX(psm.created_at) as last_movement_at")
            ->orderBy('last_movement_at')
            ->limit(100)
            ->get()
            ->map(function ($r) {
                $days = $r->last_movement_at ? now()->diffInDays(\Carbon\Carbon::parse($r->last_movement_at)) : null;
                return [
                    'product_name' => $r->product_name,
                    'qty' => (float) $r->qty,
                    'last_movement_at' => $r->last_movement_at,
                    'days_since_last_movement' => $days,
                ];
            })
            ->toArray();

        return $this->renderReport(
            $request,
            'Inventory Aging',
            'Umur persediaan berdasarkan tanggal mutasi terakhir.',
            'reporting.inventory-aging.index',
            [['label' => 'SKU Count', 'value' => count($rows), 'type' => 'number']],
            ['product_name' => 'Product', 'qty' => 'Qty', 'last_movement_at' => 'Last Movement', 'days_since_last_movement' => 'Aging (days)'],
            $rows
        );
    }

    public function negativeStockAnalysis(Request $request)
    {
        $filters = $this->baseFilters($request);
        $rows = DB::table('product.product_variant_stock as pvs')
            ->join('product.products as p', 'p.id', '=', 'pvs.product_id')
            ->leftJoin('product.product_variants as pv', 'pv.id', '=', 'pvs.product_variant_id')
            ->whereNull('pvs.deleted_at')
            ->where('pvs.quantity', '<', 0)
            ->when($filters['branchId'], fn ($q) => $q->where('pvs.branch_id', $filters['branchId']))
            ->selectRaw("p.name as product_name, COALESCE(pv.sku, p.sku, '-') as sku, pvs.quantity as qty")
            ->orderBy('qty')
            ->limit(200)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        return $this->renderReport(
            $request,
            'Negative Stock Analysis',
            'Deteksi item dengan stok minus.',
            'reporting.negative-stock-analysis.index',
            [
                ['label' => 'Negative SKU Count', 'value' => count($rows), 'type' => 'number'],
                ['label' => 'Total Negative Qty', 'value' => array_sum(array_column($rows, 'qty')), 'type' => 'number'],
            ],
            ['product_name' => 'Product', 'sku' => 'SKU', 'qty' => 'Negative Qty'],
            $rows
        );
    }

    public function salesByCashier(Request $request)
    {
        $filters = $this->baseFilters($request);
        $rows = DB::table('transaction.sales_orders as so')
            ->leftJoin('auth.users as u', 'u.id', '=', 'so.created_by')
            ->whereDate('so.sales_date', '>=', $filters['dateFrom'])
            ->whereDate('so.sales_date', '<=', $filters['dateTo'])
            ->whereNull('so.deleted_at')
            ->when($filters['branchId'], fn ($q) => $q->where('so.branch_id', $filters['branchId']))
            ->selectRaw("COALESCE(u.first_name || ' ' || u.last_name, u.username, '-') as cashier, COUNT(so.id) as transactions, COALESCE(SUM(so.total), 0) as revenue")
            ->groupBy(DB::raw("COALESCE(u.first_name || ' ' || u.last_name, u.username, '-')"))
            ->orderByDesc('revenue')
            ->limit(100)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        return $this->renderReport(
            $request,
            'Sales by Cashier',
            'Performa penjualan per kasir.',
            'reporting.sales-by-cashier.index',
            [
                ['label' => 'Cashier Count', 'value' => count($rows), 'type' => 'number'],
                ['label' => 'Total Revenue', 'value' => array_sum(array_column($rows, 'revenue')), 'type' => 'currency'],
            ],
            ['cashier' => 'Cashier', 'transactions' => 'Transactions', 'revenue' => 'Revenue'],
            $rows
        );
    }
}

