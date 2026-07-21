<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\MethodPayment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantStock;
use App\Models\SalesOrder;
use App\Services\Shop\ShopCartService;
use App\Services\Shop\ShopCheckoutService;
use App\Services\Shop\ShopContextService;
use App\Services\Xendit\PaymentSyncService;
use App\Services\Xendit\XenditService;
use App\Support\WmsContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $productsQuery = Product::with('nature')
            ->withCount('variants')
            ->saleItems()
            ->whereNull('deleted_at')
            ->where('branch_id', $branchId)
            ->whereHas('nature', fn ($q) => $q->where('code', 'FINISHED_GOOD'))
            ->orderBy('name');

        if ($search !== '') {
            $productsQuery->where(function ($q) use ($search) {
                $q->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('code', 'ilike', '%'.$search.'%');
            });
        }

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

        return view('agent.order.index', [
            'customer' => $ctx->customer(),
            'branch' => $ctx->branch(),
            'products' => $products,
            'search' => $search,
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

        $product = Product::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->saleItems()
            ->whereHas('nature', fn ($q) => $q->where('code', 'FINISHED_GOOD'))
            ->findOrFail($request->product_id);

        $defaultUnitId = $product->default_unit_id;

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

        $xendit = app(XenditService::class);
        $pgMethods = $methodPayments->filter(fn ($m) => $xendit->usesXenditForMethod($m->code, $m));
        $standardMethods = $methodPayments->filter(fn ($m) => ! $m->uses_payment_gateway);
        $codMethod = $methodPayments->first(fn ($m) => strtoupper($m->code) === 'COD');
        $xenditChannelGroups = $xendit->isPaymentGatewayReady()
            ? $xendit->buildPaymentChannelGroups($pgMethods)
            : [];

        $codIcon = $codMethod
            ? $xendit->channelIconUrl($codMethod->code, $codMethod->name)
            : null;

        return view('agent.order.checkout', [
            'customer' => $ctx->customer(),
            'cart' => $cart->get(),
            'summary' => $cart->summarize(),
            'codMethod' => $codMethod,
            'codIcon' => $codIcon,
            'xenditChannelGroups' => $xenditChannelGroups,
            'standardMethods' => $standardMethods,
            'hasPaymentOptions' => $codMethod !== null
                || count($xenditChannelGroups) > 0
                || $standardMethods->isNotEmpty(),
        ]);
    }

    public function checkoutProcess(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'payment_method_id' => 'required|uuid',
            'xendit_channel' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $cartService = $this->cart();
        if ($cartService->count() < 1) {
            return response()->json(['success' => false, 'message' => 'Keranjang kosong'], 422);
        }

        $checkoutRequest = $cartService->toCheckoutRequest();
        if ($request->filled('notes')) {
            $checkoutRequest->merge(['notes' => $request->notes]);
        }

        $checkout = $this->checkoutService();
        $method = MethodPayment::findOrFail($request->payment_method_id);
        $xendit = app(XenditService::class);

        try {
            if ($xendit->usesXenditForMethod($method->code, $method)) {
                if (! $xendit->isConfigured()) {
                    return response()->json(['success' => false, 'message' => 'Payment gateway belum dikonfigurasi'], 422);
                }

                $result = $checkout->processXendit(
                    $checkoutRequest,
                    $request->payment_method_id,
                    $request->xendit_channel,
                    self::ORDER_TYPE,
                );
                $cartService->clear();

                if ($request->expectsJson()) {
                    return response()->json(['success' => true, 'redirect' => $result['invoice_url'], 'data' => $result]);
                }

                return redirect()->away($result['invoice_url']);
            }

            if (strtoupper($method->code) === 'COD') {
                $result = $checkout->processCod($checkoutRequest, $request->payment_method_id, self::ORDER_TYPE);
                $cartService->clear();

                return redirect()
                    ->route('agent-order.orders.show', $result['order_id'])
                    ->with('success', 'Pesanan COD berhasil dibuat. Nomor: '.$result['sales_number']);
            }

            return response()->json(['success' => false, 'message' => 'Metode pembayaran tidak didukung'], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
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

    public function orders(): View
    {
        $customer = auth('customer')->user();

        $orders = SalesOrder::query()
            ->where('order_type', self::ORDER_TYPE)
            ->where('customer_id', $customer->id)
            ->with('payments.methodPayment')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('agent.order.orders.index', [
            'customer' => $customer,
            'orders' => $orders,
        ]);
    }

    public function orderShow(string $orderId, Request $request): View
    {
        $order = SalesOrder::with(['items.product', 'items.variant', 'payments.methodPayment'])
            ->findOrFail($orderId);

        $this->checkoutService()->assertOrderOwnedByCustomer($order, self::ORDER_TYPE);

        return view('agent.order.orders.show', [
            'customer' => auth('customer')->user(),
            'order' => $order,
            'paymentBanner' => $request->query('payment'),
        ]);
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
}
