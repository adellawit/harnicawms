<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
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
        $isFilter = $status !== '' || $paymentStatus !== '' || $orderType !== '' || $branchId !== $defaultBranchId;

        return view('admin.transaction.index', compact('status', 'paymentStatus', 'orderType', 'isFilter', 'branchId', 'defaultBranchId'));
    }

    public function indexData(Request $request)
    {
        $defaultBranchId = $this->getBranchId();
        $branchId = $request->get('branch_id', $defaultBranchId);

        $query = SalesOrder::with(['methodPayment:id,name', 'priceList:id,name,code'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

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
                ];
                $color = $colors[$row->status] ?? 'info';
                return '<span class="badge bg-label-' . $color . '">' . ucfirst($row->status) . '</span>';
            })
            ->addColumn('payment_badge', function ($row) {
                $colors = ['paid' => 'success', 'unpaid' => 'danger', 'partial' => 'warning'];
                $color = $colors[$row->payment_status] ?? 'secondary';
                return '<span class="badge bg-label-' . $color . '">' . ucfirst($row->payment_status) . '</span>';
            })
            ->addColumn('total_fmt', fn ($row) => format_number((float) $row->total, 2, true))
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
            ->rawColumns(['status_badge', 'payment_badge'])
            ->toJson();
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
        $order = SalesOrder::with([
            'items.product:id,name,sku',
            'items.variant:id,sku',
            'items.variant.variantAttributes.attributeValue',
            'items.variant.variantAttributes.attributeDefinition:id,name',
            'items.unit:id,name,symbol',
            'payments.methodPayment:id,name',
            'methodPayment:id,name',
            'customer:id,name,code',
            'branch:id,name,brand_name,address,phone,email,parent_id',
            'branch.parent:id,name,brand_name',
        ])->withTrashed()->findOrFail($id);

        return view('admin.transaction.print-invoice', [
            'order' => $order,
            'backUrl' => route('transaction.detail', $order->id),
            'autoPrint' => request()->boolean('print'),
        ]);
    }
}
