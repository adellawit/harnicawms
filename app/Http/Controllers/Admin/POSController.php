<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MethodPayment;
use App\Models\Product;
use App\Models\ProductNature;
use App\Models\ProductPriceList;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderPayment;
use App\Services\PosCheckoutService;
use App\Services\Product\ProductSearchService;
use App\Support\WmsContext;
use App\Services\Xendit\PaymentSyncService;
use App\Services\Xendit\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class POSController extends Controller
{
    public function __construct(
        protected PosCheckoutService $checkout,
        protected ProductSearchService $productSearch,
        protected XenditService $xendit,
        protected PaymentSyncService $paymentSync,
    ) {}

    protected function getBranchId(): ?string
    {
        return auth('web')->user()?->current_business_unit_id;
    }

    protected function getCompanyId(): ?string
    {
        return auth('web')->user()?->getCompanyIdForProduct();
    }

    public function indexView(Request $request)
    {
        $branchId = $this->getBranchId();
        $warehouseId = optional(WmsContext::defaultWarehouse($branchId))->id;
        $companyId = $this->getCompanyId();

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

        // Customers (from customer.customers, filter by branch via customer_group)
        $customers = Customer::with('customerGroup')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->when($branchId, function ($q) use ($branchId) {
                $q->whereHas('customerGroup', fn ($cg) => $cg->where('branch_id', $branchId));
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'customer_group_id']);

        // Product parents (no price/stock on card - fetched on variant selection)
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
            'taxRate' => 11,
            'xenditEnabled' => $this->xendit->isPaymentGatewayReady(),
            'xenditMethodCodes' => $xenditMethodCodes,
            'xenditActiveChannels' => $xenditActiveChannels,
            'xenditSyncChannels' => config('xendit.sync_channels_from_api', true),
            'xenditChannelGroups' => $xenditChannelGroups,
            'nonXenditMethods' => $nonXenditMethods,
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
        ]);

        $branchId = $this->getBranchId();
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
                'image' => $variant->image ?? $product->image ?? null,
            ]);
        }

        return response()->json(['variants' => $result]);
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

        $branchId = $this->getBranchId();
        $companyId = $this->getCompanyId();
        $userId = auth('web')->id();

        if (! $branchId) {
            return response()->json(['success' => false, 'message' => 'Branch not selected'], 422);
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

            $changeAmount = max($amountPaid - $total, 0);

            SalesOrderPayment::create([
                'sales_order_id' => $order->id,
                'method_payment_id' => $request->payment_method_id,
                'payment_code' => 'PAY-' . $salesNumber,
                'amount' => $amountPaid,
                'change_amount' => $changeAmount,
                'status' => 'completed',
                'created_by' => $userId,
            ]);

            $this->checkout->completePaidOrder($order->fresh(), $userId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction completed successfully',
                'data' => [
                    'sales_number' => $order->sales_number,
                    'total' => (float) $order->total,
                    'amount_paid' => $amountPaid,
                    'change_amount' => $changeAmount,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('POS Payment failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Payment failed: ' . $e->getMessage(),
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

            $payment = SalesOrderPayment::create([
                'sales_order_id' => $order->id,
                'method_payment_id' => $request->payment_method_id,
                'payment_code' => 'PAY-' . $salesNumber,
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
                'external_id' => 'pos-' . $order->id,
                'amount' => (int) round($total),
                'description' => 'POS ' . $salesNumber . ' - ' . $channelLabel,
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
                    'notes' => 'xendit_channel:' . strtoupper((string) $xenditChannel),
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
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('POS Xendit payment failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Xendit payment failed: ' . $e->getMessage(),
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
}
