<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\ParameterDetail;
use App\Models\Product;
use App\Models\ProductBatch;
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
use App\Models\Warehouse;
use App\Services\BatchStockService;
use App\Services\InventoryCostService;
use App\Services\PurchaseOrderHierarchyService;
use App\Services\StockMutationService;
use App\Support\PurchaseOrderCartonDisplay;
use App\Support\PurchaseOrderCatalog;
use App\Support\PurchaseOrderReceiveWarehouse;
use App\Support\PurchaseOrderStatus;
use App\Support\WmsContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class PurchaseOrderController extends Controller
{
    protected function generatePurchaseNumber(string $kind = 'standalone'): string
    {
        $prefixKey = match ($kind) {
            PurchaseOrderHierarchyService::KIND_MASTER => 'CPO-',
            default => 'PO-',
        };
        $prefix = $prefixKey.date('Ym').'-';
        $last = ProductPurchaseOrder::withTrashed()
            ->where('purchase_number', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(purchase_number) DESC, purchase_number DESC')
            ->value('purchase_number');
        $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    protected function computePurchaseTotal(float $subtotal, float $tax, float $discount, float $otherCost): float
    {
        return $subtotal + $tax - $discount + $otherCost;
    }

    protected function purchaseProductsQuery(?string $branchId)
    {
        return Product::query()
            ->whereNull('deleted_at')
            ->where('is_purchase_item', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with([
                'itemType:id,key,value',
                'defaultUnit:id,name,symbol',
                'unitConversions' => fn ($q) => $q->whereNull('deleted_at'),
                'variants' => fn ($q) => $q->where('is_active', true)->whereNull('deleted_at')
                    ->with(['variantAttributes.attributeDefinition:id,name', 'variantAttributes.attributeValue:id,value']),
            ])
            ->orderBy('name');
    }

    protected function purchaseUnitsQuery(?string $branchId)
    {
        return ProductUnit::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->when($branchId, fn ($query) => $query->orWhere('branch_id', $branchId));
            })
            ->orderBy('name');
    }

    public function indexView(Request $request)
    {
        $user = auth('web')->user();
        $defaultBranchId = $user->current_business_unit_id;

        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';

        $filterBranchId = $request->get('branch_id', $defaultBranchId);
        $poStatuses = PurchaseOrderStatus::selectableOptions();

        return view('admin.product.purchase-order.index', compact('status', 'isFilter', 'filterBranchId', 'poStatuses'));
    }

    protected function purchaseOrderStatusBadge(ProductPurchaseOrder $row): string
    {
        $statusKey = $row->status_key ?? $row->status;
        $tone = $row->deleted_at
            ? 'danger'
            : (in_array($statusKey, ['draft'], true)
                ? 'secondary'
                : (in_array($statusKey, ['payment', 'received'], true) ? 'success' : 'warning'));

        return '<span class="badge bg-label-' . $tone . '">' . ($row->deleted_at ? 'Deleted' : e($row->status_label)) . '</span>';
    }

    protected function purchaseOrderKindBadge(ProductPurchaseOrder $row): string
    {
        $kind = $row->po_kind ?? 'standalone';
        $color = match ($kind) {
            'master' => 'primary',
            'sub' => 'info',
            default => 'secondary',
        };
        $label = $row->po_kind_label ?? ucfirst($kind);

        return '<span class="badge bg-label-' . $color . '">' . e($label) . '</span>';
    }

    /**
     * Receive tindakan di list: Sub-PO / standalone berstatus process|receiving dengan sisa qty.
     */
    protected function purchaseOrderCanReceive(ProductPurchaseOrder $row): bool
    {
        if ($row->deleted_at) {
            return false;
        }

        if (! PurchaseOrderHierarchyService::canReceive($row)) {
            return false;
        }

        $status = $row->status_key ?? $row->status;
        if (! in_array($status, ['process', 'receiving'], true)) {
            return false;
        }

        $row->loadMissing(['items' => fn ($q) => $q->whereNull('deleted_at'), 'items.receiveItems']);

        return $row->items->contains(fn ($item) => $item->quantity_remaining > 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function purchaseOrderIndexPayload(ProductPurchaseOrder $row, bool $includeTreeMeta = false): array
    {
        $payload = [
            'id' => $row->id,
            'purchase_number' => $row->purchase_number,
            'purchase_date' => $row->purchase_date,
            'supplier_name' => $row->supplier_name,
            'po_kind' => $row->po_kind ?? 'standalone',
            'po_kind_badge' => $this->purchaseOrderKindBadge($row),
            'status' => $row->status,
            'status_key' => $row->status_key ?? $row->status,
            'status_badge' => $this->purchaseOrderStatusBadge($row),
            'can_update_status' => PurchaseOrderStatus::canUpdate($row) ? 1 : 0,
            'can_create_sub' => PurchaseOrderHierarchyService::canCreateSubPurchaseOrder($row) ? 1 : 0,
            'can_receive' => $this->purchaseOrderCanReceive($row) ? 1 : 0,
            'progress_pct' => PurchaseOrderHierarchyService::receiveProgressPercent($row),
            'progress_display' => PurchaseOrderHierarchyService::receiveProgressHtml($row),
            'total_fmt' => format_number((float) $row->total, 2, true),
            'deleted_at' => $row->deleted_at,
            'parent_id' => $row->parent_id,
            'parent_number' => $row->parent?->purchase_number ? e($row->parent->purchase_number) : '-',
        ];

        if ($includeTreeMeta) {
            $childrenCount = (int) ($row->children_count ?? 0);
            $payload['children_count'] = $childrenCount;
            $payload['has_children'] = ($row->po_kind ?? '') === PurchaseOrderHierarchyService::KIND_MASTER && $childrenCount > 0;
            $payload['tree_control'] = $payload['has_children']
                ? '<i class="ti ti-chevron-right po-tree-toggle" data-id="' . e($row->id) . '" title="Tampilkan Release Order"></i>'
                : '<span class="po-tree-spacer"></span>';
            $payload['purchase_number_display'] = e($row->purchase_number)
                . ($payload['has_children']
                    ? ' <span class="badge bg-label-info ms-1">' . $childrenCount . ' RO</span>'
                    : '');
        }

        return $payload;
    }

    protected function purchaseOrderChildrenQuery(Request $request, string $parentId)
    {
        $user = auth('web')->user();
        $branchId = $request->get('branch_id');

        $master = ProductPurchaseOrder::query()
            ->where('po_kind', PurchaseOrderHierarchyService::KIND_MASTER)
            ->findOrFail($parentId);

        if (! in_array($master->branch_id, $user->getAccessibleBusinessUnitIdsForQuery(), true)) {
            abort(403, 'Unauthorized.');
        }

        $query = ProductPurchaseOrder::query()->with([
            'parent:id,purchase_number',
            'items' => fn ($q) => $q->whereNull('deleted_at'),
            'items.receiveItems',
        ])->where('parent_id', $parentId);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($request->status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($request->status !== 'active') {
            $query->withTrashed();
        }

        return $query->orderBy('release_sequence')
            ->orderBy('created_at');
    }

    public function indexData(Request $request)
    {
        $user = auth('web')->user();
        $branchId = $request->get('branch_id');

        $data = ProductPurchaseOrder::query()->with([
            'parent:id,purchase_number',
            'items' => fn ($q) => $q->whereNull('deleted_at'),
            'items.receiveItems',
        ])->withCount('children')
            ->whereNull('parent_id');

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
            ->addColumn('status_badge', fn ($row) => $this->purchaseOrderStatusBadge($row))
            ->addColumn('status_key', fn ($row) => $row->status_key ?? $row->status)
            ->addColumn('can_update_status', fn ($row) => PurchaseOrderStatus::canUpdate($row) ? 1 : 0)
            ->addColumn('can_create_sub', fn ($row) => PurchaseOrderHierarchyService::canCreateSubPurchaseOrder($row) ? 1 : 0)
            ->addColumn('can_receive', fn ($row) => $this->purchaseOrderCanReceive($row) ? 1 : 0)
            ->addColumn('po_kind_badge', fn ($row) => $this->purchaseOrderKindBadge($row))
            ->addColumn('parent_number', fn ($row) => $row->parent?->purchase_number ? e($row->parent->purchase_number) : '-')
            ->addColumn('progress_pct', fn ($row) => PurchaseOrderHierarchyService::receiveProgressPercent($row))
            ->addColumn('progress_display', fn ($row) => PurchaseOrderHierarchyService::receiveProgressHtml($row))
            ->addColumn('total_fmt', fn ($row) => format_number((float) $row->total, 2, true))
            ->addColumn('children_count', fn ($row) => (int) ($row->children_count ?? 0))
            ->addColumn('has_children', fn ($row) => ($row->po_kind ?? '') === PurchaseOrderHierarchyService::KIND_MASTER && (int) ($row->children_count ?? 0) > 0)
            ->addColumn('tree_control', function ($row) {
                if (($row->po_kind ?? '') !== PurchaseOrderHierarchyService::KIND_MASTER || (int) ($row->children_count ?? 0) < 1) {
                    return '<span class="po-tree-spacer"></span>';
                }

                return '<i class="ti ti-chevron-right po-tree-toggle" data-id="' . e($row->id) . '" title="Tampilkan Release Order"></i>';
            })
            ->addColumn('purchase_number_display', function ($row) {
                $html = e($row->purchase_number);
                if (($row->po_kind ?? '') === PurchaseOrderHierarchyService::KIND_MASTER && (int) ($row->children_count ?? 0) > 0) {
                    $html .= ' <span class="badge bg-label-info ms-1">' . (int) $row->children_count . ' RO</span>';
                }

                return $html;
            })
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('purchase_number', 'ilike', "%{$search}%")
                            ->orWhere('supplier_name', 'ilike', "%{$search}%")
                            ->orWhereHas('children', function ($childQuery) use ($search) {
                                $childQuery->where('purchase_number', 'ilike', "%{$search}%")
                                    ->orWhere('supplier_name', 'ilike', "%{$search}%");
                            });
                    });
                }
            })
            ->rawColumns(['status_badge', 'po_kind_badge', 'progress_display', 'total_fmt', 'tree_control', 'purchase_number_display'])
            ->toJson();
    }

    public function childrenData(Request $request, string $id)
    {
        $children = $this->purchaseOrderChildrenQuery($request, $id)->get();

        return response()->json([
            'data' => $children->map(fn (ProductPurchaseOrder $row) => $this->purchaseOrderIndexPayload($row))->values(),
        ]);
    }

    public function mastersForSub(Request $request)
    {
        $user = auth('web')->user();
        $masters = PurchaseOrderHierarchyService::eligibleMastersForUser($user->getAccessibleBusinessUnitIdsForQuery());

        return response()->json($masters->map(fn (ProductPurchaseOrder $master) => [
            'id' => $master->id,
            'purchase_number' => $master->purchase_number,
            'supplier_id' => $master->supplier_id,
            'supplier_name' => $master->supplier_name,
            'purchase_date' => optional($master->purchase_date)->format('d/m/Y'),
            'release_status' => $master->release_status,
            'release_status_label' => $master->release_status_label,
            'has_remaining' => PurchaseOrderHierarchyService::masterHasRemainingRelease($master),
        ])->values());
    }

    public function masterItems(Request $request, string $id)
    {
        $user = auth('web')->user();
        $master = ProductPurchaseOrder::with(['items.product', 'items.unit', 'items.variant'])
            ->where('po_kind', PurchaseOrderHierarchyService::KIND_MASTER)
            ->findOrFail($id);

        if (! in_array($master->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }

        PurchaseOrderHierarchyService::backfillParentItemLinks($master);

        return response()->json([
            'master' => [
                'id' => $master->id,
                'purchase_number' => $master->purchase_number,
                'supplier_id' => $master->supplier_id,
                'supplier_name' => $master->supplier_name,
                'supplier_contact' => $master->supplier_contact,
                'supplier_address' => $master->supplier_address,
            ],
            'items' => PurchaseOrderHierarchyService::masterItemsPayload($master),
        ]);
    }

    public function suppliersByType(Request $request)
    {
        $branchId = auth('web')->user()->current_business_unit_id;

        $suppliers = Supplier::query()
            ->with('supplierType:id,key,value')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'contact', 'phone', 'address', 'is_ppn', 'ppn_rate', 'supplier_type_id']);

        return response()->json(PurchaseOrderCatalog::suppliersPayload($suppliers));
    }

    public function insertView(Request $request)
    {
        $branchId = auth('web')->user()->current_business_unit_id;

        $products = PurchaseOrderCatalog::productsPayload(
            $this->purchaseProductsQuery($branchId)->get(['id', 'name', 'code', 'item_type_id', 'default_unit_id', 'is_purchase_item'])
        );

        $units = $this->purchaseUnitsQuery($branchId)->get(['id', 'name', 'symbol']);

        $poStatuses = ParameterDetail::whereHas('parameter', fn ($q) => $q->where('code', 'PO_STATUS'))
            ->orderByRaw("CASE key WHEN 'draft' THEN 1 WHEN 'process' THEN 2 WHEN 'receiving' THEN 3 WHEN 'payment' THEN 4 ELSE 5 END")
            ->get(['id', 'key', 'value']);

        $defaultPoKind = $request->get('po_kind', 'standalone');
        $defaultParentId = $request->get('parent_id');
        $lockDocumentTypeToSub = $defaultPoKind === 'sub' && filled($defaultParentId);
        $supplierItemTypeMap = PurchaseOrderCatalog::SUPPLIER_ITEM_TYPE_MAP;

        return view('admin.product.purchase-order.insert', compact(
            'products',
            'units',
            'poStatuses',
            'defaultPoKind',
            'defaultParentId',
            'lockDocumentTypeToSub',
            'supplierItemTypeMap'
        ));
    }

    public function insertData(Request $request)
    {
        $request->merge([
            'subtotal' => normalize_number_input($request->subtotal),
            'tax_amount' => normalize_number_input($request->tax_amount),
            'discount_amount' => normalize_number_input($request->discount_amount),
            'other_cost_amount' => normalize_number_input($request->other_cost_amount),
            'total' => normalize_number_input($request->total),
        ]);

        $request->merge([
            'items' => collect($request->items ?? [])
                ->map(function ($item) {
                    $item['product_id'] = $item['product_id'] ?? null;

                    return $item;
                })
                ->filter(fn ($item) => (float) ($item['quantity'] ?? 0) > 0)
                ->values()
                ->all(),
        ]);

        $request->validate([
            'po_kind' => 'required|in:standalone,master,sub',
            'parent_id' => 'nullable|uuid|exists:product.purchase_orders,id|required_if:po_kind,sub',
            'purchase_date' => 'required|date',
            'supplier_id' => 'required_unless:po_kind,sub|nullable|exists:master_data.suppliers,id',
            'status_id' => 'required|exists:public.parameter_details,id',
            'expected_delivery_date' => 'nullable|date',
            'attention_to' => 'nullable|string|max:200',
            'ship_to_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'subtotal' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'other_cost_amount' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.parent_item_id' => 'nullable|uuid|exists:product.purchase_order_items,id',
            'items.*.product_id' => 'required|exists:product.products,id',
            'items.*.variant_id' => 'nullable|exists:product.product_variants,id',
            'items.*.unit_id' => 'required|exists:product.product_units,id',
            'items.*.quantity' => 'required|numeric|min:0.000001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ]);

        if ($request->filled('parent_id') && $request->input('po_kind') !== PurchaseOrderHierarchyService::KIND_SUB) {
            return redirect()->back()
                ->withErrors(['po_kind' => 'Release dari CPO hanya dapat bertipe RO, bukan PO standalone atau CPO baru.'])
                ->withInput();
        }

        $user = auth('web')->user();
        $branchId = $user->getBranchIdForTransaction();
        $companyId = $user->getCompanyIdForProduct();
        $poKind = $request->input('po_kind', PurchaseOrderHierarchyService::KIND_STANDALONE);

        $parent = null;
        if ($poKind === PurchaseOrderHierarchyService::KIND_SUB) {
            $parent = ProductPurchaseOrder::with('items')->findOrFail($request->parent_id);
            if (! PurchaseOrderHierarchyService::isMaster($parent)) {
                return redirect()->back()->withErrors(['parent_id' => 'PO induk tidak valid.'])->withInput();
            }
            if (! PurchaseOrderHierarchyService::canCreateSubPurchaseOrder($parent)) {
                return redirect()->back()
                    ->withErrors(['parent_id' => 'Sub-PO hanya dapat dibuat dari CPO berstatus Process (bukan Draft).'])
                    ->withInput();
            }
            if (! in_array($parent->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
                abort(403, 'Unauthorized.');
            }
            PurchaseOrderHierarchyService::backfillParentItemLinks($parent);
            $mergedForValidation = $this->mergeDuplicateItems($request->items ?? [], true);
            PurchaseOrderHierarchyService::validateSubPurchaseItems($parent, $mergedForValidation);
        }

        $statusDetail = ParameterDetail::findOrFail($request->status_id);
        $status = $statusDetail->key ?? 'draft';

        if ($poKind === PurchaseOrderHierarchyService::KIND_SUB) {
            $supplier = Supplier::findOrFail($parent->supplier_id);
        } else {
            $supplier = Supplier::findOrFail($request->supplier_id);
        }

        DB::beginTransaction();
        try {
            if ($poKind === PurchaseOrderHierarchyService::KIND_SUB) {
                $purchaseNumber = PurchaseOrderHierarchyService::generateSubPurchaseNumber($parent);
                $releaseSequence = PurchaseOrderHierarchyService::nextReleaseSequence($parent);
            } else {
                $purchaseNumber = $this->generatePurchaseNumber($poKind);
                $releaseSequence = null;
            }

            $purchase = ProductPurchaseOrder::create([
                'purchase_number' => $purchaseNumber,
                'purchase_date' => $request->purchase_date,
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'supplier_contact' => $supplier->contact,
                'supplier_address' => $supplier->address,
                'attention_to' => $request->attention_to,
                'ship_to_address' => $request->ship_to_address,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'parent_id' => $parent?->id,
                'po_kind' => $poKind,
                'release_sequence' => $releaseSequence,
                'release_status' => $poKind === PurchaseOrderHierarchyService::KIND_MASTER ? 'open' : null,
                'status' => $status,
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes' => $request->notes,
                'subtotal' => $request->subtotal ?? 0,
                'tax_amount' => $request->tax_amount ?? 0,
                'discount_amount' => $request->discount_amount ?? 0,
                'other_cost_amount' => $request->other_cost_amount ?? 0,
                'total' => $request->total ?? 0,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $mergedItems = $this->mergeDuplicateItems($request->items, $poKind === PurchaseOrderHierarchyService::KIND_SUB);

            $productsById = Product::query()
                ->with('unitConversions')
                ->whereIn('id', collect($mergedItems)->pluck('product_id')->filter()->unique())
                ->get()
                ->keyBy('id');
            $unitsById = ProductUnit::query()->get()->keyBy('id');

            $subtotal = 0;
            foreach ($mergedItems as $item) {
                $qty = $item['quantity'];
                $unitPrice = $item['unit_price'];
                $disc = $item['discount_amount'];
                $itemSubtotal = ($qty * $unitPrice) - $disc;
                $product = $productsById->get($item['product_id']);
                $carton = $product
                    ? $this->resolveCartonFields($product, (float) $qty, $item['unit_id'], $unitsById)
                    : ['carton_qty' => null, 'carton_display' => null];

                ProductPurchaseOrderItem::create([
                    'purchase_order_id' => $purchase->id,
                    'parent_item_id' => $item['parent_item_id']
                        ?? ($parent ? PurchaseOrderHierarchyService::resolveParentItemId($parent, $item) : null),
                    'product_id' => $item['product_id'],
                    'variant_id' => ! empty($item['variant_id']) ? $item['variant_id'] : null,
                    'unit_id' => $item['unit_id'],
                    'quantity' => $qty,
                    'carton_qty' => $carton['carton_qty'],
                    'carton_display' => $carton['carton_display'],
                    'batch_number' => null,
                    'expiry_date' => null,
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
                'other_cost_amount' => $request->other_cost_amount ?? 0,
                'total' => $this->computePurchaseTotal(
                    $subtotal,
                    (float) ($request->tax_amount ?? 0),
                    (float) ($request->discount_amount ?? 0),
                    (float) ($request->other_cost_amount ?? 0)
                ),
                'updated_by' => $user->id,
            ]);

            if ($parent) {
                PurchaseOrderHierarchyService::syncParentReleaseStatus($parent);
            }

            DB::commit();

            $message = match ($poKind) {
                PurchaseOrderHierarchyService::KIND_MASTER => 'CPO (Contract PO) created successfully. Use RO for partial releases.',
                PurchaseOrderHierarchyService::KIND_SUB => 'RO (Release Order) created from CPO.',
                default => 'PO created successfully.',
            };

            return redirect()->route('product.purchase-order.detail.view', $purchase->id)->with('success', $message);
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

        if (! PurchaseOrderHierarchyService::isEditable($purchase)) {
            return redirect()->route('product.purchase-order.detail.view', $purchase->id)
                ->withErrors(['error' => PurchaseOrderHierarchyService::isMaster($purchase)
                    ? 'PO Utama tidak dapat diedit setelah disimpan.'
                    : 'Hanya PO draft yang dapat diedit.']);
        }

        $branchId = $user->current_business_unit_id;

        $products = PurchaseOrderCatalog::productsPayload(
            $this->purchaseProductsQuery($branchId)->get(['id', 'name', 'code', 'item_type_id', 'default_unit_id', 'is_purchase_item'])
        );

        $units = $this->purchaseUnitsQuery($branchId)->get(['id', 'name', 'symbol']);

        $suppliers = Supplier::query()
            ->with('supplierType:id,key,value')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'contact', 'phone', 'address', 'is_ppn', 'ppn_rate', 'supplier_type_id']);

        $poStatuses = ParameterDetail::whereHas('parameter', fn ($q) => $q->where('code', 'PO_STATUS'))
            ->orderByRaw("CASE key WHEN 'draft' THEN 1 WHEN 'process' THEN 2 WHEN 'receiving' THEN 3 WHEN 'payment' THEN 4 ELSE 5 END")
            ->get(['id', 'key', 'value']);

        $supplierItemTypeMap = PurchaseOrderCatalog::SUPPLIER_ITEM_TYPE_MAP;

        return view('admin.product.purchase-order.edit', compact(
            'purchase',
            'products',
            'units',
            'suppliers',
            'poStatuses',
            'supplierItemTypeMap'
        ));
    }

    public function editData(Request $request)
    {
        $request->merge([
            'subtotal' => normalize_number_input($request->subtotal),
            'tax_amount' => normalize_number_input($request->tax_amount),
            'discount_amount' => normalize_number_input($request->discount_amount),
            'other_cost_amount' => normalize_number_input($request->other_cost_amount),
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
            'attention_to' => 'nullable|string|max:200',
            'ship_to_address' => 'nullable|string',
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

        if (! PurchaseOrderHierarchyService::isEditable($purchase)) {
            return redirect()->back()->withErrors(['error' => PurchaseOrderHierarchyService::isMaster($purchase)
                ? 'PO Utama tidak dapat diedit setelah disimpan.'
                : 'Hanya PO draft yang dapat diedit.'])->withInput();
        }

        $statusKey = $purchase->status_key ?? $purchase->status;
        if ($statusKey !== 'draft') {
            return redirect()->back()->withErrors(['error' => 'Only draft purchase orders can be edited.'])->withInput();
        }

        if (PurchaseOrderHierarchyService::isSub($purchase)) {
            $parent = ProductPurchaseOrder::with('items')->findOrFail($purchase->parent_id);
            PurchaseOrderHierarchyService::backfillParentItemLinks($parent);
            $mergedForValidation = $this->mergeDuplicateItems($request->items ?? [], true);
            PurchaseOrderHierarchyService::validateSubPurchaseItems($parent, $mergedForValidation, $purchase->id);
        }

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
                'attention_to' => $request->attention_to,
                'ship_to_address' => $request->ship_to_address,
                'status' => $status,
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes' => $request->notes,
                'updated_by' => $user->id,
            ]);

            $purchase->items()->delete();

            // Merge duplicate items (same product, variant, unit) so quantity is aggregated
            $mergedItems = $this->mergeDuplicateItems(
                $request->items,
                PurchaseOrderHierarchyService::isSub($purchase)
            );

            $parentForItems = PurchaseOrderHierarchyService::isSub($purchase)
                ? ProductPurchaseOrder::with('items')->find($purchase->parent_id)
                : null;

            $productsById = Product::query()
                ->with('unitConversions')
                ->whereIn('id', collect($mergedItems)->pluck('product_id')->filter()->unique())
                ->get()
                ->keyBy('id');
            $unitsById = ProductUnit::query()->get()->keyBy('id');

            $subtotal = 0;
            foreach ($mergedItems as $item) {
                $qty = $item['quantity'];
                $unitPrice = $item['unit_price'];
                $disc = $item['discount_amount'];
                $itemSubtotal = ($qty * $unitPrice) - $disc;
                $product = $productsById->get($item['product_id']);
                $carton = $product
                    ? $this->resolveCartonFields($product, (float) $qty, $item['unit_id'], $unitsById)
                    : ['carton_qty' => null, 'carton_display' => null];

                ProductPurchaseOrderItem::create([
                    'purchase_order_id' => $purchase->id,
                    'parent_item_id' => $item['parent_item_id']
                        ?? ($parentForItems ? PurchaseOrderHierarchyService::resolveParentItemId($parentForItems, $item) : null),
                    'product_id' => $item['product_id'],
                    'variant_id' => !empty($item['variant_id']) ? $item['variant_id'] : null,
                    'unit_id' => $item['unit_id'],
                    'quantity' => $qty,
                    'carton_qty' => $carton['carton_qty'],
                    'carton_display' => $carton['carton_display'],
                    'batch_number' => null,
                    'expiry_date' => null,
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
                'other_cost_amount' => $request->other_cost_amount ?? 0,
                'total' => $this->computePurchaseTotal(
                    $subtotal,
                    (float) ($request->tax_amount ?? 0),
                    (float) ($request->discount_amount ?? 0),
                    (float) ($request->other_cost_amount ?? 0)
                ),
                'updated_by' => $user->id,
            ]);

            DB::commit();

            if ($parentForItems) {
                PurchaseOrderHierarchyService::syncParentReleaseStatus($parentForItems);
            }

            return redirect()->route('product.purchase-order.detail.view', $purchase->id)->with('success', 'Purchase order updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function detailView(Request $request, string $id)
    {
        $purchase = ProductPurchaseOrder::with([
            'parent',
            'children' => fn ($q) => $q->whereNull('deleted_at')->orderBy('release_sequence'),
            'items.product',
            'items.unit',
            'items.variant.variantAttributes.attributeDefinition',
            'items.variant.variantAttributes.attributeValue',
            'items.receiveItems',
            'items.parentItem',
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

        $masterReleaseSummary = null;
        if (PurchaseOrderHierarchyService::isMaster($purchase)) {
            $masterReleaseSummary = PurchaseOrderHierarchyService::masterItemsPayload($purchase);
        }

        return view('admin.product.purchase-order.detail', compact('purchase', 'masterReleaseSummary'));
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
            ->stream($filename);
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:product.purchase_orders,id',
        ]);

        $purchase = ProductPurchaseOrder::with('parent')->findOrFail($request->id);
        $user = auth('web')->user();
        if (! in_array($purchase->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }

        if (PurchaseOrderHierarchyService::isMaster($purchase)) {
            $hasChildren = $purchase->children()->whereNull('deleted_at')->exists();
            if ($hasChildren) {
                return redirect()->back()->withErrors(['error' => 'PO Utama yang sudah memiliki Sub-PO tidak dapat dihapus.']);
            }
        }

        $statusKey = $purchase->status_key ?? $purchase->status;
        if ($statusKey !== 'draft') {
            return redirect()->back()->withErrors(['error' => 'Only draft purchase orders can be deleted.']);
        }

        $parent = $purchase->parent;
        $user = auth('web')->user();
        $purchase->updated_by = $user->id;
        $purchase->deleted_by = $user->id;
        $purchase->save();
        $purchase->delete();

        if ($parent && PurchaseOrderHierarchyService::isMaster($parent)) {
            PurchaseOrderHierarchyService::syncParentReleaseStatus($parent);
        }

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

    public function updateStatusData(Request $request)
    {
        $allowedKeys = PurchaseOrderStatus::selectableOptions()->pluck('key')->all();

        $request->validate([
            'id' => 'required|exists:product.purchase_orders,id',
            'status' => 'required|string|in:'.implode(',', $allowedKeys),
        ]);

        $user = auth('web')->user();
        $purchase = ProductPurchaseOrder::findOrFail($request->id);

        if (! in_array($purchase->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }

        if (! PurchaseOrderStatus::canUpdate($purchase)) {
            return redirect()->back()->withErrors(['error' => 'Status PO ini tidak dapat diubah.']);
        }

        $newStatus = $request->status;
        $error = PurchaseOrderStatus::validateTransition($purchase, $newStatus);
        if ($error) {
            return redirect()->back()->withErrors(['error' => $error]);
        }

        $currentStatus = $purchase->status_key ?? $purchase->status;
        if ($currentStatus === $newStatus) {
            return redirect()->route('product.purchase-order.index.view')
                ->with('success', 'Status purchase order tidak berubah.');
        }

        $purchase->update([
            'status' => $newStatus,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('product.purchase-order.index.view')
            ->with('success', 'Status purchase order diubah menjadi '.PurchaseOrderStatus::label($newStatus).'.');
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
    protected function mergeDuplicateItems(array $items, bool $preserveParentItemIds = false): array
    {
        return collect($items)
            ->map(function ($item) {
                $item['quantity'] = (float) (normalize_number_input($item['quantity'] ?? 0) ?? 0);
                $item['unit_price'] = (float) (normalize_number_input($item['unit_price'] ?? 0) ?? 0);
                $item['discount_amount'] = (float) (normalize_number_input($item['discount_amount'] ?? 0) ?? 0);
                $item['variant_id'] = $item['variant_id'] ?? null;
                $item['parent_item_id'] = $item['parent_item_id'] ?? null;
                $item['batch_number'] = $this->normalizeBatchNumber($item['batch_number'] ?? '');

                return $item;
            })
            ->groupBy(function ($item) use ($preserveParentItemIds) {
                if ($preserveParentItemIds && ! empty($item['parent_item_id'])) {
                    return 'parent:' . $item['parent_item_id'];
                }

                return implode('|', [
                    $item['product_id'],
                    $item['variant_id'] ?: 'null',
                    $item['unit_id'],
                    $item['batch_number'] ?: 'null',
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
            'items.product.nature',
            'items.unit',
            'items.variant.variantAttributes.attributeDefinition',
            'items.variant.variantAttributes.attributeValue',
            'items.receiveItems',
        ])->findOrFail($id);

        $user = auth('web')->user();
        if (! in_array($purchase->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }

        if (! PurchaseOrderHierarchyService::canReceive($purchase)) {
            return redirect()->route('product.purchase-order.detail.view', $purchase->id)
                ->withErrors(['error' => 'Penerimaan hanya dapat dilakukan pada Sub-PO atau PO standalone, bukan PO Utama.']);
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
        $suggestedBatchNumber = $this->generateBatchNumber(now()->format('Y-m-d'));

        return view('admin.product.purchase-order.receive', compact(
            'purchase',
            'warehouses',
            'defaultWarehouseId',
            'suggestedBatchNumber'
        ));
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
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|string',
            'items.*.use_batch' => 'nullable|in:0,1',
        ]);

        $purchase = ProductPurchaseOrder::with('items.receiveItems')->findOrFail($request->purchase_order_id);
        $user = auth('web')->user();
        if (! in_array($purchase->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }

        if (! PurchaseOrderHierarchyService::canReceive($purchase)) {
            return redirect()->back()->withErrors(['error' => 'Penerimaan hanya dapat dilakukan pada Sub-PO atau PO standalone.'])->withInput();
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

            $useBatch = (string) ($item['use_batch'] ?? '0') === '1';
            if (! $useBatch) {
                continue;
            }

            $batchNumber = $this->normalizeBatchNumber($item['batch_number'] ?? '');
            if ($batchNumber === '' || $this->isSystemGeneratedBatchNumber($batchNumber)) {
                $batchNumber = $this->generateBatchNumber($request->receive_date);
            }
            if ($batchNumber === '') {
                $productName = $poItem->product?->name ?? 'Unknown';
                return redirect()->back()->withErrors(['error' => "Kode Batch wajib diisi untuk {$productName}."])->withInput();
            }
            if (empty(trim((string) ($item['expiry_date'] ?? '')))) {
                $productName = $poItem->product?->name ?? 'Unknown';
                return redirect()->back()->withErrors(['error' => "Tanggal Expired wajib diisi untuk {$productName}."])->withInput();
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
                'warehouse_id' => $request->warehouse_id,
                'receive_number' => $this->generateReceiveNumber($purchase->purchase_number),
                'receive_date' => $request->receive_date,
                'notes' => $request->notes,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $warehouseId = $request->warehouse_id;
            $warehouse = Warehouse::query()->whereKey($warehouseId)->first();
            $stockBranchId = $warehouse?->branch_id ?: $warehouse?->company_id ?: $purchase->branch_id;
            $companyId = $purchase->company_id ?: $user->getCompanyIdForProduct();
            $mutationType = StockMutationType::where('code', 'PURCHASE_RECEIPT')->first();

            foreach ($itemsToReceive as $item) {
                $poItem = $purchase->items->firstWhere('id', $item['purchase_order_item_id']);
                $qtyReceived = (float) $item['quantity_received'];

                $useBatch = (string) ($item['use_batch'] ?? '0') === '1';
                $batchNumber = $useBatch ? $this->normalizeBatchNumber($item['batch_number'] ?? '') : null;
                if ($useBatch && ($batchNumber === null || $batchNumber === '' || $this->isSystemGeneratedBatchNumber($batchNumber))) {
                    $batchNumber = $this->generateBatchNumber($request->receive_date);
                }
                $expiryDate = $useBatch && ! empty(trim((string) ($item['expiry_date'] ?? '')))
                    ? HelperController::parseDate($item['expiry_date'])
                    : null;

                $productBatch = null;
                if ($useBatch && $batchNumber) {
                    $productBatch = BatchStockService::receiveInbound(
                        productId: $poItem->product_id,
                        companyId: $companyId,
                        batchNumber: $batchNumber,
                        expiryDate: $expiryDate,
                        warehouseId: $warehouseId,
                        branchId: $stockBranchId,
                        unitId: $poItem->unit_id,
                        quantity: $qtyReceived,
                        userId: $user->id,
                    );

                    $poItemUpdates = [
                        'updated_by' => $user->id,
                    ];
                    if (! $poItem->batch_number) {
                        $poItemUpdates['batch_number'] = $batchNumber;
                    }
                    if (! $poItem->expiry_date && $expiryDate) {
                        $poItemUpdates['expiry_date'] = $expiryDate;
                    }
                    if (! $poItem->product_batch_id) {
                        $poItemUpdates['product_batch_id'] = $productBatch->id;
                    }
                    if (count($poItemUpdates) > 1) {
                        $poItem->update($poItemUpdates);
                    }
                }

                ProductPurchaseOrderReceiveItem::create([
                    'receive_id' => $receive->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'variant_id' => $poItem->variant_id,
                    'unit_id' => $poItem->unit_id,
                    'batch_number' => $batchNumber,
                    'expiry_date' => $expiryDate,
                    'product_batch_id' => $productBatch?->id,
                    'quantity_received' => $qtyReceived,
                    'notes' => $item['notes'] ?? null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                // Update HPP (average cost) secara global untuk variant / product ini
                InventoryCostService::updateAverageCostForPurchaseReceive(
                    $poItem,
                    $qtyReceived,
                    $stockBranchId,
                    $companyId,
                    $user->id,
                    $warehouseId
                );

                // Update product_variant_stock + cost layer (FEFO) agar halaman stok akurat
                $variant = ProductVariant::resolveForStock(
                    $poItem->product_id,
                    $poItem->variant_id,
                    $user->id
                );

                if ($variant) {
                    StockMutationService::inbound(
                        productId: $poItem->product_id,
                        variantId: $variant->id,
                        companyId: $companyId,
                        branchId: $stockBranchId,
                        unitId: $poItem->unit_id,
                        quantity: $qtyReceived,
                        unitCost: (float) $poItem->unit_price,
                        referenceType: 'PurchaseReceive',
                        referenceId: $receive->id,
                        userId: $user->id,
                        notes: "Receive {$receive->receive_number} from PO {$purchase->purchase_number}",
                        date: $request->receive_date,
                        expiryDate: $expiryDate,
                        warehouseId: $warehouseId,
                    );
                }

                // Legacy product_stock unik per (product_id, branch_id) saja — jangan include warehouse/unit di lookup.
                $stock = ProductStock::withTrashed()->firstOrNew([
                    'product_id' => $poItem->product_id,
                    'branch_id' => $stockBranchId,
                ]);

                if ($stock->trashed()) {
                    $stock->restore();
                }

                if (! $stock->exists) {
                    $stock->company_id = $companyId;
                    $stock->warehouse_id = $warehouseId;
                    $stock->unit_id = $poItem->unit_id;
                    $stock->quantity = 0;
                    $stock->created_by = $user->id;
                }

                $qtyBefore = (float) $stock->quantity;
                $qtyAfter = $qtyBefore + $qtyReceived;

                $stock->fill([
                    'company_id' => $companyId ?: $stock->company_id,
                    'warehouse_id' => $warehouseId,
                    'unit_id' => $poItem->unit_id ?: $stock->unit_id,
                    'quantity' => $qtyAfter,
                    'updated_by' => $user->id,
                ]);
                $stock->save();

                ProductStockMovement::create([
                    'product_stock_id' => $stock->id,
                    'product_id' => $poItem->product_id,
                    'company_id' => $companyId,
                    'branch_id' => $stockBranchId,
                    'warehouse_id' => $warehouseId,
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

            if (PurchaseOrderHierarchyService::isSub($purchase)) {
                $purchase->loadMissing('parent');
                if ($purchase->parent && PurchaseOrderHierarchyService::isMaster($purchase->parent)) {
                    PurchaseOrderHierarchyService::syncParentReleaseStatus($purchase->parent);
                }
            }

            DB::commit();

            $hasBatchLabels = $receive->items()
                ->whereNotNull('batch_number')
                ->where('batch_number', '!=', '')
                ->exists();

            return redirect()
                ->route('product.purchase-order.receive-detail.view', $receive->id)
                ->with('success', "Receive {$receive->receive_number} saved successfully.")
                ->with('print_batch', $hasBatchLabels);
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

        $batchItems = $receive->items->filter(
            fn ($item) => filled(trim((string) ($item->batch_number ?? '')))
        );

        return view('admin.product.purchase-order.receive-detail', compact('receive', 'batchItems'));
    }

    public function receiveBatchPrint(Request $request, string $id)
    {
        $receive = ProductPurchaseOrderReceive::with([
            'purchaseOrder',
            'items.product',
            'items.unit',
            'items.variant',
            'warehouse',
        ])->findOrFail($id);

        $user = auth('web')->user();
        if (! in_array($receive->purchaseOrder->branch_id, $user->getAccessibleBusinessUnitIdsForQuery())) {
            abort(403, 'Unauthorized.');
        }

        $labels = $receive->items
            ->filter(fn ($item) => filled(trim((string) ($item->batch_number ?? ''))))
            ->values();

        if ($labels->isEmpty()) {
            return redirect()
                ->route('product.purchase-order.receive-detail.view', $receive->id)
                ->withErrors(['error' => 'Tidak ada item dengan kode batch untuk dicetak.']);
        }

        $copies = max(1, min(20, (int) $request->get('copies', 1)));

        $company = BusinessUnit::query()
            ->whereKey($receive->purchaseOrder->company_id)
            ->first();

        return Pdf::loadView('admin.product.purchase-order.print-batch-labels', [
            'receive' => $receive,
            'labels' => $labels,
            'copies' => $copies,
            'company' => $company,
        ])
            ->setPaper('a4', 'portrait')
            ->stream('batch-labels-'.$receive->receive_number.'.pdf');
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function receiveWarehouseOptions(ProductPurchaseOrder $purchase): array
    {
        $options = [];
        $seen = [];
        $distId = $purchase->company_id ?: optional(WmsContext::distributor())->id;
        $operationalUnit = $purchase->branch_id ? BusinessUnit::find($purchase->branch_id) : null;
        $contexts = $operationalUnit?->type_code === 'COMPANY'
            ? [$distId]
            : [$purchase->branch_id ?: $distId];

        foreach ($contexts as $contextId) {
            if (! $contextId) {
                continue;
            }

            foreach (WmsContext::warehouses($contextId) as $warehouse) {
                if (isset($seen[$warehouse->id])) {
                    continue;
                }

                $type = $warehouse->warehouse_type_code ? " ({$warehouse->warehouse_type_code})" : '';
                $branch = $warehouse->branch?->name ? " - {$warehouse->branch->name}" : '';

                $options[] = [
                    'id' => $warehouse->id,
                    'label' => trim("{$warehouse->code} - {$warehouse->name}{$type}{$branch}"),
                ];
                $seen[$warehouse->id] = true;
            }
        }

        if (PurchaseOrderReceiveWarehouse::hasReceivableRawMaterial($purchase)) {
            $wip = WmsContext::wipWarehouse($distId);
            if ($wip) {
                $options = PurchaseOrderReceiveWarehouse::appendWarehouseOption($options, $seen, $wip);
            }
        }

        if ($purchase->warehouse_id && ! isset($seen[$purchase->warehouse_id])) {
            $warehouse = Warehouse::query()->whereKey($purchase->warehouse_id)->first();
            if ($warehouse) {
                $type = $warehouse->warehouse_type_code ? " ({$warehouse->warehouse_type_code})" : '';
                $options[] = [
                    'id' => $warehouse->id,
                    'label' => trim("{$warehouse->code} - {$warehouse->name}{$type}"),
                ];
            }
        }

        return PurchaseOrderReceiveWarehouse::filterOptionsByPurchase($purchase, $options);
    }

    /**
     * @param  list<array{id: string, label: string}>  $warehouses
     */
    private function defaultReceiveWarehouseId(ProductPurchaseOrder $purchase, array $warehouses): ?string
    {
        $companyId = $purchase->company_id ?: optional(WmsContext::distributor())->id;

        return PurchaseOrderReceiveWarehouse::defaultWarehouseId($purchase, $warehouses, $companyId);
    }

    /**
     * @return array{carton_qty: ?float, carton_display: ?string}
     */
    protected function resolveCartonFields(Product $product, float $quantity, string $unitId, $unitsById): array
    {
        return [
            'carton_qty' => PurchaseOrderCartonDisplay::boxQuantity($product, $quantity, $unitId),
            'carton_display' => PurchaseOrderCartonDisplay::format($product, $quantity, $unitId, $unitsById),
        ];
    }

    protected function normalizeBatchNumber(?string $batchNumber): string
    {
        return strtoupper(trim((string) $batchNumber));
    }

    /**
     * System format: BATCH-{YYMMDD}{seq} e.g. BATCH-26071601
     * (Batch + Tahun 2 digit + Bulan + Tanggal + nomor unik harian)
     */
    protected function isSystemGeneratedBatchNumber(?string $batchNumber): bool
    {
        return (bool) preg_match('/^BATCH-\d{8}$/i', trim((string) $batchNumber));
    }

    /** @var array<string, int> */
    protected array $batchSequenceCache = [];

    protected function generateBatchNumber(mixed $date = null): string
    {
        $carbon = now();
        $raw = is_string($date) ? trim($date) : null;

        if ($raw) {
            try {
                $carbon = \Carbon\Carbon::parse(HelperController::parseDate($raw));
            } catch (\Throwable) {
                // keep now()
            }
        }

        $dateKey = $carbon->format('ymd'); // e.g. 260716
        $prefix = 'BATCH-'.$dateKey;

        if (! isset($this->batchSequenceCache[$dateKey])) {
            $maxSeq = 0;

            $candidates = ProductBatch::withTrashed()
                ->where('batch_number', 'ilike', $prefix.'%')
                ->pluck('batch_number');

            foreach ($candidates as $candidate) {
                if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/i', (string) $candidate, $m)) {
                    $maxSeq = max($maxSeq, (int) $m[1]);
                }
            }

            $this->batchSequenceCache[$dateKey] = $maxSeq;
        }

        $this->batchSequenceCache[$dateKey]++;

        return $prefix.str_pad((string) $this->batchSequenceCache[$dateKey], 2, '0', STR_PAD_LEFT);
    }
}
