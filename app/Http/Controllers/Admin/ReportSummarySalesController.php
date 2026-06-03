<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\SalesOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportSummarySalesController extends Controller
{
    protected function getBranchId(): ?string
    {
        return auth('web')->user()?->current_business_unit_id;
    }

    public function indexView(Request $request)
    {
        $defaultBranchId = $this->getBranchId();
        $branchId = $request->get('branch_id', $defaultBranchId);

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // Default to current month if no date is selected
        if (!$dateFrom || !$dateTo) {
            $today = now();
            $dateFrom = $today->copy()->startOfMonth()->toDateString();
            $dateTo = $today->toDateString();
        }

        $cancelledStatuses = ['cancelled', 'void', 'refund'];

        $baseQuery = SalesOrder::whereDate('sales_date', '>=', $dateFrom)
            ->whereDate('sales_date', '<=', $dateTo)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', $cancelledStatuses);

        $totalRevenue = (float) $baseQuery->clone()->sum('total');
        $totalTransactions = (int) $baseQuery->clone()->count();

        $avgOrderValue = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        // Items & product level stats
        $itemsAgg = DB::table('transaction.sales_order_items as soi')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->whereDate('so.sales_date', '>=', $dateFrom)
            ->whereDate('so.sales_date', '<=', $dateTo)
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->whereNotIn('so.status', $cancelledStatuses)
            ->whereNull('so.deleted_at')
            ->whereNull('soi.deleted_at')
            ->selectRaw('COALESCE(SUM(soi.quantity), 0) as total_items_sold')
            ->first();

        $totalItemsSold = (float) ($itemsAgg->total_items_sold ?? 0);
        $avgItemsPerTransaction = $totalTransactions > 0 ? $totalItemsSold / $totalTransactions : 0;

        $distinctProductsSold = (int) DB::table('transaction.sales_order_items as soi')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->whereDate('so.sales_date', '>=', $dateFrom)
            ->whereDate('so.sales_date', '<=', $dateTo)
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->whereNotIn('so.status', $cancelledStatuses)
            ->whereNull('so.deleted_at')
            ->whereNull('soi.deleted_at')
            ->selectRaw('COUNT(DISTINCT soi.product_id) as cnt')
            ->value('cnt');

        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();
        $daysInRange = max(1, $from->diffInDays($to) + 1);

        $avgTransactionsPerDay = $totalTransactions / $daysInRange;
        $avgRevenuePerDay = $totalRevenue / $daysInRange;
        $avgRevenuePerProductSold = $distinctProductsSold > 0 ? $totalRevenue / $distinctProductsSold : 0;
        $avgQtyPerProductSold = $distinctProductsSold > 0 ? $totalItemsSold / $distinctProductsSold : 0;

        // Daily trend
        $dailyTrend = SalesOrder::select(
                DB::raw("DATE(sales_date) as date"),
                DB::raw("COALESCE(SUM(total), 0) as revenue"),
                DB::raw("COUNT(*) as transactions")
            )
            ->whereDate('sales_date', '>=', $dateFrom)
            ->whereDate('sales_date', '<=', $dateTo)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', $cancelledStatuses)
            ->groupBy(DB::raw('DATE(sales_date)'))
            ->orderBy('date')
            ->get();

        // Payment methods share
        $paymentByMethod = DB::table('transaction.sales_order_payments as sop')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'sop.sales_order_id')
            ->join('master_data.method_payments as mp', 'mp.id', '=', 'sop.method_payment_id')
            ->whereDate('so.sales_date', '>=', $dateFrom)
            ->whereDate('so.sales_date', '<=', $dateTo)
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->whereNotIn('so.status', $cancelledStatuses)
            ->whereNull('sop.deleted_at')
            ->whereNull('so.deleted_at')
            ->select('mp.name', DB::raw('COALESCE(SUM(sop.amount - sop.change_amount), 0) as total'))
            ->groupBy('mp.name')
            ->orderByDesc('total')
            ->get();

        $paymentTotal = $paymentByMethod->sum('total');

        $productSalesAvg = DB::table('transaction.sales_order_items as soi')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->join('product.products as p', 'p.id', '=', 'soi.product_id')
            ->whereDate('so.sales_date', '>=', $dateFrom)
            ->whereDate('so.sales_date', '<=', $dateTo)
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->whereNotIn('so.status', $cancelledStatuses)
            ->whereNull('soi.deleted_at')
            ->whereNull('so.deleted_at')
            ->select(
                'p.name',
                DB::raw('SUM(soi.quantity) as qty'),
                DB::raw('SUM(soi.subtotal) as revenue'),
                DB::raw('CASE WHEN SUM(soi.quantity) > 0 THEN SUM(soi.subtotal) / SUM(soi.quantity) ELSE 0 END as avg_unit_price')
            )
            ->groupBy('p.name')
            ->orderByDesc('revenue')
            ->get();

        $dailyAovTrend = $dailyTrend->map(function ($row) {
            $tx = (int) $row->transactions;
            $rev = (float) $row->revenue;

            return [
                'date' => $row->date,
                'aov' => $tx > 0 ? $rev / $tx : 0.0,
            ];
        });

        // Category performance
        $categorySummary = DB::table('transaction.sales_order_items as soi')
            ->join('transaction.sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->join('product.products as p', 'p.id', '=', 'soi.product_id')
            ->leftJoin('product.product_categories as pc', 'pc.id', '=', 'p.category_id')
            ->whereDate('so.sales_date', '>=', $dateFrom)
            ->whereDate('so.sales_date', '<=', $dateTo)
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->whereNotIn('so.status', $cancelledStatuses)
            ->whereNull('soi.deleted_at')
            ->whereNull('so.deleted_at')
            ->select(
                DB::raw("COALESCE(pc.name, 'Uncategorized') as category"),
                DB::raw('SUM(soi.quantity) as qty'),
                DB::raw('SUM(soi.subtotal) as revenue')
            )
            ->groupBy('pc.name')
            ->orderByDesc('revenue')
            ->get();

        // Support data
        $branches = BusinessUnit::where('is_active', true)
            ->where('type_code', 'BRANCH')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.reporting.sales.summary-sales.index', [
            'branches' => $branches,
            'branchId' => $branchId,
            'defaultBranchId' => $defaultBranchId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'totalRevenue' => $totalRevenue,
            'totalTransactions' => $totalTransactions,
            'avgOrderValue' => $avgOrderValue,
            'totalItemsSold' => $totalItemsSold,
            'avgItemsPerTransaction' => $avgItemsPerTransaction,
            'distinctProductsSold' => $distinctProductsSold,
            'daysInRange' => $daysInRange,
            'avgTransactionsPerDay' => $avgTransactionsPerDay,
            'avgRevenuePerDay' => $avgRevenuePerDay,
            'avgRevenuePerProductSold' => $avgRevenuePerProductSold,
            'avgQtyPerProductSold' => $avgQtyPerProductSold,
            'dailyTrend' => $dailyTrend,
            'paymentByMethod' => $paymentByMethod,
            'paymentTotal' => $paymentTotal,
            'dailyAovTrend' => $dailyAovTrend,
            'productSalesAvg' => $productSalesAvg,
            'categorySummary' => $categorySummary,
        ]);
    }
}

