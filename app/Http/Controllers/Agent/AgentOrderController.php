<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Marketing\Asset;
use App\Models\Marketing\Category;
use App\Models\MethodPayment;
use App\Models\Partner\Reseller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantStock;
use App\Models\Promotion;
use App\Models\SalesOrder;
use App\Models\ShippingRate;
use App\Models\Training\Course;
use App\Services\Shop\ShopCartService;
use App\Services\Shop\ShopCheckoutService;
use App\Services\Shop\ShopContextService;
use App\Services\Shipping\AgentShippingEstimator;
use App\Services\StockMutationService;
use App\Services\Xendit\PaymentSyncService;
use App\Services\Xendit\XenditService;
use App\Support\WmsContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AgentOrderController extends Controller
{
    private const ORDER_TYPE = 'web-order';

    protected function context(): ShopContextService
    {
        return new ShopContextService(auth('customer')->user());
    }

    protected function cart(): ShopCartService
    {
        return new ShopCartService($this->context());
    }

    protected function checkoutService(): ShopCheckoutService
    {
        return app(ShopCheckoutService::class, ['context' => $this->context()]);
    }

    protected function shippingEstimator(): AgentShippingEstimator
    {
        return app(AgentShippingEstimator::class);
    }

    protected function resolveShippingAddress(): ?string
    {
        $customer = $this->context()->customer();
        $agent = $customer->agent;

        return $customer->address_shipping ?: ($customer->address ?: $agent?->address);
    }

    public function dashboard(): View
    {
        $ctx = $this->context();
        $customer = $ctx->customer();
        $agent = $customer->agent;
        $cid = $customer->id;

        $activeOrdersCount = SalesOrder::where('order_type', self::ORDER_TYPE)
            ->where('customer_id', $cid)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $ordersThisMonth = SalesOrder::where('order_type', self::ORDER_TYPE)
            ->where('customer_id', $cid)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $activeResellers = $agent
            ? $agent->resellers()->where('status', 'active')->count()
            : 0;

        $activeOrders = SalesOrder::where('order_type', self::ORDER_TYPE)
            ->where('customer_id', $cid)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->latest('created_at')
            ->limit(4)
            ->get();

        $lastOrder = SalesOrder::where('order_type', self::ORDER_TYPE)
            ->where('customer_id', $cid)
            ->where('status', 'completed')
            ->latest('created_at')
            ->first();

        $resellers = $agent
            ? $agent->resellers()->latest('created_at')->limit(4)->get()
            : collect();

        $marketingAssets = Asset::query()
            ->active()
            ->where('usable_in_marketing', true)
            ->with('category')
            ->latest('created_at')
            ->limit(4)
            ->get();

        $courses = Course::query()
            ->published()
            ->latest('created_at')
            ->limit(3)
            ->get();

        $totalResellers = $agent ? $agent->resellers()->count() : 0;

        $totalMarketingAssets = Asset::query()
            ->active()
            ->where('usable_in_marketing', true)
            ->count();

        $totalCourses = Course::query()->published()->count();

        return view('agent.order.dashboard', [
            'customer' => $customer,
            'agent' => $agent,
            'agentCode' => $agent?->code ?? '-',
            'branchLabel' => $ctx->branchDisplayLabel(),
            'shippingAddress' => $this->resolveShippingAddress(),
            'stats' => [
                'active_orders' => $activeOrdersCount,
                'orders_this_month' => $ordersThisMonth,
                'active_resellers' => $activeResellers,
            ],
            'activeOrders' => $activeOrders,
            'lastOrder' => $lastOrder,
            'resellers' => $resellers,
            'marketingAssets' => $marketingAssets,
            'courses' => $courses,
            'totalActiveOrders' => $activeOrdersCount,
            'totalResellers' => $totalResellers,
            'totalMarketingAssets' => $totalMarketingAssets,
            'totalCourses' => $totalCourses,
        ]);
    }

    public function resellers(Request $request): View
    {
        $customer = $this->context()->customer();
        $agent = $customer->agent;

        $query = $agent
            ? $agent->resellers()->getQuery()
            : Reseller::query()->whereRaw('1 = 0');

        $status = $request->get('status');
        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        } else {
            $status = null;
        }

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%"));
        }

        $resellers = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('agent.order.resellers', [
            'resellers' => $resellers,
            'activeStatus' => $status ?? 'all',
            'search' => $search,
        ]);
    }

    public function training(): View
    {
        $courses = Course::published()
            ->with('category')
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->get();

        return view('agent.order.training.index', ['courses' => $courses]);
    }

    public function trainingShow(string $courseId): View
    {
        $course = Course::published()
            ->with([
                'category',
                'modules' => fn ($q) => $q->orderBy('sort_order'),
                'modules.materials' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->findOrFail($courseId);

        return view('agent.order.training.show', ['course' => $course]);
    }

    public function materials(Request $request): View
    {
        $query = Asset::query()
            ->active()
            ->where('usable_in_marketing', true)
            ->with('category')
            ->latest('created_at');

        $categoryId = $request->get('category_id');
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $type = $request->get('type');
        if (in_array($type, ['image', 'pdf', 'video', 'text'], true)) {
            $query->where('type', $type);
        } else {
            $type = null;
        }

        $assets = $query->paginate(24)->withQueryString();

        $categories = Category::whereHas('assets', fn ($q) => $q->active()->where('usable_in_marketing', true))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('agent.order.materials', [
            'assets' => $assets,
            'categories' => $categories,
            'activeCategoryId' => $categoryId,
            'activeType' => $type,
        ]);
    }

    public function reorder(string $order): RedirectResponse
    {
        $customer = $this->context()->customer();

        $order = SalesOrder::with('items')
            ->where('order_type', self::ORDER_TYPE)
            ->where('customer_id', $customer->id)
            ->findOrFail($order);

        $cart = $this->cart();
        $cart->clear();

        $skipped = 0;
        foreach ($order->items as $item) {
            if (! $item->product_variant_id) {
                $skipped++;
                continue;
            }
            try {
                $cart->add($item->product_variant_id, (float) $item->quantity);
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        if ($cart->count() < 1) {
            return redirect()->route('agent-order.index')
                ->with('error', 'Item pada pesanan tersebut sudah tidak tersedia.');
        }

        $redirect = redirect()->route('agent-order.checkout');
        if ($skipped > 0) {
            $redirect->with('warning', 'Sebagian item tidak tersedia lagi dan dilewati.');
        }

        return $redirect;
    }

    public function index(Request $request): View|RedirectResponse
    {
        $ctx = $this->context();

        try {
            $ctx->assertReady();
        } catch (\Throwable $e) {
            return view('agent.order.unavailable', [
                'message' => $e->getMessage(),
            ]);
        }

        $branchId = $ctx->branchId();
        $priceListId = $ctx->priceListId();
        $search = trim((string) $request->get('q', ''));
        $categoryId = $request->get('category_id');
        $promoOnly = $request->boolean('promo');

        $productsQuery = Product::with('nature')
            ->withCount('variants')
            ->saleItems()
            ->whereNull('deleted_at')
            ->where('branch_id', $branchId)
            ->whereHas('nature', fn ($q) => $q->where('code', 'FINISHED_GOOD'))
            ->orderBy('name');

        if ($categoryId) {
            $productsQuery->where('category_id', $categoryId);
        }

        if ($search !== '') {
            $productsQuery->where(function ($q) use ($search) {
                $q->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('code', 'ilike', '%'.$search.'%');
            });
        }

        $categoryIds = Product::query()->saleItems()->whereNull('deleted_at')
            ->where('branch_id', $branchId)
            ->whereHas('nature', fn ($q) => $q->where('code', 'FINISHED_GOOD'))
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id');

        $categories = ProductCategory::whereIn('id', $categoryIds)->orderBy('name')->get(['id', 'name']);

        $promoProductIds = Promotion::activeNow()
            ->whereNotNull('buy_product_id')
            ->pluck('buy_product_id')
            ->unique()
            ->all();

        $products = $productsQuery->get()->map(function (Product $product) use ($branchId, $priceListId) {
            $minPrice = $this->minVariantPrice($product->id, $branchId, $priceListId);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'image' => $product->image,
                'nature' => $product->nature?->name,
                'variants_count' => $product->variants_count,
                'min_price' => $minPrice,
                'has_price' => $minPrice > 0,
            ];
        })->filter(fn ($p) => $p['has_price'])->values();

        $products = $products->map(function ($p) use ($promoProductIds) {
            $p['is_promo'] = in_array($p['id'], $promoProductIds, true);

            return $p;
        });

        $promoProducts = $products->where('is_promo', true)->values();

        if ($promoOnly) {
            $products = $promoProducts;
        }

        return view('agent.order.index', [
            'customer' => $ctx->customer(),
            'branch' => $ctx->branch(),
            'products' => $products,
            'search' => $search,
            'categories' => $categories,
            'activeCategoryId' => $categoryId,
            'promoProducts' => $promoProducts,
            'promoOnly' => $promoOnly,
            'cart' => $this->cart()->get(),
            'summary' => $this->cart()->summarize(),
        ]);
    }

    public function productVariants(Request $request): JsonResponse
    {
        $ctx = $this->context();
        $ctx->assertReady();

        $request->validate(['product_id' => 'required|uuid']);

        $branchId = $ctx->branchId();
        $priceListId = $ctx->priceListId();

        $product = Product::with('defaultUnit')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->saleItems()
            ->whereHas('nature', fn ($q) => $q->where('code', 'FINISHED_GOOD'))
            ->findOrFail($request->product_id);

        $defaultUnitId = $product->default_unit_id;
        $unitLabel = $product->defaultUnit?->symbol
            ?: ($product->defaultUnit?->name ?: $product->defaultUnit?->code);

        $variants = ProductVariant::where('product_id', $product->id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->get();

        $result = [];
        $warehouseId = optional(WmsContext::defaultWarehouse($branchId))->id;
        foreach ($variants as $v) {
            $unitId = $defaultUnitId ?? ProductVariantPrice::where('variant_id', $v->id)
                ->where('branch_id', $branchId)
                ->where('price_list_id', $priceListId)
                ->whereNull('deleted_at')
                ->value('unit_id');

            if (! $unitId) {
                continue;
            }

            $priceRow = ProductVariantPrice::where('variant_id', $v->id)
                ->where('branch_id', $branchId)
                ->where('price_list_id', $priceListId)
                ->where('unit_id', $unitId)
                ->whereNull('deleted_at')
                ->first();

            $sellingPrice = (float) ($priceRow?->selling_price ?? 0);
            if ($sellingPrice <= 0) {
                continue;
            }

            $stockRow = ProductVariantStock::where('product_variant_id', $v->id)
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId), fn ($q) => $q->where('branch_id', $branchId))
                ->where('unit_id', $unitId)
                ->whereNull('deleted_at')
                ->first();

            $stock = (int) ($stockRow?->quantity ?? 0);
            if ($product->is_stock_item && $stock < 1) {
                continue;
            }

            $result[] = [
                'id' => $v->id,
                'sku' => $v->sku,
                'display_name' => $v->display_name ?: $v->sku,
                'image' => $v->image ?? $product->image,
                'selling_price' => $sellingPrice,
                'stock' => $stock,
                'unit_id' => $unitId,
                'unit_label' => $unitLabel,
                'is_stock_item' => (bool) $product->is_stock_item,
            ];
        }

        return response()->json([
            'success' => true,
            'product' => ['id' => $product->id, 'name' => $product->name],
            'variants' => $result,
        ]);
    }

    public function cartAdd(Request $request): JsonResponse
    {
        $request->validate([
            'variant_id' => 'required|uuid',
            'quantity' => 'nullable|numeric|min:0.000001',
        ]);

        try {
            $cart = $this->cart()->add(
                $request->variant_id,
                (float) ($request->quantity ?? 1)
            );
            $summary = $this->cart()->summarize();

            return response()->json([
                'success' => true,
                'message' => 'Ditambahkan ke keranjang',
                'cart_count' => count($cart['items']),
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function cartUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'cart_key' => 'required|string',
            'quantity' => 'required|numeric|min:0',
        ]);

        try {
            $cart = $this->cart()->update($request->cart_key, (float) $request->quantity);
            $summary = $this->cart()->summarize();

            return response()->json([
                'success' => true,
                'cart' => $cart,
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function cartRemove(Request $request): JsonResponse
    {
        $request->validate(['cart_key' => 'required|string']);

        $cart = $this->cart()->remove($request->cart_key);
        $summary = $this->cart()->summarize();

        return response()->json([
            'success' => true,
            'cart' => $cart,
            'summary' => $summary,
        ]);
    }

    public function checkout(): View|RedirectResponse
    {
        $cart = $this->cart();
        if ($cart->count() < 1) {
            return redirect()->route('agent-order.index')->with('error', 'Keranjang masih kosong.');
        }

        $ctx = $this->context();
        $branchId = $ctx->branchId();

        $methodPayments = MethodPayment::whereNull('deleted_at')
            ->where('is_active', true)
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->get();

        $cashMethods = $methodPayments->filter(fn ($m) => $this->isCashPaymentMethod($m))->values();
        $manualTransferMethod = $methodPayments->first(fn ($m) => $this->isManualTransferMethod($m));

        $customer = $ctx->customer();
        $agent = $customer->agent;
        $fgWarehouse = WmsContext::finishedGoodsWarehouse();
        $originUnit = $ctx->branch();

        $originCityId = $fgWarehouse?->city_id;
        $destCityId = $agent?->city_id;
        $weightKg = $this->shippingEstimator()->cartTotalWeightKg($cart);

        $shippingOptions = [];
        if ($originCityId && $destCityId) {
            $rates = ShippingRate::query()
                ->where('origin_city_id', $originCityId)
                ->where('destination_city_id', $destCityId)
                ->where('is_active', true)
                ->orderBy('courier_code')
                ->orderBy('service_code')
                ->get();

            foreach ($rates as $rate) {
                $shippingOptions[] = [
                    'rate_id' => $rate->id,
                    'courier_code' => $rate->courier_code,
                    'courier_label' => ShippingRate::COURIERS[$rate->courier_code] ?? strtoupper($rate->courier_code),
                    'service_code' => $rate->service_code,
                    'service_name' => $rate->service_name,
                    'amount' => $rate->estimateForWeightKg($weightKg),
                    'etd' => $this->shippingEstimator()->formatShippingEtd($rate),
                ];
            }
        }

        $shippingAvailable = $originCityId && $destCityId && count($shippingOptions) > 0;

        $shipping = [
            'origin_city' => $fgWarehouse?->city ?: $originUnit?->city,
            'origin_province' => $fgWarehouse?->province ?: $originUnit?->province,
            'origin_name' => $fgWarehouse?->name ?: ($originUnit?->brand_name ?: $originUnit?->name),
            'destination_city' => $agent?->city,
            'destination_province' => $agent?->province,
            'destination_name' => $agent?->name ?: $customer->name,
            'address' => $this->resolveShippingAddress(),
            'amount' => 0,
        ];

        return view('agent.order.checkout', [
            'customer' => $customer,
            'cart' => $cart->get(),
            'summary' => $cart->summarize(),
            'shipping' => $shipping,
            'shippingOptions' => $shippingOptions,
            'shippingAvailable' => $shippingAvailable,
            'weightKg' => $weightKg,
            'codMethod' => null,
            'codIcon' => null,
            'xenditChannelGroups' => [],
            'standardMethods' => $cashMethods,
            'manualTransferMethod' => $manualTransferMethod,
            'hasPaymentOptions' => $cashMethods->isNotEmpty() || $manualTransferMethod !== null,
        ]);
    }

    public function checkoutProcess(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'payment_method_id' => 'required|uuid',
            'shipping_rate_id' => ['required', 'uuid', 'exists:master_data.shipping_rates,id'],
            'xendit_channel' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $cartService = $this->cart();
        if ($cartService->count() < 1) {
            return redirect()->route('agent-order.index')->with('error', 'Keranjang kosong.');
        }

        $customer = $this->context()->customer();
        $agent = $customer->agent;
        $originCityId = optional(WmsContext::finishedGoodsWarehouse())->city_id;
        $destCityId = $agent?->city_id;

        if (! $originCityId || ! $destCityId) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Ongkir belum tersedia untuk kota tujuan Anda. Hubungi admin untuk menambahkan tarif.');
        }

        $rate = ShippingRate::query()
            ->where('id', $request->shipping_rate_id)
            ->where('is_active', true)
            ->first();

        if (! $rate || $rate->origin_city_id !== $originCityId || $rate->destination_city_id !== $destCityId) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Ongkir tidak valid. Silakan pilih kurir lagi.');
        }

        $shippingAmount = $rate->estimateForWeightKg($this->shippingEstimator()->cartTotalWeightKg($cartService));
        $shippingMeta = [
            'courier' => $rate->courier_code,
            'service' => $rate->service_code,
            'rate_id' => $rate->id,
            'etd' => $this->shippingEstimator()->formatShippingEtd($rate),
        ];

        $checkoutRequest = $cartService->toCheckoutRequest();
        if ($request->filled('notes')) {
            $checkoutRequest->merge(['notes' => $request->notes]);
        }

        $checkout = $this->checkoutService();
        $method = MethodPayment::findOrFail($request->payment_method_id);
        $xendit = app(XenditService::class);

        try {
            if ($xendit->usesXenditForMethod($method->code, $method)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Metode pembayaran online belum tersedia untuk order agent.');
            }

            if ($this->isCashPaymentMethod($method)) {
                $result = $checkout->processCod(
                    $checkoutRequest,
                    $request->payment_method_id,
                    self::ORDER_TYPE,
                    $this->resolveShippingAddress(),
                    $shippingAmount,
                    $shippingMeta,
                );
                $cartService->clear();

                return redirect()
                    ->route('agent-order.orders.show', $result['order_id'])
                    ->with('success', 'Pesanan berhasil dibuat. Nomor: '.$result['sales_number']);
            }

            if ($this->isManualTransferMethod($method)) {
                $result = $checkout->processCod(
                    $checkoutRequest,
                    $request->payment_method_id,
                    self::ORDER_TYPE,
                    $this->resolveShippingAddress(),
                    $shippingAmount,
                    $shippingMeta,
                );

                $order = SalesOrder::findOrFail($result['order_id']);
                DB::transaction(function () use ($order, $request) {
                    $seq = (int) SalesOrder::whereDate('created_at', now()->toDateString())
                        ->whereNotNull('unique_code')
                        ->lockForUpdate()
                        ->max('unique_code');
                    $seq = $seq + 1;
                    $order->update([
                        'unique_code' => $seq,
                        'payable_amount' => (float) $order->total + $seq,
                        'method_payment_id' => $request->payment_method_id,
                    ]);
                });

                $cartService->clear();

                return redirect()
                    ->route('agent-order.orders.show', $result['order_id'])
                    ->with('success', 'Pesanan berhasil dibuat. Transfer sesuai nominal unik di halaman detail.');
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Metode pembayaran tidak didukung.');
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function paymentReturn(Request $request, PaymentSyncService $paymentSync): RedirectResponse
    {
        $status = $request->query('status', 'success');
        $orderId = $request->query('order_id');
        $redirectStatus = $status;

        if ($orderId) {
            $order = SalesOrder::find($orderId);
            if ($order) {
                $this->checkoutService()->assertOrderOwnedByCustomer($order, self::ORDER_TYPE);

                if ($status === 'success') {
                    $paymentSync->syncOrderFromGateway($order, 'redirect_success', $request);
                    $order->refresh();
                    $redirectStatus = $order->payment_status === 'paid' ? 'success' : 'pending';
                } else {
                    $paymentSync->handleRedirectFailed($order, $request);
                    $order->refresh();
                    $redirectStatus = $order->payment_status === 'paid' ? 'success' : 'failed';
                }
            }
        }

        return redirect()->route('agent-order.orders.show', [
            'order' => $orderId,
            'payment' => $redirectStatus,
        ]);
    }

    public function paymentStatus(Request $request, string $orderId, PaymentSyncService $paymentSync): JsonResponse
    {
        $order = SalesOrder::findOrFail($orderId);
        $this->checkoutService()->assertOrderOwnedByCustomer($order, self::ORDER_TYPE);

        if ($order->payment_status !== 'paid' && $request->boolean('sync')) {
            $paymentSync->syncOrderFromGateway($order, 'poll_status', $request);
            $order->refresh();
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
            ],
        ]);
    }

    public function orders(Request $request): View
    {
        $customer = auth('customer')->user();

        $filterMap = [
            'pending' => ['status', 'pending'],
            'completed' => ['status', 'completed'],
            'unpaid' => ['payment_status', 'unpaid'],
            'cancelled' => ['status', 'cancelled'],
        ];
        $activeFilter = $request->get('filter');
        $activeFilter = array_key_exists($activeFilter, $filterMap) ? $activeFilter : 'all';

        $query = SalesOrder::query()
            ->where('order_type', self::ORDER_TYPE)
            ->where('customer_id', $customer->id)
            ->withCount('items')
            ->with(['items' => fn ($q) => $q->with('product')->limit(1), 'methodPayment', 'payments.methodPayment'])
            ->orderByDesc('created_at');

        if ($activeFilter !== 'all') {
            [$col, $val] = $filterMap[$activeFilter];
            $query->where($col, $val);
        }

        $orders = $query->paginate(15);

        return view('agent.order.orders.index', [
            'customer' => $customer,
            'orders' => $orders,
            'activeFilter' => $activeFilter,
        ]);
    }

    public function orderShow(string $orderId, Request $request): View
    {
        $order = SalesOrder::with(['items.product', 'items.variant', 'items.unit', 'payments.methodPayment'])
            ->findOrFail($orderId);

        $this->checkoutService()->assertOrderOwnedByCustomer($order, self::ORDER_TYPE);

        return view('agent.order.orders.show', [
            'customer' => auth('customer')->user(),
            'order' => $order,
            'paymentBanner' => $request->query('payment'),
        ]);
    }

    public function receiveOrder(string $order): RedirectResponse
    {
        $order = SalesOrder::with('items')->findOrFail($order);
        $this->checkoutService()->assertOrderOwnedByCustomer($order, self::ORDER_TYPE);

        if ($order->received_at || $order->status === 'completed') {
            return back()->with('error', 'Order sudah diterima.');
        }

        if ($order->status !== 'shipped') {
            return back()->with('error', 'Order belum dikirim, belum bisa diterima.');
        }

        $agent = $this->context()->customer()->agent;
        abort_unless($agent, 403, 'Akun bukan agent.');

        $sourceWh = WmsContext::finishedGoodsWarehouse($order->company_id);
        $agentWh = WmsContext::defaultAgentWarehouse($agent->id) ?: $agent->defaultWarehouse;
        abort_unless($agentWh, 422, 'Gudang agen belum diset.');

        $actorId = auth('customer')->id();

        try {
            DB::transaction(function () use ($order, $sourceWh, $agentWh, $actorId) {
                $order = SalesOrder::lockForUpdate()->with('items')->findOrFail($order->id);

                if ($order->received_at || $order->status === 'completed') {
                    throw new \RuntimeException('Order sudah diterima.');
                }

                if ($order->status !== 'shipped') {
                    throw new \RuntimeException('Order belum dikirim, belum bisa diterima.');
                }

                $sourceWhId = $sourceWh?->id;
                $srcBranch = $sourceWh?->branch_id ?: ($order->branch_id ?: $sourceWhId);
                $agentBranchId = $agentWh->branch_id ?: ($order->branch_id ?: $agentWh->company_id);
                $inboundCompanyId = $agentWh->company_id ?: $order->company_id;

                foreach ($order->items as $item) {
                    $product = Product::find($item->product_id);
                    if (! $product?->is_stock_item) {
                        continue;
                    }

                    $outboundWarehouseId = $item->source_warehouse_id ?: ($sourceWhId ?: $order->warehouse_id);
                    abort_unless($outboundWarehouseId && $srcBranch, 422, 'Gudang asal pengiriman belum diset.');

                    $outbound = StockMutationService::outbound(
                        $item->product_id,
                        $item->product_variant_id,
                        $order->company_id,
                        (string) $srcBranch,
                        $item->unit_id,
                        (float) $item->quantity,
                        SalesOrder::class,
                        $order->id,
                        $actorId,
                        'Kirim ke Agen - '.$order->sales_number,
                        $outboundWarehouseId
                    );

                    StockMutationService::inbound(
                        $item->product_id,
                        $item->product_variant_id,
                        $inboundCompanyId,
                        (string) $agentBranchId,
                        $item->unit_id,
                        (float) $item->quantity,
                        (float) $item->unit_price,
                        SalesOrder::class,
                        $order->id,
                        $actorId,
                        'Terima di gudang Agen - '.$order->sales_number,
                        null,
                        $outbound['earliest_expiry'] ?? null,
                        $agentWh->id
                    );
                }

                $order->update([
                    'status' => 'completed',
                    'received_at' => now(),
                    'fulfilled_at' => $order->fulfilled_at ?: now(),
                    'updated_by' => $actorId,
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Barang diterima. Stok masuk ke gudang Anda.');
    }

    public function stock(Request $request): View
    {
        $agent = $this->context()->customer()->agent;
        $warehouseId = $agent?->default_warehouse_id;

        $query = ProductVariantStock::query()
            ->where('warehouse_id', $warehouseId)
            ->with(['variant.product.defaultUnit', 'variant.variantAttributes.attributeValue'])
            ->when(! $warehouseId, fn ($q) => $q->whereRaw('1 = 0'));

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $query->whereHas('variant', function ($v) use ($search) {
                $v->where('sku', 'ilike', "%{$search}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'ilike', "%{$search}%"));
            });
        }

        $stocks = $query->orderByDesc('quantity')->paginate(30)->withQueryString();

        return view('agent.order.stock', [
            'stocks' => $stocks,
            'search' => $search,
            'warehouseName' => optional($agent?->defaultWarehouse)->name,
            'lowThreshold' => 5,
        ]);
    }

    protected function loadOrderForPrint(string $orderId): SalesOrder
    {
        $order = SalesOrder::with([
            'items.product',
            'items.variant.variantAttributes.attributeValue',
            'items.unit',
            'payments.methodPayment',
            'methodPayment',
            'customer.agent',
        ])->findOrFail($orderId);

        $this->checkoutService()->assertOrderOwnedByCustomer($order, self::ORDER_TYPE);

        return $order;
    }

    protected function documentCompany(SalesOrder $order): ?BusinessUnit
    {
        return $order->company_id
            ? (BusinessUnit::find($order->company_id) ?: WmsContext::distributor())
            : WmsContext::distributor();
    }

    public function orderPoPdf(string $order)
    {
        $order = $this->loadOrderForPrint($order);

        $pdf = Pdf::loadView('agent.order.pdf.po', [
            'order' => $order,
            'company' => $this->documentCompany($order),
            'agent' => $order->customer?->agent,
        ])->setPaper('a4', 'portrait');

        $filename = 'PO-'.preg_replace('/[^A-Za-z0-9\-_]/', '_', $order->sales_number).'.pdf';

        return $pdf->stream($filename);
    }

    public function orderInvoicePdf(string $order)
    {
        $order = $this->loadOrderForPrint($order);

        $pdf = Pdf::loadView('agent.order.pdf.invoice', [
            'order' => $order,
            'company' => $this->documentCompany($order),
            'agent' => $order->customer?->agent,
        ])->setPaper('a4', 'portrait');

        $filename = 'INV-'.preg_replace('/[^A-Za-z0-9\-_]/', '_', $order->sales_number).'.pdf';

        return $pdf->stream($filename);
    }

    protected function minVariantPrice(string $productId, string $branchId, string $priceListId): float
    {
        return (float) ProductVariantPrice::query()
            ->whereHas('variant', fn ($q) => $q->where('product_id', $productId)->whereNull('deleted_at'))
            ->where('branch_id', $branchId)
            ->where('price_list_id', $priceListId)
            ->whereNull('deleted_at')
            ->min('selling_price');
    }

    public function uploadPaymentProof(Request $request, string $order): RedirectResponse
    {
        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $order = SalesOrder::findOrFail($order);
        $this->checkoutService()->assertOrderOwnedByCustomer($order, self::ORDER_TYPE);
        abort_if($order->payment_status === 'paid', 422, 'Order sudah lunas.');

        $path = $request->file('proof')->store('web-order-proofs', 'public');
        $order->update([
            'payment_proof_path' => $path,
            'payment_proof_uploaded_at' => now(),
            'payment_status' => 'pending_verification',
        ]);

        return back()->with('success', 'Bukti transfer terkirim. Menunggu verifikasi admin.');
    }

    protected function isCashPaymentMethod(MethodPayment $method): bool
    {
        return ! $method->uses_payment_gateway
            && in_array(strtoupper($method->code), ['CASH', 'TUNAI'], true);
    }

    protected function isManualTransferMethod(MethodPayment $method): bool
    {
        return strtoupper($method->code) === 'MANUAL_TRANSFER';
    }
}
