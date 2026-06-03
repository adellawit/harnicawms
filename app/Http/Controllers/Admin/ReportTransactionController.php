<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\MethodPayment;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportTransactionController extends Controller
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
        $status = $request->get('status');
        $paymentMethodId = $request->get('payment_method_id');
        $customerId = $request->get('customer_id');
        $isSalesByCustomer = $request->routeIs('reporting.sales-by-customer.index');
        $perPage = (int) $request->get('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        if (! $dateFrom || ! $dateTo) {
            $today = now();
            $dateFrom = $today->copy()->startOfMonth()->toDateString();
            $dateTo = $today->toDateString();
        }

        $query = SalesOrder::with(['branch:id,name', 'methodPayment:id,name'])
            ->whereDate('sales_date', '>=', $dateFrom)
            ->whereDate('sales_date', '<=', $dateTo)
            ->whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($paymentMethodId, fn ($q) => $q->where('method_payment_id', $paymentMethodId))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId));

        $totalTransactions = (int) $query->clone()->count();
        $totalAmount = (float) $query->clone()->sum('total');
        $totalPaid = (int) $query->clone()->where('payment_status', 'paid')->count();
        $totalUnpaid = (int) $query->clone()->where('payment_status', '!=', 'paid')->count();
        $avgTransaction = $totalTransactions > 0 ? $totalAmount / $totalTransactions : 0;

        $transactions = $query->orderByDesc('sales_date')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $branches = BusinessUnit::where('type_code', 'BRANCH')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $paymentMethods = MethodPayment::where('is_active', true)
            ->whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $customers = collect();
        $topCustomersByValue = collect();
        $topCustomersChartData = [];

        if ($isSalesByCustomer) {
            $customers = SalesOrder::query()
                ->whereNotNull('customer_id')
                ->whereNull('deleted_at')
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->select('customer_id', 'customer_name')
                ->groupBy('customer_id', 'customer_name')
                ->orderBy('customer_name')
                ->get();

            $topCustomersByValue = $this->topCustomersByValueQuery(
                $dateFrom,
                $dateTo,
                $branchId,
                $status,
                $customerId,
            )->get();

            $topCustomersChartData = $topCustomersByValue->map(fn ($row) => [
                'name' => $row->customer_name,
                'value' => (float) $row->total_value,
                'orders' => (int) $row->order_count,
            ])->values()->all();
        }

        $statuses = ['draft', 'completed', 'cancelled', 'void', 'refund'];

        return view('admin.reporting.transaction.index', [
            'transactions' => $transactions,
            'branches' => $branches,
            'paymentMethods' => $paymentMethods,
            'customers' => $customers,
            'statuses' => $statuses,
            'branchId' => $branchId,
            'defaultBranchId' => $defaultBranchId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'status' => $status,
            'paymentMethodId' => $paymentMethodId,
            'customerId' => $customerId,
            'isSalesByCustomer' => $isSalesByCustomer,
            'perPage' => $perPage,
            'totalTransactions' => $totalTransactions,
            'totalAmount' => $totalAmount,
            'totalPaid' => $totalPaid,
            'totalUnpaid' => $totalUnpaid,
            'avgTransaction' => $avgTransaction,
            'topCustomersByValue' => $topCustomersByValue,
            'topCustomersChartData' => $topCustomersChartData ?? [],
        ]);
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function topCustomersByValueQuery(
        string $dateFrom,
        string $dateTo,
        ?string $branchId,
        ?string $status,
        ?string $customerId,
    ) {
        return DB::table('transaction.sales_orders as so')
            ->whereDate('so.sales_date', '>=', $dateFrom)
            ->whereDate('so.sales_date', '<=', $dateTo)
            ->whereNull('so.deleted_at')
            ->when($branchId, fn ($q) => $q->where('so.branch_id', $branchId))
            ->when($status, fn ($q) => $q->where('so.status', $status))
            ->when($customerId, fn ($q) => $q->where('so.customer_id', $customerId))
            ->select(
                DB::raw("COALESCE(NULLIF(TRIM(so.customer_name), ''), 'Walk-in Customer') as customer_name"),
                DB::raw('COUNT(so.id) as order_count'),
                DB::raw('COALESCE(SUM(so.total), 0) as total_value'),
            )
            ->groupBy(
                'so.customer_id',
                DB::raw("COALESCE(NULLIF(TRIM(so.customer_name), ''), 'Walk-in Customer')"),
            )
            ->orderByDesc('total_value')
            ->limit(10);
    }
}
