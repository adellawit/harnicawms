<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MethodPayment;
use App\Models\PurchaseKontrabon;
use App\Models\Supplier;
use App\Services\KontrabonService;
use App\Support\KontrabonStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\DataTables;

class PurchaseInvoiceController extends Controller
{
    public function indexView(Request $request)
    {
        $user = auth('web')->user();
        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';
        $filterBranchId = $request->get('branch_id', $user->current_business_unit_id);
        $paymentMethods = $this->paymentMethodOptions();

        return view('admin.product.purchase-invoice.index', compact('status', 'isFilter', 'filterBranchId', 'paymentMethods'));
    }

    public function indexData(Request $request)
    {
        $user = auth('web')->user();
        $branchId = $request->get('branch_id');

        $data = PurchaseKontrabon::query()
            ->withCount('items');

        if ($branchId) {
            $data->where('branch_id', $branchId);
        } else {
            $accessibleIds = $user->getAccessibleBusinessUnitIdsForQuery();
            if (! empty($accessibleIds)) {
                $data->whereIn('branch_id', $accessibleIds);
            }
        }

        if ($request->status === 'deleted') {
            $data->onlyTrashed();
        } elseif ($request->status !== 'active') {
            $data->withTrashed();
        }

        $data->orderByDesc('kontrabon_date')->orderByDesc('created_at');

        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->addColumn('status_badge', function ($row) {
                $tone = KontrabonStatus::badgeClass($row->status);

                return '<span class="badge bg-label-'.$tone.'">'.e($row->status_label).'</span>';
            })
            ->addColumn('total_fmt', fn ($row) => format_number((float) $row->total, 2, true))
            ->addColumn('paid_fmt', fn ($row) => format_number((float) ($row->paid_amount ?? 0), 2, true))
            ->addColumn('balance_fmt', fn ($row) => format_number(KontrabonStatus::paymentBalance($row), 2, true))
            ->addColumn('balance_amount', fn ($row) => KontrabonStatus::paymentBalance($row))
            ->addColumn('po_count', fn ($row) => (int) ($row->items_count ?? 0))
            ->addColumn('can_edit', fn ($row) => KontrabonStatus::canEdit($row))
            ->addColumn('can_pay', fn ($row) => KontrabonStatus::canPay($row))
            ->addColumn('can_cancel', fn ($row) => KontrabonStatus::canCancel($row))
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('kontrabon_number', 'ilike', "%{$search}%")
                            ->orWhere('supplier_name', 'ilike', "%{$search}%")
                            ->orWhere('payment_reference', 'ilike', "%{$search}%");
                    });
                }
            })
            ->rawColumns(['status_badge', 'total_fmt'])
            ->toJson();
    }

    public function insertView()
    {
        $suppliers = Supplier::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->when(auth('web')->user()->current_business_unit_id, function ($q, $branchId) {
                $q->where('branch_id', $branchId);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('admin.product.purchase-invoice.insert', compact('suppliers'));
    }

    public function insertData(Request $request)
    {
        $validated = $this->validateKontrabonRequest($request);
        $user = auth('web')->user();
        $branchId = $user->getBranchIdForTransaction();
        $companyId = $user->getCompanyIdForProduct();

        try {
            $items = $this->applyItemAttachments($request, $validated['items']);

            $kontrabon = KontrabonService::createKontrabon([
                'kontrabon_date' => $this->parseDate($validated['kontrabon_date']),
                'supplier_id' => $validated['supplier_id'],
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'notes' => $validated['notes'] ?? null,
                'submit' => $request->boolean('submit'),
            ], $items, $user->id);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('product.purchase-invoice.detail.view', $kontrabon->id)
            ->with('success', 'Kontrabon '.$kontrabon->kontrabon_number.' berhasil dibuat.');
    }

    public function editView(string $id)
    {
        $kontrabon = PurchaseKontrabon::with(['items.purchaseOrder'])->findOrFail($id);
        $this->authorizeBranch($kontrabon);

        if (! KontrabonStatus::canEdit($kontrabon)) {
            return redirect()
                ->route('product.purchase-invoice.detail.view', $kontrabon->id)
                ->withErrors(['error' => 'Kontrabon hanya dapat diedit saat status Draft.']);
        }

        $suppliers = Supplier::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->when($kontrabon->branch_id, fn ($q) => $q->where('branch_id', $kontrabon->branch_id))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('admin.product.purchase-invoice.edit', compact('kontrabon', 'suppliers'));
    }

    public function editData(Request $request)
    {
        $validated = $this->validateKontrabonRequest($request);
        $kontrabon = PurchaseKontrabon::with('items')->findOrFail($validated['id']);
        $this->authorizeBranch($kontrabon);
        $user = auth('web')->user();

        try {
            $items = $this->applyItemAttachments($request, $validated['items'], $kontrabon);

            $kontrabon = KontrabonService::updateKontrabon($kontrabon, [
                'kontrabon_date' => $this->parseDate($validated['kontrabon_date']),
                'supplier_id' => $validated['supplier_id'],
                'branch_id' => $kontrabon->branch_id,
                'notes' => $validated['notes'] ?? null,
                'submit' => $request->boolean('submit'),
            ], $items, $user->id);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('product.purchase-invoice.detail.view', $kontrabon->id)
            ->with('success', 'Kontrabon '.$kontrabon->kontrabon_number.' berhasil diperbarui.');
    }

    public function detailView(string $id)
    {
        $kontrabon = PurchaseKontrabon::with(['items.purchaseOrder', 'supplier', 'branch', 'payments'])->findOrFail($id);
        $this->authorizeBranch($kontrabon);
        $paymentMethods = $this->paymentMethodOptions();

        return view('admin.product.purchase-invoice.detail', compact('kontrabon', 'paymentMethods'));
    }

    public function eligiblePurchaseOrders(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|uuid|exists:master_data.suppliers,id',
            'exclude_kontrabon_id' => 'nullable|uuid',
            'receive_scope' => 'nullable|in:received,unreceived',
        ]);

        $user = auth('web')->user();
        $branchId = $request->get('branch_id', $user->current_business_unit_id);
        $branchIds = $branchId ? [$branchId] : $user->getAccessibleBusinessUnitIdsForQuery();

        $orders = KontrabonService::eligiblePurchaseOrders(
            $request->supplier_id,
            $branchIds,
            $request->exclude_kontrabon_id,
            $request->receive_scope
        );

        return response()->json([
            'data' => $orders->map(function ($po) use ($request) {
                $amounts = KontrabonService::purchaseOrderTotals($po);
                $remaining = KontrabonService::purchaseOrderRemainingInvoiceAmount(
                    $po,
                    $request->exclude_kontrabon_id
                );
                $hasReceive = KontrabonService::purchaseOrderHasReceive($po);
                $items = KontrabonService::purchaseOrderItemsPayload($po);
                $orderedQty = collect($items)->sum('quantity');
                $receivedQty = collect($items)->sum('quantity_received');

                return [
                    'id' => $po->id,
                    'purchase_number' => $po->purchase_number,
                    'purchase_date' => optional($po->purchase_date)->format('d/m/Y'),
                    'expected_delivery_date' => optional($po->expected_delivery_date)->format('d/m/Y'),
                    'status' => $po->status_label,
                    'po_kind_label' => $po->po_kind_label,
                    'has_receive' => $hasReceive,
                    'is_fully_received' => KontrabonService::purchaseOrderIsFullyReceived($po),
                    'ordered_qty' => $orderedQty,
                    'received_qty' => $receivedQty,
                    'subtotal' => $amounts['subtotal'],
                    'tax_amount' => $amounts['tax_amount'],
                    'discount_amount' => $amounts['discount_amount'],
                    'other_cost_amount' => $amounts['other_cost_amount'],
                    'total' => $amounts['total'],
                    'po_total' => $amounts['total'],
                    'remaining_invoice_amount' => $remaining,
                    'total_fmt' => format_number($amounts['total'], 2, true),
                    'remaining_fmt' => format_number($remaining, 2, true),
                    'ordered_qty_fmt' => format_number($orderedQty, 2, true),
                    'received_qty_fmt' => format_number($receivedQty, 2, true),
                    'detail_url' => route('product.purchase-order.detail.view', $po->id),
                    'items' => $items,
                ];
            })->values(),
        ]);
    }

    public function submitData(Request $request)
    {
        $request->validate(['id' => 'required|uuid|exists:product.purchase_kontrabons,id']);
        $kontrabon = PurchaseKontrabon::with('items')->findOrFail($request->id);
        $this->authorizeBranch($kontrabon);

        try {
            KontrabonService::submitKontrabon($kontrabon);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Kontrabon '.$kontrabon->kontrabon_number.' berhasil disubmit.');
    }

    public function paymentData(Request $request)
    {
        $this->normalizeKontrabonDateInputs($request);

        if ($request->has('amount')) {
            $request->merge([
                'amount' => $this->parseNumericInput($request->input('amount')),
            ]);
        }

        $validated = $request->validate([
            'id' => 'required|uuid|exists:product.purchase_kontrabons,id',
            'payment_date' => 'required|date_format:d/m/Y',
            'amount' => 'required|numeric|min:0.01',
            'payment_reference' => 'nullable|string|max:120',
            'payment_method' => 'nullable|string|max:80',
            'payment_notes' => 'nullable|string|max:500',
        ]);

        $kontrabon = PurchaseKontrabon::with('items.purchaseOrder')->findOrFail($validated['id']);
        $this->authorizeBranch($kontrabon);
        $user = auth('web')->user();

        try {
            KontrabonService::recordPayment($kontrabon, [
                'payment_date' => $this->parseDate($validated['payment_date']),
                'amount' => (float) $validated['amount'],
                'payment_reference' => $validated['payment_reference'] ?? null,
                'payment_method' => $validated['payment_method'] ?? null,
                'payment_notes' => $validated['payment_notes'] ?? null,
            ], $user->id);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Pembayaran kontrabon '.$kontrabon->kontrabon_number.' berhasil dicatat.');
    }

    public function cancelData(Request $request)
    {
        $request->validate(['id' => 'required|uuid|exists:product.purchase_kontrabons,id']);
        $kontrabon = PurchaseKontrabon::findOrFail($request->id);
        $this->authorizeBranch($kontrabon);
        $user = auth('web')->user();

        try {
            KontrabonService::cancelKontrabon($kontrabon, $user->id);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Kontrabon '.$kontrabon->kontrabon_number.' dibatalkan.');
    }

    public function deleteData(Request $request)
    {
        $request->validate(['id' => 'required|uuid|exists:product.purchase_kontrabons,id']);
        $kontrabon = PurchaseKontrabon::findOrFail($request->id);
        $this->authorizeBranch($kontrabon);

        if ($kontrabon->status !== KontrabonStatus::DRAFT) {
            return back()->withErrors(['error' => 'Hanya kontrabon Draft yang dapat dihapus.']);
        }

        $kontrabon->update(['deleted_by' => auth('web')->id()]);
        $kontrabon->delete();

        return redirect()
            ->route('product.purchase-invoice.index.view')
            ->with('success', 'Kontrabon '.$kontrabon->kontrabon_number.' berhasil dihapus.');
    }

    public function restoreData(Request $request)
    {
        $request->validate(['id' => 'required|uuid']);
        $kontrabon = PurchaseKontrabon::withTrashed()->findOrFail($request->id);
        $this->authorizeBranch($kontrabon);
        $kontrabon->restore();

        return back()->with('success', 'Kontrabon '.$kontrabon->kontrabon_number.' berhasil direstore.');
    }

    protected function validateKontrabonRequest(Request $request): array
    {
        $this->normalizeKontrabonDateInputs($request);
        $this->normalizeKontrabonAmountInputs($request);

        return $request->validate([
            'id' => 'nullable|uuid|exists:product.purchase_kontrabons,id',
            'kontrabon_date' => 'required|date_format:d/m/Y',
            'supplier_id' => 'required|uuid|exists:master_data.suppliers,id',
            'notes' => 'nullable|string|max:1000',
            'submit' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_id' => 'required|uuid|exists:product.purchase_orders,id',
            'items.*.total' => 'nullable|numeric|min:0.01',
            'items.*.supplier_invoice_number' => 'nullable|string|max:80',
            'items.*.supplier_invoice_date' => 'nullable|date_format:d/m/Y',
            'items.*.notes' => 'nullable|string|max:500',
            'items.*.attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
            'items.*.remove_attachment' => 'nullable|boolean',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function applyItemAttachments(Request $request, array $items, ?PurchaseKontrabon $kontrabon = null): array
    {
        $existingByPo = $kontrabon
            ? $kontrabon->items->keyBy('purchase_order_id')
            : collect();

        $selectedPoIds = [];

        foreach ($items as $index => $item) {
            $poId = $item['purchase_order_id'] ?? null;
            if (! $poId) {
                continue;
            }

            $selectedPoIds[$poId] = true;
            $prev = $existingByPo->get($poId);
            $file = $request->file("items.{$index}.attachment");
            $remove = $request->boolean("items.{$index}.remove_attachment");

            if ($file) {
                if ($prev?->attachment_path) {
                    $this->deleteAttachmentFile($prev->attachment_path);
                }

                $stored = $this->storeAttachment($file);
                $item['attachment_path'] = $stored['path'] ?? null;
                $item['attachment_name'] = $stored['name'] ?? null;
                $item['attachment_mime'] = $stored['mime'] ?? null;
            } elseif ($remove) {
                if ($prev?->attachment_path) {
                    $this->deleteAttachmentFile($prev->attachment_path);
                }
                $item['attachment_path'] = null;
                $item['attachment_name'] = null;
                $item['attachment_mime'] = null;
            } elseif ($prev?->attachment_path) {
                $item['attachment_path'] = $prev->attachment_path;
                $item['attachment_name'] = $prev->attachment_name;
                $item['attachment_mime'] = $prev->attachment_mime;
            } else {
                $item['attachment_path'] = null;
                $item['attachment_name'] = null;
                $item['attachment_mime'] = null;
            }

            $items[$index] = $item;
        }

        foreach ($existingByPo as $poId => $prev) {
            if (! isset($selectedPoIds[$poId]) && $prev->attachment_path) {
                $this->deleteAttachmentFile($prev->attachment_path);
            }
        }

        return $items;
    }

    protected function normalizeKontrabonAmountInputs(Request $request): void
    {
        $items = collect($request->input('items', []))
            ->map(function ($item) {
                if (array_key_exists('total', $item)) {
                    $item['total'] = $this->parseNumericInput($item['total']);
                }

                return $item;
            })
            ->all();

        $request->merge(['items' => $items]);
    }

    protected function parseNumericInput(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace(['.', ' '], ['', ''], (string) $value);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /**
     * @return array{path: string, name: string, mime: string}|null
     */
    protected function storeAttachment(?UploadedFile $file): ?array
    {
        if (! $file) {
            return null;
        }

        $path = $file->store('purchase-invoices/attachments', 'public');

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType() ?: $file->getMimeType(),
        ];
    }

    protected function deleteAttachmentFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function normalizeKontrabonDateInputs(Request $request): void
    {
        if ($request->filled('kontrabon_date')) {
            $request->merge([
                'kontrabon_date' => $this->normalizeToDisplayDate($request->input('kontrabon_date')),
            ]);
        }

        if ($request->filled('payment_date')) {
            $request->merge([
                'payment_date' => $this->normalizeToDisplayDate($request->input('payment_date')),
            ]);
        }

        $items = collect($request->input('items', []))
            ->map(function ($item) {
                if (! empty($item['supplier_invoice_date'])) {
                    $item['supplier_invoice_date'] = $this->normalizeToDisplayDate($item['supplier_invoice_date']);
                } else {
                    $item['supplier_invoice_date'] = null;
                }

                return $item;
            })
            ->all();

        $request->merge(['items' => $items]);
    }

    protected function normalizeToDisplayDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('d/m/Y');
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    protected function parseDate(string $value): string
    {
        $normalized = $this->normalizeToDisplayDate($value);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Invalid date value.');
        }

        return Carbon::createFromFormat('d/m/Y', $normalized)->format('Y-m-d');
    }

    protected function paymentMethodOptions()
    {
        $branchId = auth('web')->user()->current_business_unit_id;

        $methods = MethodPayment::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('uses_payment_gateway', false)->orWhereNull('uses_payment_gateway');
            })
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        if ($methods->isNotEmpty()) {
            return $methods;
        }

        return collect([
            (object) ['code' => null, 'name' => 'Transfer'],
            (object) ['code' => null, 'name' => 'Cash'],
            (object) ['code' => null, 'name' => 'Giro'],
            (object) ['code' => null, 'name' => 'Cheque'],
        ]);
    }

    protected function authorizeBranch(PurchaseKontrabon $kontrabon): void
    {
        $user = auth('web')->user();
        $accessibleIds = $user->getAccessibleBusinessUnitIdsForQuery();

        if (! empty($accessibleIds) && ! in_array($kontrabon->branch_id, $accessibleIds, true)) {
            abort(403, 'Unauthorized.');
        }
    }
}
