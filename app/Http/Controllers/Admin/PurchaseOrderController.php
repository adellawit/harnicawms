<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\ParameterDetail;
use App\Models\Product;
use App\Models\ProductPurchaseOrder;
use App\Models\ProductPurchaseOrderItem;
use App\Models\ProductPurchaseOrderReceive;
use App\Models\ProductPurchaseOrderReceiveItem;
use App\Models\ProductStock;
use App\Models\ProductStockMovement;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\StockMutationType;
use App\Models\Supplier;
use App\Services\InventoryCostService;
use App\Services\StockMutationService;
use App\Support\WmsContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class PurchaseOrderController extends Controller
{
    protected function generatePurchaseNumber(): string
    {
        $prefix = 'PO-' . date('Ym') . '-';
        $last = ProductPurchaseOrder::withTrashed()
            ->where('purchase_number', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(purchase_number) DESC, purchase_number DESC')
            ->value('purchase_number');
        $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;
        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function indexView(Request $request)
    {
        $user = auth('web')->user();
        $defaultBranchId = $user->current_business_unit_id;

        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';

        $filterBranchId = $request->get('branch_id', $defaultBranchId);

        return view('admin.product.purchase-order.index', compact('status', 'isFilter', 'filterBranchId'));
    }

    public function indexData(Request $request)
    {
        $user = auth('web')->user();
        $branchId = $request->get('branch_id');

        $data = ProductPurchaseOrder::query();

        if ($branchId) {
            // explicit filter from branch selector dropdown
            $data->where('branch_id', $branchId);
        } else {
            // show all POs accessible to this user (company users see all child branches)
            $accessibleIds = $user->getAccessibleBusinessUnitIdsForQuery();
            if (! empty($accessibleIds)) {
                $data->whereIn('branch_id', $accessibleIds);
            }
        }

        if ($request->status === 'deleted') {
            $data = $data->onlyTrashed();
        } elseif ($request->status !== 'active') {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('purchase_date', 'DESC')
            ->orderBy('created_at', 'DESC');

        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->addColumn('status_badge', fn ($row) => '<span class="badge bg-label-' . ($row->deleted_at ? 'danger' : (in_array($row->status_key ?? $row->status, ['draft']) ? 'secondary' : (in_array($row->status_key ?? $row->status, ['payment', 'received']) ? 'success' : 'warning'))) . '">' . ($row->deleted_at ? 'Deleted' : $row->status_label) . '</span>')
            ->addColumn('total_fmt', fn ($row) => format_number((float) $row->total, 2, true))
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('purchase_number', 'ilike', "%{$search}%")
                            ->orWhere('supplier_name', 'ilike', "%{$search}%");
                    });
                }
            })
            ->rawColumns(['status_badge', 'total_fmt'])
            ->toJson();
    }

    public function suppliersByType(Request $request)
    {
        $branchId = auth('web')->user()->current_business_unit_id;

        return Supplier::where('is_active', true)
            ->whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'contact', 'phone', 'address', 'is_ppn', 'ppn_rate']);
    }

    public function insertView(Request $request)
    {
        $branchId = auth('web')->user()->current_business_unit_id;

        $products = Product::whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with([
                'defaultUnit:id,name,symbol',
                'unitConversions' => fn ($q) => $q->whereNull('deleted_at'),
                'variants' => fn ($q) => $q->where('is_active', true)->whereNull('deleted_at')
                    ->with(['variantAttributes.attributeDefinition:id,name', 'variantAttributes.attributeValue:id,value']),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'item_type_id', 'default_unit_id']);

        $units = ProductUnit::whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name', 'symbol']);

        $poStatuses = ParameterDetail::whereHas('parameter', fn ($q) => $q->where('code', 'PO_STATUS'))
            ->orderByRaw("CASE key WHEN 'draft' THEN 1 WHEN 'process' THEN 2 WHEN 'receiving' THEN 3 WHEN 'payment' THEN 4 ELSE 5 END")
            ->get(['id', 'key', 'value']);

        return view('admin.product.purchase-order.insert', compact('products', 'units', 'poStatuses'));
    }

    public function insertData(Request $request)
    {
        $request->merge([
            'subtotal' => normalize_number_input($request->subtotal),
            'tax_amount' => normalize_number_input($request->tax_amount),
            'discount_amount' => normalize_number_input($request->discount_amount),
            'total' => normalize_number_input($request->total),
        ]);

        $request->merge([
            'items' => collect($request->items ?? [])->map(function ($item) {
                $item['product_id'] = $item['product_id'] ?? null;
                return $item;
            })->all(),
        ]);

        $request->validate([
            'purchase_date' => 'required|date',
            'supplier_id' => 'required|exists:master_data.suppliers,id',
            'status_id' => 'required|exists:public.parameter_details,id',
            'expected_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'subtotal' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:product.products,id',
            'items.*.variant_id' => 'nullable|exists:product.product_variants,id',
            'items.*.unit_id' => 'required|exists:product.product_units,id',
            'items.*.quantity' => 'required|numeric|min:0.000001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ]);

        $user = auth('web')->user();
        $branchId = $user->getBranchIdForTransaction();
        $companyId = $user->getCompanyIdForProduct();

        $statusDetail = ParameterDetail::findOrFail($request->status_id);
        $status = $statusDetail->key ?? 'draft';

        $supplier = Supplier::findOrFail($request->supplier_id);

        DB::beginTransaction();
        try {
            $purchaseNumber = $this->generatePurchaseNumber();
            $purchase = ProductPurchaseOrder::create([
                'purchase_number' => $purchaseNumber,
                'purchase_date' => $request->purchase_date,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => $supplier->name,
                'supplier_contact' => $supplier->contact,
                'supplier_address' => $supplier->address,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'status' => $status,
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes' => $request->notes,
                'subtotal' => $request->subtotal ?? 0,
                'tax_amount' => $request->tax_amount ?? 0,
                'discount_amount' => $request->discount_amount ?? 0,
                'total' => $request->total ?? 0,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Merge duplicate items (same product, variant, unit) so quantity is aggregated
            $mergedItems = $this->mergeDuplicateItems($request->items);

            $subtotal = 0;
            foreach ($mergedItems as $item) {
                $qty = $item['quantity'];
                $unitPrice = $item['unit_price'];
                $disc = $item['discount_amount'];
                $itemSubtotal = ($qty * $unitPrice) - $disc;

                ProductPurchaseOrderItem::create([
                    'purchase_order_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => !empty($item['variant_id']) ? $item['variant_id'] : null,
                    'unit_id' => $item['unit_id'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $disc,
                    'subtotal' => $itemSubtotal,
                    'notes' => $item['notes'] ?? null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
                $subtotal += $itemSubtotal;
            }

            $purchase->update([
                'subtotal' => $subtotal,
                'tax_amount' => $request->tax_amount ?? 0,
                'discount_amount' => $request->discount_amount ?? 0,
                'total' => $subtotal + (float) ($request->tax_amount ?? 0) - (float) ($request->discount_amount ?? 0),
                'updated_by' => $user->id,
            ]);

            DB::commit();
            return redirect()->route('product.purchase-order.index.view')->with('success', 'Purchase order created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function editView(Request $request, string $id)
    {
        $purchase = ProductPurchaseOrder::with(['items.product', 'items.unit', 'items.variant'])->findOrFail($id);
        $user = auth('web')->user();
        if (! in_array($purchase->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }
        $branchId = $user->current_business_unit_id;

        $products = Product::whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with([
                'defaultUnit:id,name,symbol',
                'unitConversions' => fn ($q) => $q->whereNull('deleted_at'),
                'variants' => fn ($q) => $q->where('is_active', true)->whereNull('deleted_at')
                    ->with(['variantAttributes.attributeDefinition:id,name', 'variantAttributes.attributeValue:id,value']),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'item_type_id', 'default_unit_id']);

        $units = ProductUnit::whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name', 'symbol']);

        $suppliers = Supplier::where('is_active', true)->whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'contact', 'phone', 'address', 'is_ppn', 'ppn_rate']);

        $poStatuses = ParameterDetail::whereHas('parameter', fn ($q) => $q->where('code', 'PO_STATUS'))
            ->orderByRaw("CASE key WHEN 'draft' THEN 1 WHEN 'process' THEN 2 WHEN 'receiving' THEN 3 WHEN 'payment' THEN 4 ELSE 5 END")
            ->get(['id', 'key', 'value']);

        return view('admin.product.purchase-order.edit', compact('purchase', 'products', 'units', 'suppliers', 'poStatuses'));
    }

    public function editData(Request $request)
    {
        $request->merge([
            'subtotal' => normalize_number_input($request->subtotal),
            'tax_amount' => normalize_number_input($request->tax_amount),
            'discount_amount' => normalize_number_input($request->discount_amount),
            'total' => normalize_number_input($request->total),
        ]);

        $request->merge([
            'items' => collect($request->items ?? [])->map(function ($item) {
                $item['product_id'] = $item['product_id'] ?? null;
                return $item;
            })->all(),
        ]);

        $request->validate([
            'id' => 'required|exists:product.purchase_orders,id',
            'purchase_date' => 'required|date',
            'supplier_id' => 'required|exists:master_data.suppliers,id',
            'status_id' => 'required|exists:public.parameter_details,id',
            'expected_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:product.products,id',
            'items.*.variant_id' => 'nullable|exists:product.product_variants,id',
            'items.*.unit_id' => 'required|exists:product.product_units,id',
            'items.*.quantity' => 'required|numeric|min:0.000001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ]);

        $user = auth('web')->user();
        $purchase = ProductPurchaseOrder::findOrFail($request->id);
        if (! in_array($purchase->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }
        $statusKey = $purchase->status_key ?? $purchase->status;
        if ($statusKey !== 'draft') {
            return redirect()->back()->withErrors(['error' => 'Only draft purchase orders can be edited.'])->withInput();
        }

        $user = auth('web')->user();
        $supplier = Supplier::findOrFail($request->supplier_id);

        $statusDetail = ParameterDetail::findOrFail($request->status_id);
        $status = $statusDetail->key ?? 'draft';

        DB::beginTransaction();
        try {
            $purchase->update([
                'purchase_date' => $request->purchase_date,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => $supplier->name,
                'supplier_contact' => $supplier->contact,
                'supplier_address' => $supplier->address,
                'status' => $status,
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes' => $request->notes,
                'updated_by' => $user->id,
            ]);

            $purchase->items()->delete();

            // Merge duplicate items (same product, variant, unit) so quantity is aggregated
            $mergedItems = $this->mergeDuplicateItems($request->items);

            $subtotal = 0;
            foreach ($mergedItems as $item) {
                $qty = $item['quantity'];
                $unitPrice = $item['unit_price'];
                $disc = $item['discount_amount'];
                $itemSubtotal = ($qty * $unitPrice) - $disc;

                ProductPurchaseOrderItem::create([
                    'purchase_order_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => !empty($item['variant_id']) ? $item['variant_id'] : null,
                    'unit_id' => $item['unit_id'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $disc,
                    'subtotal' => $itemSubtotal,
                    'notes' => $item['notes'] ?? null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
                $subtotal += $itemSubtotal;
            }

            $purchase->update([
                'subtotal' => $subtotal,
                'tax_amount' => $request->tax_amount ?? 0,
                'discount_amount' => $request->discount_amount ?? 0,
                'total' => $subtotal + (float) ($request->tax_amount ?? 0) - (float) ($request->discount_amount ?? 0),
                'updated_by' => $user->id,
            ]);

            DB::commit();
            return redirect()->route('product.purchase-order.detail.view', $purchase->id)->with('success', 'Purchase order updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function detailView(Request $request, string $id)
    {
        $purchase = ProductPurchaseOrder::with([
            'items.product',
            'items.unit',
            'items.variant.variantAttributes.attributeDefinition',
            'items.variant.variantAttributes.attributeValue',
            'items.receiveItems',
            'receives' => fn ($q) => $q->whereNull('deleted_at')->orderBy('receive_date', 'DESC'),
            'receives.items',
            'receives.createdByUser',
            'branch',
            'supplier',
        ])->findOrFail($id);

        $user = auth('web')->user();
        if (! in_array($purchase->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }
        return view('admin.product.purchase-order.detail', compact('purchase'));
    }

    public function exportPdf(Request $request, string $id)
    {
        $purchase = ProductPurchaseOrder::with([
            'items.product',
            'items.unit',
            'items.variant.variantAttributes.attributeDefinition',
            'items.variant.variantAttributes.attributeValue',
            'branch',
            'supplier',
        ])->findOrFail($id);

        $user = auth('web')->user();
        if (! in_array($purchase->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }

        $showPrices = $request->boolean('show_prices', true);
        $company = $purchase->company_id
            ? BusinessUnit::find($purchase->company_id)
            : WmsContext::distributor();

        $filename = 'PO-' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $purchase->purchase_number) . '.pdf';

        return Pdf::loadView('admin.product.purchase-order.pdf', compact('purchase', 'showPrices', 'company'))
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:product.purchase_orders,id',
        ]);

        $purchase = ProductPurchaseOrder::findOrFail($request->id);
        $user = auth('web')->user();
        if (! in_array($purchase->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }
        $statusKey = $purchase->status_key ?? $purchase->status;
        if ($statusKey !== 'draft') {
            return redirect()->back()->withErrors(['error' => 'Only draft purchase orders can be deleted.']);
        }

        $user = auth('web')->user();
        $purchase->updated_by = $user->id;
        $purchase->deleted_by = $user->id;
        $purchase->save();
        $purchase->delete();

        return redirect()->route('product.purchase-order.index.view')->with('success', 'Purchase order deleted successfully.');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:product.purchase_orders,id',
        ]);

        $user = auth('web')->user();
        $purchase = ProductPurchaseOrder::withTrashed()->findOrFail($request->id);
        if (! in_array($purchase->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }

        $purchase->updated_by = $user->id;
        $purchase->deleted_by = null;
        $purchase->save();
        $purchase->restore();

        return redirect()->route('product.purchase-order.index.view')->with('success', 'Purchase order restored successfully.');
    }

    protected function generateReceiveNumber(string $poNumber): string
    {
        $prefix = 'RCV-' . date('Ym') . '-';
        $last = ProductPurchaseOrderReceive::withTrashed()
            ->where('receive_number', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(receive_number) DESC, receive_number DESC')
            ->value('receive_number');
        $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;
        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Merge duplicate PO items (same product_id, variant_id, unit_id) by summing quantity and discount.
     */
    protected function mergeDuplicateItems(array $items): array
    {
        return collect($items)
            ->map(function ($item) {
                // Normalise numeric values up front
                $item['quantity'] = (float) (normalize_number_input($item['quantity'] ?? 0) ?? 0);
                $item['unit_price'] = (float) (normalize_number_input($item['unit_price'] ?? 0) ?? 0);
                $item['discount_amount'] = (float) (normalize_number_input($item['discount_amount'] ?? 0) ?? 0);
                $item['variant_id'] = $item['variant_id'] ?? null;
                return $item;
            })
            ->groupBy(function ($item) {
                return implode('|', [
                    $item['product_id'],
                    $item['variant_id'] ?: 'null',
                    $item['unit_id'],
                ]);
            })
            ->map(function ($group) {
                $base = $group->first();
                $base['quantity'] = $group->sum('quantity');
                // Sum discounts from all duplicate lines
                $base['discount_amount'] = $group->sum('discount_amount');
                // Assume same unit_price within group; if not, take last input
                $base['unit_price'] = $group->last()['unit_price'];
                return $base;
            })
            ->values()
            ->all();
    }

    protected function updatePurchaseOrderStatus(ProductPurchaseOrder $purchase): void
    {
        $purchase->load('items.receiveItems');

        $allFullyReceived = true;
        $anyReceived = false;

        foreach ($purchase->items as $item) {
            $received = (float) $item->receiveItems->sum('quantity_received');
            $ordered = (float) $item->quantity;

            if ($received > 0) {
                $anyReceived = true;
            }
            if ($received < $ordered) {
                $allFullyReceived = false;
            }
        }

        if ($allFullyReceived && $anyReceived) {
            $purchase->update(['status' => 'received']);
        } elseif ($anyReceived) {
            $purchase->update(['status' => 'receiving']);
        }
    }

    public function receiveView(Request $request, string $id)
    {
        $purchase = ProductPurchaseOrder::with([
            'items.product',
            'items.unit',
            'items.variant.variantAttributes.attributeDefinition',
            'items.variant.variantAttributes.attributeValue',
            'items.receiveItems',
        ])->findOrFail($id);

        $user = auth('web')->user();
        if (! in_array($purchase->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }

        $status = $purchase->status_key ?? $purchase->status;
        if (!in_array($status, ['process', 'receiving'])) {
            return redirect()->route('product.purchase-order.detail.view', $purchase->id)
                ->withErrors(['error' => 'Only purchase orders with status Process or Receiving can receive items.']);
        }

        $hasRemaining = $purchase->items->contains(fn ($item) => $item->quantity_remaining > 0);
        if (!$hasRemaining) {
            return redirect()->route('product.purchase-order.detail.view', $purchase->id)
                ->withErrors(['error' => 'All items have been fully received.']);
        }

        $warehouses = $this->receiveWarehouseOptions($purchase);
        $defaultWarehouseId = $this->defaultReceiveWarehouseId($purchase, $warehouses);

        return view('admin.product.purchase-order.receive', compact('purchase', 'warehouses', 'defaultWarehouseId'));
    }

    public function receiveData(Request $request)
    {
        $request->validate([
            'purchase_order_id' => 'required|exists:product.purchase_orders,id',
            'warehouse_id' => 'required|uuid',
            'receive_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:product.purchase_order_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0',
        ]);

        $purchase = ProductPurchaseOrder::with('items.receiveItems')->findOrFail($request->purchase_order_id);
        $user = auth('web')->user();
        if (! in_array($purchase->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }

        $status = $purchase->status_key ?? $purchase->status;
        if (!in_array($status, ['process', 'receiving'])) {
            return redirect()->back()->withErrors(['error' => 'Only purchase orders with status Process or Receiving can receive items.'])->withInput();
        }

        $itemsToReceive = collect($request->items)->filter(fn ($item) => (float) ($item['quantity_received'] ?? 0) > 0);
        if ($itemsToReceive->isEmpty()) {
            return redirect()->back()->withErrors(['error' => 'At least one item must have a quantity greater than 0.'])->withInput();
        }

        foreach ($itemsToReceive as $item) {
            $poItem = $purchase->items->firstWhere('id', $item['purchase_order_item_id']);
            if (!$poItem) {
                return redirect()->back()->withErrors(['error' => 'Invalid purchase order item.'])->withInput();
            }
            $remaining = $poItem->quantity_remaining;
            $qty = (float) $item['quantity_received'];
            if ($qty > $remaining) {
                $productName = $poItem->product?->name ?? 'Unknown';
                return redirect()->back()->withErrors(['error' => "Quantity for {$productName} exceeds remaining ({$remaining})."])->withInput();
            }
        }

        $user = auth('web')->user();
        $allowedWarehouseIds = collect($this->receiveWarehouseOptions($purchase))->pluck('id')->all();
        if (! in_array($request->warehouse_id, $allowedWarehouseIds, true)) {
            return redirect()->back()->withErrors(['error' => 'Gudang tujuan tidak valid.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $receive = ProductPurchaseOrderReceive::create([
                'purchase_order_id' => $purchase->id,
                'receive_number' => $this->generateReceiveNumber($purchase->purchase_number),
                'receive_date' => $request->receive_date,
                'notes' => $request->notes,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $warehouseId = $request->warehouse_id;
            $companyId = $purchase->company_id ?: $user->getCompanyIdForProduct();
            $mutationType = StockMutationType::where('code', 'PURCHASE_RECEIPT')->first();

            foreach ($itemsToReceive as $item) {
                $poItem = $purchase->items->firstWhere('id', $item['purchase_order_item_id']);
                $qtyReceived = (float) $item['quantity_received'];

                ProductPurchaseOrderReceiveItem::create([
                    'receive_id' => $receive->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'variant_id' => $poItem->variant_id,
                    'unit_id' => $poItem->unit_id,
                    'quantity_received' => $qtyReceived,
                    'notes' => $item['notes'] ?? null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                // Update HPP (average cost) secara global untuk variant / product ini
                InventoryCostService::updateAverageCostForPurchaseReceive(
                    $poItem,
                    $qtyReceived,
                    $warehouseId,
                    $companyId,
                    $user->id
                );

                // Juga update product_variant_stock + cost layer (FEFO) agar halaman stok akurat
                $variantId = $poItem->variant_id
                    ?: ProductVariant::where('product_id', $poItem->product_id)
                        ->where('is_active', true)
                        ->value('id');
                if ($variantId) {
                    StockMutationService::inbound(
                        productId: $poItem->product_id,
                        variantId: $variantId,
                        companyId: $companyId,
                        branchId: $warehouseId,
                        unitId: $poItem->unit_id,
                        quantity: $qtyReceived,
                        unitCost: (float) $poItem->unit_price,
                        referenceType: 'PurchaseReceive',
                        referenceId: $receive->id,
                        userId: $user->id,
                        notes: "Receive {$receive->receive_number} from PO {$purchase->purchase_number}",
                        date: $request->receive_date,
                    );
                }

                $stock = ProductStock::firstOrCreate(
                    [
                        'product_id' => $poItem->product_id,
                        'branch_id' => $warehouseId,
                        'unit_id' => $poItem->unit_id,
                    ],
                    [
                        'company_id' => $companyId,
                        'quantity' => 0,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]
                );

                $qtyBefore = (float) $stock->quantity;
                $qtyAfter = $qtyBefore + $qtyReceived;

                $stock->update([
                    'quantity' => $qtyAfter,
                    'updated_by' => $user->id,
                ]);

                ProductStockMovement::create([
                    'product_stock_id' => $stock->id,
                    'product_id' => $poItem->product_id,
                    'company_id' => $companyId,
                    'branch_id' => $warehouseId,
                    'unit_id' => $poItem->unit_id,
                    'stock_mutation_type_id' => $mutationType?->id,
                    'type' => 'in',
                    'quantity' => $qtyReceived,
                    'quantity_before' => $qtyBefore,
                    'quantity_after' => $qtyAfter,
                    'reference_type' => ProductPurchaseOrderReceive::class,
                    'reference_id' => $receive->id,
                    'notes' => "Receive {$receive->receive_number} from PO {$purchase->purchase_number}",
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            $this->updatePurchaseOrderStatus($purchase);

            DB::commit();
            return redirect()->route('product.purchase-order.detail.view', $purchase->id)
                ->with('success', "Receive {$receive->receive_number} saved successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function receiveDetailView(Request $request, string $id)
    {
        $receive = ProductPurchaseOrderReceive::with([
            'purchaseOrder',
            'items.product',
            'items.unit',
            'items.variant.variantAttributes.attributeDefinition',
            'items.variant.variantAttributes.attributeValue',
            'createdByUser',
        ])->findOrFail($id);

        $user = auth('web')->user();
        if (! in_array($receive->purchaseOrder->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }

        return view('admin.product.purchase-order.receive-detail', compact('receive'));
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function receiveWarehouseOptions(ProductPurchaseOrder $purchase): array
    {
        $options = [];
        $seen = [];
        $distId = $purchase->company_id ?: optional(WmsContext::distributor())->id;

        foreach (WmsContext::warehouses($distId) as $warehouse) {
            $options[] = ['id' => $warehouse->id, 'label' => $warehouse->name];
            $seen[$warehouse->id] = true;
        }

        if ($purchase->branch_id && ! isset($seen[$purchase->branch_id])) {
            $branch = BusinessUnit::find($purchase->branch_id);
            if ($branch) {
                $options[] = [
                    'id' => $branch->id,
                    'label' => $branch->name.' (Cabang PO)',
                ];
            }
        }

        return $options;
    }

    /**
     * @param  list<array{id: string, label: string}>  $warehouses
     */
    private function defaultReceiveWarehouseId(ProductPurchaseOrder $purchase, array $warehouses): ?string
    {
        $distId = $purchase->company_id ?: optional(WmsContext::distributor())->id;
        $wipId = optional(WmsContext::wipWarehouse($distId))->id;
        $allowed = collect($warehouses)->pluck('id')->all();

        if ($wipId && in_array($wipId, $allowed, true)) {
            return $wipId;
        }

        if ($purchase->branch_id && in_array($purchase->branch_id, $allowed, true)) {
            return $purchase->branch_id;
        }

        return $allowed[0] ?? null;
    }
}
