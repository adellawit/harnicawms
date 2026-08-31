<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MethodPayment;
use App\Models\Product;
use App\Models\ProductNature;
use App\Models\ProductPriceList;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderPayment;
use App\Models\ShippingRate;
use App\Models\Warehouse;
use App\Services\MembershipPointService;
use App\Services\PosCheckoutService;
use App\Services\PosWarehouseService;
use App\Services\Shipping\PosShippingOptionsService;
use App\Services\Product\ProductSearchService;
use App\Services\Promotion\PromotionEngineService;
use App\Services\Sales\BarcodeDispatchService;
use App\Services\Xendit\PaymentSyncService;
use App\Services\Xendit\XenditService;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class POSController extends Controller
{
    public function __construct(
        protected PosCheckoutService $checkout,
        protected ProductSearchService $productSearch,
        protected XenditService $xendit,
        protected PaymentSyncService $paymentSync,
        protected BarcodeDispatchService $barcodeDispatch,
        protected PosShippingOptionsService $shippingOptions,
        protected PosWarehouseService $posWarehouses,
    ) {}

    protected function getBranchId(): ?string
    {
        return auth('web')->user()?->current_business_unit_id;
    }

    protected function getCompanyId(): ?string
    {
        return auth('web')->user()?->getCompanyIdForProduct();
    }

    protected function posWarehouseFromRequest(Request $request): Warehouse
    {
        return $this->posWarehouses->resolveForPos(
            $request->warehouse_id,
            $this->getBranchId(),
            $this->getCompanyId()
        );
    }

    public function indexView(Request $request)
    {
        $branchId = $this->getBranchId();
        $companyId = $this->getCompanyId();
        $posWarehouses = $this->posWarehouses->posEnabledWarehouses($branchId, $companyId);
        $defaultPosWarehouse = $this->posWarehouses->defaultWarehouse($branchId, $companyId);

        // Type Transaction = Price Lists
        $priceLists = ProductPriceList::whereNull('deleted_at')
            ->where('is_active', true)
            ->forBusinessContext($companyId, $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $defaultPriceListId = $priceLists->firstWhere('code', 'REGULER')?->id
            ?? $priceLists->first()?->id;

        // Method Payment (from method_payments)
        $methodPayments = MethodPayment::whereNull('deleted_at')
            ->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'uses_payment_gateway', 'gateway_provider', 'payment_group_code', 'gateway_channel_code']);

        $agentConversion = session('partner_agent_conversion');
        $preselectCustomerId = $request->query('customer_id')
            ?: (is_array($agentConversion) ? ($agentConversion['customer_id'] ?? null) : null);

        // Customers (from customer.customers, filter by branch via customer_group)
        $customers = Customer::with([
            'customerGroup',
            'agent:id,customer_id,code,name,status',
            'reseller:id,customer_id,code,name,status,agent_id',
            'reseller.agent:id,code,name',
        ])
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->when($branchId, function ($q) use ($branchId) {
                $q->whereHas('customerGroup', fn ($cg) => $cg->where('branch_id', $branchId));
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'customer_group_id', 'customer_type', 'points_balance']);

        // Ensure converted agent customer is selectable even if branch filter misses them.
        if ($preselectCustomerId && ! $customers->contains('id', $preselectCustomerId)) {
            $extra = Customer::with([
                'customerGroup',
                'agent:id,customer_id,code,name,status',
                'reseller:id,customer_id,code,name,status,agent_id',
                'reseller.agent:id,code,name',
            ])
                ->whereNull('deleted_at')
                ->where('id', $preselectCustomerId)
                ->first(['id', 'code', 'name', 'customer_group_id', 'customer_type', 'points_balance']);

            if ($extra) {
                $customers = $customers->prepend($extra)->unique('id')->values();
            }
        }

        $customerSelectGroups = $this->buildPosCustomerSelectGroups($customers);
        $membershipConfig = app(MembershipPointService::class)->resolveDefaultConfig($branchId);
        $redeemValuePerPoint = (int) ($membershipConfig?->redeem_value_per_point ?? 0);
        $earnAmountStep = (int) ($membershipConfig?->transaction_amount_step ?? 0);
        $earnPointsPerStep = (int) ($membershipConfig?->points_per_step ?? 0);
        $products = Product::with('nature')
            ->withCount('variants')
            ->saleItems()
            ->whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        // Product types (product_natures) with product count
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

            // POS: tunai = kolom CASH. Kartu debit/kredit butuh EDC/Xendit (belum di POS).
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

        return view('admin.transaction.pos', [
            'products' => $products,
            'productTypes' => $productTypes,
            'priceLists' => $priceLists,
            'defaultPriceListId' => $defaultPriceListId,
            'methodPayments' => $methodPayments,
            'customers' => $customers,
            'customerSelectGroups' => $customerSelectGroups,
            'taxRate' => 11,
            'redeemValuePerPoint' => $redeemValuePerPoint,
            'earnAmountStep' => $earnAmountStep,
            'earnPointsPerStep' => $earnPointsPerStep,
            'xenditEnabled' => $this->xendit->isPaymentGatewayReady(),
            'xenditMethodCodes' => $xenditMethodCodes,
            'xenditActiveChannels' => $xenditActiveChannels,
            'xenditSyncChannels' => config('xendit.sync_channels_from_api', true),
            'xenditChannelGroups' => $xenditChannelGroups,
            'nonXenditMethods' => $nonXenditMethods,
            'preselectCustomerId' => $preselectCustomerId,
            'agentConversion' => is_array($agentConversion) ? $agentConversion : null,
            'posWarehouses' => $posWarehouses,
            'defaultPosWarehouse' => $defaultPosWarehouse,
        ]);
    }

    /**
     * Get product variants with price and stock for selected price list
     */
    public function getProductVariants(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:product.products,id',
            'price_list_id' => 'required|exists:product.product_price_lists,id',
            'warehouse_id' => 'required|uuid',
        ]);

        $branchId = $this->getBranchId();
        if (! $branchId) {
            return response()->json(['variants' => [], 'message' => 'Branch not selected']);
        }

        try {
            $warehouse = $this->posWarehouseFromRequest($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['variants' => [], 'message' => $e->getMessage()], 422);
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

        $freeUnits = $this->posWarehouses->allowsFreeUnits($warehouse);
        $result = [];
        foreach ($variants as $variant) {
            $mapped = $this->productSearch->mapVariantForPos(
                $variant,
                $branchId,
                $request->price_list_id,
                $warehouse->id
            );
            if ($mapped === null) {
                continue;
            }

            $unitOptions = $freeUnits
                ? $this->productSearch->buildPosUnitOptions($variant, $branchId, $request->price_list_id)
                : [];

            $result[] = array_merge($mapped, [
                'barcode' => $variant->barcode,
                'image' => $variant->image ?? null,
                'product_id' => $product->id,
                'default_unit_id' => $product->default_unit_id,
                'unit_options' => $unitOptions,
                'has_trackable_serials' => $this->barcodeDispatch->productHasTrackableSerials(
                    $product->id,
                    $variant->id
                ),
            ]);
        }

        return response()->json(['variants' => $result]);
    }

    /**
     * Resolve printed label serial into a POS cart line payload.
     */
    public function lookupBarcode(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string|max:20',
            'price_list_id' => 'required|uuid',
            'pending_product_id' => 'nullable|uuid',
            'pending_variant_id' => 'nullable|uuid',
            'pending_unit_id' => 'nullable|uuid',
            'warehouse_id' => 'nullable|uuid',
        ]);

        $branchId = $this->getBranchId();
        if (! $branchId) {
            return response()->json(['success' => false, 'message' => 'Branch not selected'], 422);
        }

        $warehouseId = null;
        if ($request->warehouse_id) {
            try {
                $warehouseId = $this->posWarehouseFromRequest($request)->id;
            } catch (\InvalidArgumentException $exception) {
                return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
            }
        }

        try {
            $payload = $this->barcodeDispatch->lookupForPos(
                $request->serial_number,
                $branchId,
                $request->price_list_id,
                $request->pending_product_id,
                $request->pending_variant_id,
                $request->pending_unit_id,
                $warehouseId,
            );

            return response()->json(['success' => true, 'data' => $payload]);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    /**
     * Preview free promo lines for current POS cart (live UI).
     */
    public function previewPromo(Request $request)
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'string'],
            'items.*.unit_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.000001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'warehouse_id' => ['nullable', 'uuid'],
        ]);

        $branchId = $this->getBranchId();
        $companyId = $this->getCompanyId() ?: optional(WmsContext::distributor())->id;
        try {
            $orderWarehouseId = $request->warehouse_id
                ? $this->posWarehouseFromRequest($request)->id
                : optional(WmsContext::salesSourceWarehouse($branchId))->id;
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

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
                $variant = ProductVariant::with(['product.defaultUnit', 'variantAttributes.attributeValue'])
                    ->find($row['product_variant_id']);
                $unit = $row['unit_id']
                    ? ProductUnit::query()->find($row['unit_id'], ['id', 'symbol', 'name'])
                    : ($variant?->product?->defaultUnit);

                return [
                    'variant_id' => $row['product_variant_id'],
                    'product_id' => $row['product_id'],
                    'unit_id' => $row['unit_id'],
                    'unit_label' => $unit?->symbol ?: ($unit?->name ?: null),
                    'quantity' => (float) $row['quantity'],
                    'name' => $variant
                        ? $this->productSearch->posDisplayName($variant)
                        : 'Promo item',
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

    public function shippingOptions(Request $request)
    {
        $request->validate([
            'destination_city_id' => ['nullable', 'uuid'],
            'warehouse_id' => ['nullable', 'uuid'],
            'items' => ['nullable', 'array'],
            'items.*.variant_id' => ['required_with:items', 'uuid'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0'],
        ]);

        $warehouse = null;
        if ($request->warehouse_id) {
            try {
                $warehouse = $this->posWarehouseFromRequest($request);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        $quote = $this->shippingOptions->quote(
            $this->getBranchId(),
            $request->destination_city_id,
            $request->items ?? [],
            $warehouse
        );

        return response()->json([
            'success' => true,
            ...$quote,
        ]);
    }

    /**
     * Poll payment status (Xendit / pending orders).
     */
    public function paymentStatus(Request $request, string $orderId)
    {
        $branchId = $this->getBranchId();
        $order = SalesOrder::where('id', $orderId)
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

        $data = [
            'sales_order_id' => $order->id,
            'sales_number' => $order->sales_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total' => (float) $order->total,
            'is_paid' => $order->payment_status === 'paid',
            'sync' => $syncResult,
        ];

        if ($order->payment_status === 'paid') {
            $agentConversion = $this->consumeAgentConversionForCustomer($order->customer_id);
            if ($agentConversion) {
                $data['agent_conversion'] = $agentConversion;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Paksa sinkron status invoice Xendit (mis. webhook telat).
     */
    public function syncPaymentStatus(Request $request, string $orderId)
    {
        $branchId = $this->getBranchId();
        $order = SalesOrder::where('id', $orderId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->first();

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $outcome = $this->paymentSync->syncOrderFromGateway($order, 'api_sync', $request);
        $order->refresh();

        return response()->json([
            'success' => $outcome['result'] !== 'error',
            'message' => $outcome['message'],
            'data' => [
                'sales_order_id' => $order->id,
                'sales_number' => $order->sales_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'is_paid' => $order->payment_status === 'paid',
                'sync_result' => $outcome['result'],
                'invoice_status' => $outcome['invoice_status'] ?? null,
            ],
        ]);
    }

    /**
     * Redirect setelah checkout Xendit (success / failed).
     */
    public function paymentReturn(Request $request)
    {
        $status = $request->query('status', 'success');
        $orderId = $request->query('order_id');
        $redirectStatus = $status;

        if ($orderId) {
            $order = SalesOrder::find($orderId);

            if ($order) {
                if ($status === 'success') {
                    $outcome = $this->paymentSync->syncOrderFromGateway($order, 'redirect_success', $request);
                    $order->refresh();
                    $redirectStatus = $order->payment_status === 'paid' ? 'success' : 'pending';
                } else {
                    $outcome = $this->paymentSync->handleRedirectFailed($order, $request);
                    $order->refresh();
                    $redirectStatus = $order->payment_status === 'paid' ? 'success' : 'failed';
                }
            }
        }

        return redirect()
            ->route('transaction.pos', [
                'payment' => $redirectStatus,
                'order_id' => $orderId,
            ]);
    }

    /**
     * Process POS payment: cash langsung, non-cash via Xendit invoice.
     */
    public function processPayment(Request $request)
    {
        $request->validate([
            'price_list_id' => 'required|uuid',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|uuid',
            'items.*.unit_id' => 'required|uuid',
            'items.*.quantity' => 'required|numeric|min:0.000001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:percent,nominal',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'items.*.serial_numbers' => 'nullable|array',
            'items.*.serial_numbers.*' => 'string|max:20',
            'payment_method_id' => 'required|uuid',
            'customer_id' => 'nullable|uuid',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'tax_enabled' => 'required|boolean',
            'discount_type' => 'nullable|in:percent,nominal',
            'discount_value' => 'nullable|numeric|min:0',
            'shipping_amount' => 'nullable|numeric|min:0',
            'shipping_rate_id' => 'nullable|uuid',
            'shipping_courier' => 'nullable|string|max:30',
            'shipping_service' => 'nullable|string|max:30',
            'shipping_etd' => 'nullable|string|max:30',
            'destination_city_id' => 'nullable|uuid',
            'amount_paid' => 'required|numeric|min:0',
            'xendit_channel' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'warehouse_id' => 'required|uuid',
        ]);

        $branchId = $this->getBranchId();
        $companyId = $this->getCompanyId();
        $userId = auth('web')->id();

        if (! $branchId) {
            return response()->json(['success' => false, 'message' => 'Branch not selected'], 422);
        }

        try {
            $this->assertShippingRate($request, $branchId);
            $this->posWarehouseFromRequest($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
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

        $useXendit = $this->xendit->usesXenditForMethod($methodPayment->code, $methodPayment);

        if ($useXendit) {
            return $this->processXenditPayment($request, $branchId, $companyId, $userId, $methodPayment);
        }

        return $this->processCashPayment($request, $branchId, $companyId, $userId);
    }

    protected function processCashPayment(Request $request, string $branchId, ?string $companyId, ?string $userId)
    {
        DB::beginTransaction();
        try {
            $totals = $this->checkout->buildCartTotals($request);
            $this->barcodeDispatch->assertCartSerialsForDestination(
                $request->customer_id,
                $totals['items_data'],
                $this->posWarehouses->requiresSerialScan($this->posWarehouseFromRequest($request))
            );
            $total = $totals['total'];
            $amountPaid = (float) $request->amount_paid;

            if ($amountPaid < $total) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cash amount is less than total',
                ], 422);
            }

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
            );

            $this->barcodeDispatch->assignSerialsForNewOrder(
                $order,
                $order->pending_serials_by_item_id ?? [],
                $userId,
                $branchId
            );

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

            DB::commit();

            $order->refresh();

            $paymentData = [
                'sales_order_id' => $order->id,
                'sales_number' => $order->sales_number,
                'total' => (float) $order->total,
                'amount_paid' => $amountPaid,
                'change_amount' => $changeAmount,
                'promo_free_count' => (int) ($totals['promo_free_count'] ?? 0),
                'membership_points_earned' => (int) ($order->membership_points_earned ?? 0),
                'membership_points_redeemed' => (int) ($order->membership_points_redeemed ?? 0),
                'membership_redeem_discount_amount' => (float) ($order->membership_redeem_discount_amount ?? 0),
            ];

            $agentConversion = $this->consumeAgentConversionForCustomer($order->customer_id);
            if ($agentConversion) {
                $paymentData['agent_conversion'] = $agentConversion;
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction completed successfully',
                'data' => $paymentData,
            ]);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('POS Payment failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

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
    ) {
        DB::beginTransaction();
        try {
            $totals = $this->checkout->buildCartTotals($request);
            $this->barcodeDispatch->assertCartSerialsForDestination(
                $request->customer_id,
                $totals['items_data'],
                $this->posWarehouses->requiresSerialScan($this->posWarehouseFromRequest($request))
            );
            $total = $totals['total'];

            if ($total <= 0) {
                return response()->json(['success' => false, 'message' => 'Invalid order total'], 422);
            }

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
            );

            $this->barcodeDispatch->assignSerialsForNewOrder(
                $order,
                $order->pending_serials_by_item_id ?? [],
                $userId,
                $branchId
            );

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
                'external_id' => 'pos-'.$order->id,
                'amount' => (int) round($total),
                'description' => 'POS '.$salesNumber.' - '.$channelLabel,
                'invoice_duration' => config('xendit.invoice_duration', 900),
                'currency' => 'IDR',
                'success_redirect_url' => route('transaction.pos.payment.return', [
                    'status' => 'success',
                    'order_id' => $order->id,
                ]),
                'failure_redirect_url' => route('transaction.pos.payment.return', [
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
            Log::error('POS Xendit payment failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Xendit payment failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format: TRX-DDMMYY-0001 (urut per cabang per hari).
     */
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
     * Group POS customers as: Agent A → resellers under A, Agent B → resellers under B, then others.
     *
     * @param  Collection<int, Customer>  $customers
     * @return list<array{label: string, customers: list<Customer>}>
     */
    protected function buildPosCustomerSelectGroups($customers): array
    {
        $agents = $customers->filter(fn (Customer $c) => $c->agent !== null)
            ->sortBy(fn (Customer $c) => $c->agent->code ?? $c->name)
            ->values();

        $resellers = $customers->filter(fn (Customer $c) => $c->reseller !== null)->values();
        $others = $customers->filter(fn (Customer $c) => $c->agent === null && $c->reseller === null)
            ->sortBy('name')
            ->values();

        $groupedAgentIds = [];
        $groups = [];

        foreach ($agents as $agentCustomer) {
            $agentId = $agentCustomer->agent->id;
            $groupedAgentIds[] = $agentId;

            $childResellers = $resellers
                ->filter(fn (Customer $c) => $c->reseller?->agent_id === $agentId)
                ->sortBy(fn (Customer $c) => $c->reseller->code ?? $c->name)
                ->values();

            $groupCustomers = collect([$agentCustomer])->merge($childResellers)->all();

            $groups[] = [
                'label' => 'Agent '.($agentCustomer->agent->code ?: $agentCustomer->name),
                'customers' => $groupCustomers,
            ];
        }

        $orphanResellers = $resellers
            ->filter(fn (Customer $c) => ! in_array($c->reseller?->agent_id, $groupedAgentIds, true))
            ->groupBy(fn (Customer $c) => $c->reseller?->agent_id ?: 'unknown');

        foreach ($orphanResellers as $agentId => $rows) {
            $sorted = $rows->sortBy(fn (Customer $c) => $c->reseller->code ?? $c->name)->values();
            $agentCode = $sorted->first()?->reseller?->agent?->code;
            $agentName = $sorted->first()?->reseller?->agent?->name;
            $label = $agentCode
                ? 'Agent '.$agentCode.($agentName ? ' · '.$agentName : '')
                : 'Reseller';

            $groups[] = [
                'label' => $label,
                'customers' => $sorted->all(),
            ];
        }

        if ($others->isNotEmpty()) {
            $groups[] = [
                'label' => 'Other Customers',
                'customers' => $others->all(),
            ];
        }

        return $groups;
    }

    /**
     * After paid POS for converted Agent customer: return agent code payload and clear session.
     *
     * @return array{agent_id: string, agent_code: string, agent_name: string, customer_id: string}|null
     */
    protected function consumeAgentConversionForCustomer(?string $customerId): ?array
    {
        if (! $customerId) {
            return null;
        }

        $conversion = session('partner_agent_conversion');
        if (! is_array($conversion) || ($conversion['customer_id'] ?? null) !== $customerId) {
            return null;
        }

        session()->forget('partner_agent_conversion');

        return [
            'agent_id' => (string) ($conversion['agent_id'] ?? ''),
            'agent_code' => (string) ($conversion['agent_code'] ?? ''),
            'agent_name' => (string) ($conversion['agent_name'] ?? ''),
            'customer_id' => (string) $customerId,
        ];
    }

    protected function assertShippingRate(Request $request, string $branchId): void
    {
        if (! $request->filled('shipping_rate_id')) {
            return;
        }

        $rate = ShippingRate::query()
            ->where('id', $request->shipping_rate_id)
            ->where('is_active', true)
            ->first();

        if (! $rate) {
            throw new \InvalidArgumentException('Tarif ongkir tidak valid.');
        }

        $this->shippingOptions->assertUsableRate(
            $rate,
            $branchId,
            $request->destination_city_id
        );
    }
}
