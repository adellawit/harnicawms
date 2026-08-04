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
use App\Services\Shop\ShopContextService;
use App\Services\Xendit\PaymentSyncService;
use App\Services\Xendit\XenditService;
use App\Support\WmsContext;
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
            ->productType()
            ->when($companyId, fn ($q) => $q->where(function ($qq) use ($companyId) {
                $qq->whereNull('company_id')->orWhere('company_id', $companyId);
            }))
            ->with(['buyProduct:id,name', 'getProduct:id,name'])
            ->orderByDesc('priority')
            ->orderBy('code')
            ->limit(6)
            ->get()
            ->map(function (Promotion $promotion) {
                $labelParts = [];
                if ($promotion->buy_min_qty > 0) {
                    $labelParts[] = 'Beli '.$this->formatPromoQty($promotion->buy_min_qty);
                }
                if ($promotion->get_qty > 0) {
                    $labelParts[] = 'gratis '.$this->formatPromoQty($promotion->get_qty);
                }

                return [
                    'name' => $promotion->name,
                    'label' => trim(implode(' ', $labelParts)),
                    'product' => $promotion->buyProduct?->name,
                ];
            });

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
        ]);
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
            'results' => $rows->map(fn (Reseller $r) => [
                'id' => $r->customer_id,
                'text' => trim(($r->name ?: $r->customer?->name).($r->customer?->code ? ' · '.$r->customer->code : '')),
                'own' => $r->agent_id === $agent->id,
            ])->values(),
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

            $result[] = array_merge($mapped, [
                'barcode' => $variant->barcode,
                'image' => $variant->image ?? null,
                'product_id' => $product->id,
            ]);
        }

        return response()->json(['variants' => $result]);
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

        $request->validate([
            'price_list_id' => 'required|uuid',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|uuid',
            'items.*.unit_id' => 'required|uuid',
            'items.*.quantity' => 'required|numeric|min:0.000001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:percent,nominal',
            'items.*.discount_value' => 'nullable|numeric|min:0',
            'payment_method_id' => 'required|uuid',
            'customer_id' => 'nullable|uuid',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'tax_enabled' => 'required|boolean',
            'discount_type' => 'nullable|in:percent,nominal',
            'discount_value' => 'nullable|numeric|min:0',
            'amount_paid' => 'required|numeric|min:0',
            'xendit_channel' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

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
                'agent-pos',
                $this->agentWarehouseId(),
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
                'agent-pos',
                $this->agentWarehouseId(),
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
}
