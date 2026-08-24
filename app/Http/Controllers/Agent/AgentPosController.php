<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\MethodPayment;
use App\Models\Partner\Agent;
use App\Models\Partner\Reseller;
use App\Models\Product;
use App\Models\ProductNature;
use App\Models\ProductPriceList;
use App\Models\Promotion;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderPayment;
use App\Services\PosCheckoutService;
use App\Services\Product\ProductSearchService;
use App\Services\Promotion\PromotionEngineService;
use App\Services\Sales\BarcodeDispatchService;
use App\Services\Shop\ShopContextService;
use App\Services\Xendit\PaymentSyncService;
use App\Services\Xendit\XenditService;
use App\Support\WmsContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AgentPosController extends Controller
{
    public function __construct(
        protected PosCheckoutService $checkout,
        protected ProductSearchService $productSearch,
        protected BarcodeDispatchService $barcodeDispatch,
        protected XenditService $xendit,
        protected PaymentSyncService $paymentSync,
    ) {}

    protected function context(): ShopContextService
    {
        return new ShopContextService(auth('customer')->user());
    }

    protected function agent(): Agent
    {
        $agent = auth('customer')->user()?->agent;
        abort_if(! $agent, 403);

        return $agent;
    }

    protected function branchId(): ?string
    {
        return $this->context()->branchId();
    }

    protected function companyId(): ?string
    {
        return $this->context()->companyId();
    }

    protected function agentWarehouseId(): ?string
    {
        $agent = $this->agent();

        return optional(WmsContext::defaultAgentWarehouse($agent->id))->id
            ?: $agent->default_warehouse_id;
    }

    public function index(Request $request): View
    {
        $branchId = $this->branchId();
        $companyId = $this->companyId();

        $priceLists = ProductPriceList::whereNull('deleted_at')
            ->where('is_active', true)
            ->forBusinessContext($companyId, $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $defaultPriceListId = $priceLists->firstWhere('code', 'REGULER')?->id
            ?? $priceLists->first()?->id;

        $methodPayments = MethodPayment::whereNull('deleted_at')
            ->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'uses_payment_gateway', 'gateway_provider', 'payment_group_code', 'gateway_channel_code']);

        $products = Product::with('nature')
            ->withCount('variants')
            ->saleItems()
            ->whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        $productTypes = ProductNature::withCount(['products' => fn ($q) => $q
            ->where('is_sale_item', true)
            ->when($branchId, fn ($p) => $p->where('branch_id', $branchId))
            ->whereNull('deleted_at')])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $xenditMethodCodes = config('xendit.method_codes', []);
        $xenditActiveChannels = $this->xendit->getMerchantActiveChannelCodes();
        $xenditChannelGroups = $this->xendit->buildPaymentChannelGroups($methodPayments);
        $nonXenditMethods = $methodPayments->filter(function ($mp) {
            $code = strtoupper($mp->code);

            return ! in_array($code, ['CASH', 'COD', 'DEBIT', 'CREDIT'], true)
                && ! $this->xendit->usesXenditForMethod($mp->code, $mp);
        })->values()->map(function ($mp) {
            return (object) [
                'id' => $mp->id,
                'code' => $mp->code,
                'name' => $mp->name,
                'icon' => $this->xendit->channelIconUrl($mp->code, $mp->name),
                'group_icon' => $this->xendit->groupIconClass($mp->code),
            ];
        });

        $campaigns = Promotion::activeNow()
            ->marketingType()
            ->when($companyId, fn ($q) => $q->where(function ($qq) use ($companyId) {
                $qq->whereNull('company_id')->orWhere('company_id', $companyId);
            }))
            ->with(['targetAgent:id,name', 'targetReseller:id,name,customer_id'])
            ->orderByDesc('priority')
            ->orderBy('code')
            ->get()
            ->map(fn (Promotion $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'discount_type' => $p->discount_type,
                'discount_value' => (float) $p->discount_value,
                'discount_label' => $p->discount_type === 'percent'
                    ? rtrim(rtrim(number_format((float) $p->discount_value, 2, ',', '.'), '0'), ',').'%'
                    : 'Rp '.number_format((float) $p->discount_value, 0, ',', '.'),
                'min_type' => $p->min_purchase_type,
                'min_value' => (float) $p->min_purchase_value,
                'target_type' => $p->target_type,
                'target_agent_id' => $p->target_agent_id,
                'target_reseller_id' => $p->target_reseller_id,
                'target_reseller_customer_id' => $p->targetReseller?->customer_id,
                'reactivates' => (bool) $p->reactivates_reseller,
            ])
            ->values();

        return view('agent.pos.index', [
            'products' => $products,
            'productTypes' => $productTypes,
            'priceLists' => $priceLists,
            'defaultPriceListId' => $defaultPriceListId,
            'methodPayments' => $methodPayments,
            'taxRate' => 0,
            'redeemValuePerPoint' => 0,
            'earnAmountStep' => 0,
            'earnPointsPerStep' => 0,
            'xenditEnabled' => $this->xendit->isPaymentGatewayReady(),
            'xenditMethodCodes' => $xenditMethodCodes,
            'xenditActiveChannels' => $xenditActiveChannels,
            'xenditSyncChannels' => config('xendit.sync_channels_from_api', true),
            'xenditChannelGroups' => $xenditChannelGroups,
            'nonXenditMethods' => $nonXenditMethods,
            'agentWarehouseId' => $this->agentWarehouseId(),
            'branchId' => $branchId,
            'campaigns' => $campaigns,
            'agentId' => $this->agent()->id,
        ]);
    }

    public function history(Request $request): View
    {
        $query = $this->agentPosOrdersQuery()
            ->with('customer:id,name,code')
            ->latest('created_at');

        $status = $request->get('status');
        if (in_array($status, ['paid', 'unpaid'], true)) {
            $query->where('payment_status', $status);
        }

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $query->where(function ($w) use ($search) {
                $w->where('sales_number', 'ilike', '%'.$search.'%')
                    ->orWhere('customer_name', 'ilike', '%'.$search.'%');
            });
        }

        $orders = $query->paginate(20)->withQueryString();
        $paymentOptions = $this->posPaymentOptions();

        return view('agent.pos.history', [
            'orders' => $orders,
            'statusFilter' => $status,
            'searchQuery' => $search,
            'cashMethodId' => $paymentOptions['cashMethodId'],
            'xenditChannelGroups' => $paymentOptions['xenditChannelGroups'],
            'nonXenditMethods' => $paymentOptions['nonXenditMethods'],
            'xenditEnabled' => $paymentOptions['xenditEnabled'],
        ]);
    }

    public function historyShow(string $order): View
    {
        $orderModel = $this->agentPosOrdersQuery()
            ->with([
                'customer',
                'items.product',
                'items.variant',
                'items.unit',
            ])
            ->findOrFail($order);

        $this->assertAgentPosOrderOwned($orderModel);

        $paymentOptions = $this->posPaymentOptions();

        return view('agent.pos.history-show', [
            'order' => $orderModel,
            'cashMethodId' => $paymentOptions['cashMethodId'],
            'xenditChannelGroups' => $paymentOptions['xenditChannelGroups'],
            'nonXenditMethods' => $paymentOptions['nonXenditMethods'],
            'xenditEnabled' => $paymentOptions['xenditEnabled'],
        ]);
    }

    public function invoicePdf(string $order)
    {
        $orderModel = $this->agentPosOrdersQuery()
            ->with([
                'items.product',
                'items.variant.variantAttributes.attributeValue',
                'items.unit',
                'payments.methodPayment',
                'methodPayment',
                'customer',
            ])
            ->findOrFail($order);

        $this->assertAgentPosOrderOwned($orderModel);

        $pdf = Pdf::loadView('agent.pos.pdf.invoice', [
            'order' => $orderModel,
            'agent' => $this->agent(),
        ])->setPaper('a4', 'portrait');

        $filename = 'INV-'.preg_replace('/[^A-Za-z0-9\-_]/', '_', $orderModel->sales_number).'.pdf';

        return $pdf->stream($filename);
    }

    protected function formatPromoQty(float $qty): string
    {
        if (fmod($qty, 1.0) === 0.0) {
            return (string) (int) $qty;
        }

        return rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',');
    }

    public function resellerSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        $agent = $this->agent();

        $base = Reseller::query()
            ->with('customer:id,code,name')
            ->whereNotNull('customer_id')
            ->where('status', 'active');

        if (mb_strlen($q) >= 3) {
            $base->where(function ($w) use ($q) {
                $w->where('name', 'ilike', "%{$q}%")
                    ->orWhere('code', 'ilike', "%{$q}%")
                    ->orWhereHas('customer', fn ($c) => $c
                        ->where('name', 'ilike', "%{$q}%")
                        ->orWhere('code', 'ilike', "%{$q}%"));
            });
        } else {
            $base->where('agent_id', $agent->id);
        }

        $rows = $base->orderBy('name')->limit(30)->get();

        return response()->json([
            'results' => $rows->map(function (Reseller $r) {
                $addressParts = array_filter([
                    $r->address,
                    trim(($r->city ?: '').($r->province ? ', '.$r->province : '')),
                    $r->postal_code,
                ]);

                return [
                    'id' => $r->customer_id,
                    'text' => trim(($r->name ?: $r->customer?->name).($r->customer?->code ? ' · '.$r->customer->code : '')),
                    'own' => $r->agent_id === $this->agent()->id,
                    'address' => $r->address,
                    'city' => $r->city,
                    'province' => $r->province,
                    'postal_code' => $r->postal_code,
                    'address_label' => $addressParts !== [] ? implode(', ', $addressParts) : null,
                ];
            })->values(),
        ]);
    }

    public function getProductVariants(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:product.products,id',
            'price_list_id' => 'required|exists:product.product_price_lists,id',
        ]);

        $branchId = $this->branchId();
        if (! $branchId) {
            return response()->json(['variants' => [], 'message' => 'Branch not selected']);
        }

        $product = Product::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->findOrFail($request->product_id);

        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->with(['product', 'variantAttributes.attributeValue', 'variantAttributes.attributeDefinition'])
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        $result = [];
        foreach ($variants as $variant) {
            $mapped = $this->productSearch->mapVariantForPos($variant, $branchId, $request->price_list_id);
            if ($mapped === null) {
                continue;
            }

            // mapVariantForPos()'s display_name comes from the shared
            // ProductVariant::getDisplayNameAttribute() accessor (used in 30+ admin
            // locations, so it isn't touched directly) — it returns the raw product
            // name, which in this tenant's data carries a trailing "(Product Type)"
            // suffix, plus " (SKU)" when the variant has no real attribute values.
            // Clean it here, scoped to the agent POS response only.
            $variantAttrs = $variant->variantAttributes
                ->map(fn ($va) => $va->attributeValue?->value ?? '')
                ->filter()
                ->implode(' / ');
            $cleanProductName = product_print_name($product->name);
            $mapped['display_name'] = $variantAttrs
                ? $cleanProductName.' - '.$variantAttrs
                : $cleanProductName;

            $unitOptions = $this->productSearch->buildPosUnitOptions($variant, $branchId, $request->price_list_id);

            $result[] = array_merge($mapped, [
                'barcode' => $variant->barcode,
                'image' => $variant->image ?? null,
                'product_id' => $product->id,
                'default_unit_id' => $product->default_unit_id,
                'unit_options' => $unitOptions,
                'has_trackable_serials' => $this->variantHasTrackableSerialsForAgentPos($product, $variant->id),
            ]);
        }

        return response()->json(['variants' => $result]);
    }

    /**
     * Produk ber-serial POS agen: ada master serial ATAU barang jadi ber-QR (rantai satuan barcode).
     */
    protected function variantHasTrackableSerialsForAgentPos(Product $product, ?string $variantId = null): bool
    {
        if ($this->barcodeDispatch->productHasTrackableSerials($product->id, $variantId)) {
            return true;
        }

        return $product->getBarcodeUnits()->count() > 1;
    }

    public function lookupBarcode(Request $request): JsonResponse
    {
        $request->validate([
            'serial_number' => 'required|string|max:20',
            'price_list_id' => 'required|uuid',
            'pending_product_id' => 'nullable|uuid',
            'pending_variant_id' => 'nullable|uuid',
            'pending_unit_id' => 'nullable|uuid',
        ]);

        $branchId = $this->branchId();
        if (! $branchId) {
            return response()->json(['success' => false, 'message' => 'Konteks agen tidak ditemukan'], 422);
        }

        try {
            $payload = $this->barcodeDispatch->lookupForPos(
                $request->serial_number,
                $branchId,
                $request->price_list_id,
                $request->pending_product_id,
                $request->pending_variant_id,
                $request->pending_unit_id,
            );

            return response()->json(['success' => true, 'data' => $payload]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function previewPromo(Request $request): JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'string'],
            'items.*.unit_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.000001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $branchId = $this->branchId();
        $companyId = $this->companyId() ?: optional(WmsContext::distributor())->id;
        $orderWarehouseId = $this->agentWarehouseId();

        $itemsData = [];
        foreach ($request->items as $item) {
            $variant = ProductVariant::with('product')->find($item['variant_id']);
            if (! $variant) {
                continue;
            }

            $itemsData[] = [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'unit_id' => $item['unit_id'],
                'quantity' => (float) $item['quantity'],
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'discount_type' => 'percent',
                'discount_value' => 0,
                'discount_amount' => 0,
                'subtotal' => 0,
                'is_promo_free' => false,
            ];
        }

        if ($itemsData === []) {
            return response()->json(['success' => true, 'free_items' => []]);
        }

        $expanded = PromotionEngineService::applyToCartLines(
            $itemsData,
            $companyId,
            $branchId,
            $orderWarehouseId
        );

        $freeItems = collect($expanded)
            ->filter(fn ($row) => ! empty($row['is_promo_free']))
            ->map(function (array $row) {
                $variant = ProductVariant::with(['product.defaultUnit'])->find($row['product_variant_id']);
                $unit = $row['unit_id']
                    ? ProductUnit::query()->find($row['unit_id'], ['id', 'symbol', 'name'])
                    : ($variant?->product?->defaultUnit);

                return [
                    'variant_id' => $row['product_variant_id'],
                    'product_id' => $row['product_id'],
                    'unit_id' => $row['unit_id'],
                    'unit_label' => $unit?->symbol ?: ($unit?->name ?: null),
                    'quantity' => (float) $row['quantity'],
                    'name' => $variant?->display_name
                        ?? $variant?->product?->name
                        ?? 'Promo item',
                    'image' => $variant?->image ?? $variant?->product?->image,
                    'promo_code' => $row['promo_code'] ?? null,
                    'promotion_id' => $row['promotion_id'] ?? null,
                    'notes' => $row['notes'] ?? 'Free promo item',
                    'source_warehouse_id' => $row['source_warehouse_id'] ?? null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'free_items' => $freeItems,
            'free_count' => $freeItems->count(),
        ]);
    }

    public function paymentStatus(Request $request, string $orderId): JsonResponse
    {
        $branchId = $this->branchId();
        $order = SalesOrder::where('id', $orderId)
            ->where('order_type', 'agent-pos')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->first();

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $syncResult = null;
        if ($order->payment_status !== 'paid') {
            $hasXendit = SalesOrderPayment::where('sales_order_id', $order->id)
                ->where('gateway', 'xendit')
                ->exists();

            if ($hasXendit && ($request->boolean('sync') || $request->query('sync') === '1')) {
                $syncResult = $this->paymentSync->syncOrderFromGateway($order, 'poll_status', $request);
                $order->refresh();
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sales_order_id' => $order->id,
                'sales_number' => $order->sales_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => (float) $order->total,
                'is_paid' => $order->payment_status === 'paid',
                'sync' => $syncResult,
            ],
        ]);
    }

    public function paymentReturn(Request $request): RedirectResponse
    {
        $status = $request->query('status', 'success');
        $orderId = $request->query('order_id');
        $redirectStatus = $status;

        if ($orderId) {
            $order = SalesOrder::where('id', $orderId)
                ->where('order_type', 'agent-pos')
                ->first();

            if ($order) {
                if ($status === 'success') {
                    $this->paymentSync->syncOrderFromGateway($order, 'redirect_success', $request);
                    $order->refresh();
                    $redirectStatus = $order->payment_status === 'paid' ? 'success' : 'pending';
                } else {
                    $this->paymentSync->handleRedirectFailed($order, $request);
                    $order->refresh();
                    $redirectStatus = $order->payment_status === 'paid' ? 'success' : 'failed';
                }
            }
        }

        return redirect()->route('agent-order.pos', [
            'payment' => $redirectStatus,
            'order_id' => $orderId,
        ]);
    }

    public function orderOnly(Request $request): JsonResponse
    {
        $branchId = $this->branchId();
        $companyId = $this->companyId();

        if (! $branchId) {
            return response()->json(['success' => false, 'message' => 'Branch not selected'], 422);
        }

        $request->merge([
            'branch_id' => $branchId,
            'company_id' => $companyId,
            'tax_rate' => 0,
            'tax_enabled' => false,
        ]);

        $request->validate($this->posOrderValidationRules(requirePayment: false));
        $this->applyPosMarketingPromo($request);

        DB::beginTransaction();
        try {
            $totals = $this->checkout->buildCartTotals($request);
            $salesNumber = $this->generateSalesNumber($branchId);
            $order = $this->checkout->createSalesOrder(
                $request,
                $totals,
                $salesNumber,
                $branchId,
                $companyId,
                null,
                'pending',
                'unpaid',
                'agent-pos',
                $this->agentWarehouseId(),
            );

            $this->barcodeDispatch->assignSerialsForNewOrder(
                $order,
                $order->pending_serials_by_item_id ?? [],
                null,
                $branchId,
            );

            $reseller = $request->attributes->get('pos_reseller') ?? $this->resolveResellerFromRequest($request);
            $this->applyOrderShippingAndAddress($order, $reseller);

            DB::commit();

            $order->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi disimpan sebagai pending, bisa dibayar di History.',
                'data' => [
                    'sales_order_id' => $order->id,
                    'sales_number' => $order->sales_number,
                    'total' => (float) $order->total,
                    'shipping_amount' => (float) $order->shipping_amount,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Agent POS order-only failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Order failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function payPendingOrder(Request $request, string $order): JsonResponse
    {
        $branchId = $this->branchId();

        if (! $branchId) {
            return response()->json(['success' => false, 'message' => 'Branch not selected'], 422);
        }

        $request->validate([
            'payment_method_id' => 'required|uuid',
            'amount_paid' => 'required|numeric|min:0',
            'xendit_channel' => 'nullable|string|max:50',
        ]);

        $orderModel = SalesOrder::query()
            ->where('id', $order)
            ->where('order_type', 'agent-pos')
            ->where('branch_id', $branchId)
            ->first();

        if (! $orderModel) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $this->assertPendingOrderOwnedByAgent($orderModel);

        if ($orderModel->payment_status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Order sudah lunas.'], 422);
        }

        $methodPayment = MethodPayment::find($request->payment_method_id);
        if (! $methodPayment) {
            return response()->json(['success' => false, 'message' => 'Payment method not found'], 422);
        }

        $methodCode = strtoupper((string) $methodPayment->code);
        if (in_array($methodCode, ['DEBIT', 'CREDIT'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran kartu debit/kredit belum tersedia di POS. Gunakan metode Xendit atau tunai.',
            ], 422);
        }

        if ($this->xendit->usesXenditForMethod($methodPayment->code, $methodPayment)) {
            return $this->payPendingOrderXendit($request, $orderModel, $methodPayment);
        }

        return $this->payPendingOrderCash($request, $orderModel);
    }

    public function processPayment(Request $request): JsonResponse
    {
        $branchId = $this->branchId();
        $companyId = $this->companyId();

        if (! $branchId) {
            return response()->json(['success' => false, 'message' => 'Branch not selected'], 422);
        }

        $request->merge([
            'branch_id' => $branchId,
            'company_id' => $companyId,
            'tax_rate' => 0,
            'tax_enabled' => false,
        ]);

        $request->validate($this->posOrderValidationRules(requirePayment: true));
        $this->applyPosMarketingPromo($request);

        $userId = null;

        $methodPayment = MethodPayment::find($request->payment_method_id);
        if (! $methodPayment) {
            return response()->json(['success' => false, 'message' => 'Payment method not found'], 422);
        }

        $methodCode = strtoupper((string) $methodPayment->code);
        if (in_array($methodCode, ['DEBIT', 'CREDIT'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran kartu debit/kredit belum tersedia di POS. Gunakan metode Xendit atau tunai.',
            ], 422);
        }

        $useXendit = $this->xendit->usesXenditForMethod($methodPayment->code, $methodPayment);

        if ($useXendit) {
            return $this->processXenditPayment($request, $branchId, $companyId, $userId, $methodPayment);
        }

        return $this->processCashPayment($request, $branchId, $companyId, $userId);
    }

    protected function processCashPayment(Request $request, string $branchId, ?string $companyId, ?string $userId): JsonResponse
    {
        DB::beginTransaction();
        try {
            $totals = $this->checkout->buildCartTotals($request);
            $amountPaid = (float) $request->amount_paid;

            $salesNumber = $this->generateSalesNumber($branchId);
            $order = $this->checkout->createSalesOrder(
                $request,
                $totals,
                $salesNumber,
                $branchId,
                $companyId,
                $userId,
                'pending',
                'unpaid',
                'agent-pos',
                $this->agentWarehouseId(),
            );

            $reseller = $request->attributes->get('pos_reseller') ?? $this->resolveResellerFromRequest($request);
            $this->applyOrderShippingAndAddress($order, $reseller);
            $order->refresh();

            $this->barcodeDispatch->assignSerialsForNewOrder(
                $order,
                $order->pending_serials_by_item_id ?? [],
                $userId,
                $branchId,
            );

            $total = (float) $order->total;
            if ($amountPaid < $total) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Cash amount is less than total',
                ], 422);
            }

            $changeAmount = max($amountPaid - $total, 0);

            SalesOrderPayment::create([
                'sales_order_id' => $order->id,
                'method_payment_id' => $request->payment_method_id,
                'payment_code' => 'PAY-'.$salesNumber,
                'amount' => $amountPaid,
                'change_amount' => $changeAmount,
                'status' => 'completed',
                'created_by' => $userId,
            ]);

            $this->checkout->completePaidOrder($order->fresh(), $userId);
            $this->barcodeDispatch->finalizeIfEligible($order->id, $userId, $branchId);

            $this->maybeReactivateReseller(
                $request->attributes->get('marketing_promo'),
                $request->attributes->get('pos_reseller'),
            );

            DB::commit();

            $order->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Transaction completed successfully',
                'data' => [
                    'sales_order_id' => $order->id,
                    'sales_number' => $order->sales_number,
                    'total' => (float) $order->total,
                    'amount_paid' => $amountPaid,
                    'change_amount' => $changeAmount,
                    'promo_free_count' => (int) ($totals['promo_free_count'] ?? 0),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Agent POS payment failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Payment failed: '.$e->getMessage(),
            ], 500);
        }
    }

    protected function processXenditPayment(
        Request $request,
        string $branchId,
        ?string $companyId,
        ?string $userId,
        MethodPayment $methodPayment,
    ): JsonResponse {
        DB::beginTransaction();
        try {
            $totals = $this->checkout->buildCartTotals($request);

            $salesNumber = $this->generateSalesNumber($branchId);
            $order = $this->checkout->createSalesOrder(
                $request,
                $totals,
                $salesNumber,
                $branchId,
                $companyId,
                $userId,
                'pending',
                'unpaid',
                'agent-pos',
                $this->agentWarehouseId(),
            );

            $reseller = $request->attributes->get('pos_reseller') ?? $this->resolveResellerFromRequest($request);
            $this->applyOrderShippingAndAddress($order, $reseller);
            $order->refresh();

            $this->barcodeDispatch->assignSerialsForNewOrder(
                $order,
                $order->pending_serials_by_item_id ?? [],
                $userId,
                $branchId,
            );

            $total = (float) $order->total;

            if ($total <= 0) {
                DB::rollBack();

                return response()->json(['success' => false, 'message' => 'Invalid order total'], 422);
            }

            $payment = SalesOrderPayment::create([
                'sales_order_id' => $order->id,
                'method_payment_id' => $request->payment_method_id,
                'payment_code' => 'PAY-'.$salesNumber,
                'gateway' => 'xendit',
                'amount' => $total,
                'change_amount' => 0,
                'status' => 'pending',
                'created_by' => $userId,
            ]);

            $xenditChannel = $request->xendit_channel ?: $methodPayment->gateway_channel_code;
            $channelLabel = $xenditChannel
                ? strtoupper((string) $xenditChannel)
                : $methodPayment->name;

            $invoicePayload = [
                'external_id' => 'agent-pos-'.$order->id,
                'amount' => (int) round($total),
                'description' => 'Agent POS '.$salesNumber.' - '.$channelLabel,
                'invoice_duration' => config('xendit.invoice_duration', 900),
                'currency' => 'IDR',
                'success_redirect_url' => route('agent-order.pos.payment.return', [
                    'status' => 'success',
                    'order_id' => $order->id,
                ]),
                'failure_redirect_url' => route('agent-order.pos.payment.return', [
                    'status' => 'failed',
                    'order_id' => $order->id,
                ]),
            ];

            $xenditMethods = $this->xendit->resolvePaymentMethods(
                $methodPayment->resolvesPgGroupCode(),
                $xenditChannel
            );
            if ($xenditMethods !== null) {
                $invoicePayload['payment_methods'] = $xenditMethods;
            }

            if ($xenditChannel) {
                $payment->update([
                    'notes' => 'xendit_channel:'.strtoupper((string) $xenditChannel),
                ]);
            }

            $invoice = $this->xendit->createInvoice($invoicePayload);

            $invoiceUrl = $invoice['invoice_url'] ?? null;
            $invoiceId = $invoice['id'] ?? null;

            if (! $invoiceUrl) {
                throw new \RuntimeException('Xendit did not return invoice_url');
            }

            $payment->update([
                'gateway_reference' => $invoiceId,
                'gateway_url' => $invoiceUrl,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Redirect to Xendit to complete payment',
                'xendit' => true,
                'data' => [
                    'sales_order_id' => $order->id,
                    'sales_number' => $order->sales_number,
                    'total' => (float) $order->total,
                    'invoice_url' => $invoiceUrl,
                    'invoice_id' => $invoiceId,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Agent POS Xendit payment failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Xendit payment failed: '.$e->getMessage(),
            ], 500);
        }
    }

    protected function generateSalesNumber(string $branchId): string
    {
        $prefix = 'TRX';
        $dateKey = now()->format('dmy');
        $pattern = "{$prefix}-{$dateKey}-%";

        $lastOrder = SalesOrder::where('branch_id', $branchId)
            ->where('sales_number', 'like', $pattern)
            ->lockForUpdate()
            ->orderByDesc('sales_number')
            ->value('sales_number');

        $seq = 1;
        if ($lastOrder && preg_match('/-(\d+)$/', $lastOrder, $matches)) {
            $seq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $dateKey, $seq);
    }

    /**
     * @return array<string, mixed>
     */
    protected function posOrderValidationRules(bool $requirePayment = true): array
    {
        $rules = [
            'price_list_id' => 'required|uuid',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|uuid',
            'items.*.unit_id' => 'required|uuid',
            'items.*.price_list_id' => 'nullable|uuid',
            'items.*.quantity' => 'required|numeric|min:0.000001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:percent,nominal',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.serial_numbers' => 'nullable|array',
            'items.*.serial_numbers.*' => 'string|max:20',
            'customer_id' => 'nullable|uuid',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'tax_enabled' => 'required|boolean',
            'discount_type' => 'nullable|in:percent,nominal',
            'discount_value' => 'nullable|numeric|min:0',
            'shipping_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'marketing_promotion_id' => 'nullable|uuid|exists:product.promotions,id',
        ];

        if ($requirePayment) {
            $rules['payment_method_id'] = 'required|uuid';
            $rules['amount_paid'] = 'required|numeric|min:0';
            $rules['xendit_channel'] = 'nullable|string|max:50';
        }

        return $rules;
    }

    protected function applyPosMarketingPromo(Request $request): void
    {
        $marketingPromo = null;
        $reseller = null;

        if ($request->filled('marketing_promotion_id')) {
            $marketingPromo = Promotion::activeNow()
                ->marketingType()
                ->find($request->marketing_promotion_id);
            abort_unless($marketingPromo, 422, 'Promo tidak valid.');

            $reseller = $this->resolveResellerFromRequest($request);

            $this->assertMarketingTargetMatches($marketingPromo, $this->agent()->id, $reseller);

            [$subtotal, $qty] = $this->cartAmountAndQty($request->items);
            $meets = $marketingPromo->min_purchase_type === 'qty'
                ? $qty >= (float) $marketingPromo->min_purchase_value
                : $subtotal >= (float) $marketingPromo->min_purchase_value;
            abort_unless($meets, 422, 'Syarat promo belum terpenuhi.');

            $request->merge([
                'discount_type' => $marketingPromo->discount_type,
                'discount_value' => (float) $marketingPromo->discount_value,
            ]);
        }

        $request->attributes->set('marketing_promo', $marketingPromo);
        $request->attributes->set('pos_reseller', $reseller);
    }

    protected function resolveResellerFromRequest(Request $request): ?Reseller
    {
        if (! $request->customer_id) {
            return null;
        }

        return Reseller::where('customer_id', $request->customer_id)->first();
    }

    protected function formatResellerAddress(?Reseller $reseller): ?string
    {
        if (! $reseller) {
            return null;
        }

        $parts = array_filter([
            $reseller->address,
            trim(($reseller->city ?: '').($reseller->province ? ', '.$reseller->province : '')),
            $reseller->postal_code,
        ]);

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    protected function applyOrderShippingAndAddress(SalesOrder $order, ?Reseller $reseller): void
    {
        $order->update([
            'customer_address' => $this->formatResellerAddress($reseller),
        ]);
    }

    protected function assertPendingOrderOwnedByAgent(SalesOrder $order): void
    {
        $this->assertAgentPosOrderOwned($order);
        abort_unless($order->payment_status !== 'paid', 422, 'Order sudah lunas.');
    }

    protected function assertAgentPosOrderOwned(SalesOrder $order): void
    {
        abort_unless($order->order_type === 'agent-pos', 403);

        $agentWarehouseId = $this->agentWarehouseId();
        abort_unless($agentWarehouseId && $order->warehouse_id === $agentWarehouseId, 403);

        $branchId = $this->branchId();
        if ($branchId) {
            abort_unless($order->branch_id === $branchId, 403);
        }
    }

    protected function agentPosOrdersQuery()
    {
        $branchId = $this->branchId();
        $warehouseId = $this->agentWarehouseId();

        return SalesOrder::query()
            ->where('order_type', 'agent-pos')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId));
    }

    /**
     * @return array{
     *   cashMethodId: ?string,
     *   xenditChannelGroups: array<int, mixed>,
     *   nonXenditMethods: \Illuminate\Support\Collection,
     *   xenditEnabled: bool
     * }
     */
    protected function posPaymentOptions(): array
    {
        $branchId = $this->branchId();
        $methodPayments = MethodPayment::whereNull('deleted_at')
            ->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'uses_payment_gateway', 'gateway_provider', 'payment_group_code', 'gateway_channel_code']);

        $cashMethodId = $methodPayments->first(fn ($mp) => strtoupper($mp->code) === 'CASH')?->id
            ?: $methodPayments->first()?->id;

        $nonXenditMethods = $methodPayments->filter(function ($mp) {
            $code = strtoupper($mp->code);

            return ! in_array($code, ['CASH', 'COD', 'DEBIT', 'CREDIT'], true)
                && ! $this->xendit->usesXenditForMethod($mp->code, $mp);
        })->values()->map(function ($mp) {
            return (object) [
                'id' => $mp->id,
                'code' => $mp->code,
                'name' => $mp->name,
                'icon' => $this->xendit->channelIconUrl($mp->code, $mp->name),
            ];
        });

        return [
            'cashMethodId' => $cashMethodId,
            'xenditChannelGroups' => $this->xendit->buildPaymentChannelGroups($methodPayments),
            'nonXenditMethods' => $nonXenditMethods,
            'xenditEnabled' => $this->xendit->isPaymentGatewayReady(),
        ];
    }

    protected function payPendingOrderCash(Request $request, SalesOrder $order): JsonResponse
    {
        DB::beginTransaction();
        try {
            $order = SalesOrder::lockForUpdate()->findOrFail($order->id);

            if ($order->payment_status === 'paid') {
                DB::rollBack();

                return response()->json(['success' => false, 'message' => 'Order sudah lunas.'], 422);
            }

            $total = (float) $order->total;
            $amountPaid = (float) $request->amount_paid;

            if ($amountPaid < $total) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Cash amount is less than total',
                ], 422);
            }

            $changeAmount = max($amountPaid - $total, 0);

            SalesOrderPayment::create([
                'sales_order_id' => $order->id,
                'method_payment_id' => $request->payment_method_id,
                'payment_code' => 'PAY-'.$order->sales_number,
                'amount' => $amountPaid,
                'change_amount' => $changeAmount,
                'status' => 'completed',
                'created_by' => null,
            ]);

            $this->checkout->completePaidOrder($order->fresh(), null);
            $this->barcodeDispatch->finalizeIfEligible($order->id, null, (string) $order->branch_id);

            DB::commit();

            $order->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Transaction completed successfully',
                'data' => [
                    'sales_order_id' => $order->id,
                    'sales_number' => $order->sales_number,
                    'total' => (float) $order->total,
                    'amount_paid' => $amountPaid,
                    'change_amount' => $changeAmount,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Agent POS pay pending (cash) failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Payment failed: '.$e->getMessage(),
            ], 500);
        }
    }

    protected function payPendingOrderXendit(Request $request, SalesOrder $order, MethodPayment $methodPayment): JsonResponse
    {
        DB::beginTransaction();
        try {
            $order = SalesOrder::lockForUpdate()->findOrFail($order->id);

            if ($order->payment_status === 'paid') {
                DB::rollBack();

                return response()->json(['success' => false, 'message' => 'Order sudah lunas.'], 422);
            }

            $total = (float) $order->total;

            if ($total <= 0) {
                DB::rollBack();

                return response()->json(['success' => false, 'message' => 'Invalid order total'], 422);
            }

            $payment = SalesOrderPayment::create([
                'sales_order_id' => $order->id,
                'method_payment_id' => $request->payment_method_id,
                'payment_code' => 'PAY-'.$order->sales_number,
                'gateway' => 'xendit',
                'amount' => $total,
                'change_amount' => 0,
                'status' => 'pending',
                'created_by' => null,
            ]);

            $xenditChannel = $request->xendit_channel ?: $methodPayment->gateway_channel_code;
            $channelLabel = $xenditChannel
                ? strtoupper((string) $xenditChannel)
                : $methodPayment->name;

            $invoicePayload = [
                'external_id' => 'agent-pos-'.$order->id,
                'amount' => (int) round($total),
                'description' => 'Agent POS '.$order->sales_number.' - '.$channelLabel,
                'invoice_duration' => config('xendit.invoice_duration', 900),
                'currency' => 'IDR',
                'success_redirect_url' => route('agent-order.pos.payment.return', [
                    'status' => 'success',
                    'order_id' => $order->id,
                ]),
                'failure_redirect_url' => route('agent-order.pos.payment.return', [
                    'status' => 'failed',
                    'order_id' => $order->id,
                ]),
            ];

            $xenditMethods = $this->xendit->resolvePaymentMethods(
                $methodPayment->resolvesPgGroupCode(),
                $xenditChannel
            );
            if ($xenditMethods !== null) {
                $invoicePayload['payment_methods'] = $xenditMethods;
            }

            if ($xenditChannel) {
                $payment->update([
                    'notes' => 'xendit_channel:'.strtoupper((string) $xenditChannel),
                ]);
            }

            $invoice = $this->xendit->createInvoice($invoicePayload);

            $invoiceUrl = $invoice['invoice_url'] ?? null;
            $invoiceId = $invoice['id'] ?? null;

            if (! $invoiceUrl) {
                throw new \RuntimeException('Xendit did not return invoice_url');
            }

            $payment->update([
                'gateway_reference' => $invoiceId,
                'gateway_url' => $invoiceUrl,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Redirect to Xendit to complete payment',
                'xendit' => true,
                'data' => [
                    'sales_order_id' => $order->id,
                    'sales_number' => $order->sales_number,
                    'total' => (float) $order->total,
                    'invoice_url' => $invoiceUrl,
                    'invoice_id' => $invoiceId,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Agent POS pay pending (Xendit) failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Xendit payment failed: '.$e->getMessage(),
            ], 500);
        }
    }

    protected function assertMarketingTargetMatches(Promotion $promo, string $agentId, ?Reseller $reseller): void
    {
        $targetType = $promo->target_type;

        if ($targetType === 'agent') {
            if ($promo->target_agent_id && $promo->target_agent_id !== $agentId) {
                abort(422, 'Promo tidak berlaku untuk target ini.');
            }

            return;
        }

        if ($targetType === 'reseller') {
            if (! $reseller) {
                abort(422, 'Promo tidak berlaku untuk target ini.');
            }
            if ($promo->target_reseller_id && $promo->target_reseller_id !== $reseller->id) {
                abort(422, 'Promo tidak berlaku untuk target ini.');
            }

            return;
        }

        if ($targetType === 'both') {
            $agentOk = ! $promo->target_agent_id || $promo->target_agent_id === $agentId;
            $resellerOk = $reseller && (
                ! $promo->target_reseller_id || $promo->target_reseller_id === $reseller->id
            );

            if ($agentOk || $resellerOk) {
                return;
            }

            abort(422, 'Promo tidak berlaku untuk target ini.');
        }

        abort(422, 'Promo tidak berlaku untuk target ini.');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{0: float, 1: float}
     */
    protected function cartAmountAndQty(array $items): array
    {
        $subtotal = 0.0;
        $qty = 0.0;

        foreach ($items as $item) {
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);
            $lineTotal = $unitPrice * $quantity;
            $discType = $item['discount_type'] ?? 'percent';
            $discValue = (float) ($item['discount_value'] ?? 0);
            $discAmt = 0.0;

            if ($discType === 'percent') {
                $discAmt = round($lineTotal * $discValue / 100);
            } else {
                $discAmt = min(round($discValue), round($lineTotal));
            }

            $subtotal += ($lineTotal - $discAmt);
            $qty += $quantity;
        }

        return [$subtotal, $qty];
    }

    protected function maybeReactivateReseller(?Promotion $marketingPromo, ?Reseller $reseller): void
    {
        if (! $marketingPromo?->reactivates_reseller || ! $reseller || $reseller->status === 'active') {
            return;
        }

        $reseller->update(['status' => 'active']);
    }
}
