<?php

namespace App\Services\Shop;

use App\Models\MethodPayment;
use App\Models\SalesOrder;
use App\Models\SalesOrderPayment;
use App\Services\PosCheckoutService;
use App\Services\Xendit\PaymentSyncService;
use App\Services\Xendit\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopCheckoutService
{
    public function __construct(
        protected ShopContextService $context,
        protected PosCheckoutService $checkout,
        protected XenditService $xendit,
        protected PaymentSyncService $paymentSync,
    ) {}

    public function generateSalesNumber(string $branchId): string
    {
        $prefix = (string) config('shop.sales_number_prefix', 'WEB');
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
    public function processXendit(Request $checkoutRequest, string $paymentMethodId, ?string $xenditChannel = null): array
    {
        $this->context->assertReady();
        $branchId = $this->context->branchId();
        $companyId = $this->context->companyId();
        $customer = $this->context->customer();

        $methodPayment = MethodPayment::where('id', $paymentMethodId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->firstOrFail();

        if (! $this->xendit->usesXenditForMethod($methodPayment->code, $methodPayment)) {
            throw new \InvalidArgumentException('Metode pembayaran tidak didukung.');
        }

        DB::beginTransaction();
        try {
            $totals = $this->checkout->buildCartTotals($checkoutRequest);
            $total = $totals['total'];

            if ($total <= 0) {
                throw new \InvalidArgumentException('Total pesanan tidak valid.');
            }

            $salesNumber = $this->generateSalesNumber($branchId);
            $order = $this->checkout->createSalesOrder(
                $checkoutRequest,
                $totals,
                $salesNumber,
                $branchId,
                $companyId,
                null,
                'pending',
                'unpaid',
                'web',
            );

            $order->update([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_contact' => $customer->phone ?? $customer->mobile,
                'customer_address' => $customer->address,
            ]);

            $payment = SalesOrderPayment::create([
                'sales_order_id' => $order->id,
                'method_payment_id' => $paymentMethodId,
                'payment_code' => 'PAY-'.$salesNumber,
                'gateway' => 'xendit',
                'amount' => $total,
                'change_amount' => 0,
                'status' => 'pending',
            ]);

            $xenditChannel = $xenditChannel ?: $methodPayment->gateway_channel_code;
            $externalId = $this->paymentSync->externalIdForOrder($order);

            $invoicePayload = [
                'external_id' => $externalId,
                'amount' => (int) round($total),
                'description' => 'Web Order '.$salesNumber.' - '.$customer->name,
                'invoice_duration' => config('xendit.invoice_duration', 900),
                'currency' => 'IDR',
                'success_redirect_url' => route('customer.payment.return', [
                    'status' => 'success',
                    'order_id' => $order->id,
                ]),
                'failure_redirect_url' => route('customer.payment.return', [
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
                $payment->update(['notes' => 'xendit_channel:'.strtoupper((string) $xenditChannel)]);
            }

            $invoice = $this->xendit->createInvoice($invoicePayload);
            $invoiceUrl = $invoice['invoice_url'] ?? null;
            $invoiceId = $invoice['id'] ?? null;

            if (! $invoiceUrl) {
                throw new \RuntimeException('Xendit tidak mengembalikan invoice_url.');
            }

            $payment->update([
                'gateway_reference' => $invoiceId,
                'gateway_url' => $invoiceUrl,
            ]);

            DB::commit();

            return [
                'success' => true,
                'order_id' => $order->id,
                'sales_number' => $order->sales_number,
                'total' => (float) $order->total,
                'invoice_url' => $invoiceUrl,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Shop Xendit checkout failed', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function processCod(Request $checkoutRequest, string $paymentMethodId): array
    {
        $this->context->assertReady();
        $branchId = $this->context->branchId();
        $companyId = $this->context->companyId();
        $customer = $this->context->customer();

        DB::beginTransaction();
        try {
            $totals = $this->checkout->buildCartTotals($checkoutRequest);
            $total = $totals['total'];
            $salesNumber = $this->generateSalesNumber($branchId);

            $order = $this->checkout->createSalesOrder(
                $checkoutRequest,
                $totals,
                $salesNumber,
                $branchId,
                $companyId,
                null,
                'pending',
                'unpaid',
                'web',
            );

            $order->update([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_contact' => $customer->phone ?? $customer->mobile,
                'customer_address' => $customer->address,
            ]);

            SalesOrderPayment::create([
                'sales_order_id' => $order->id,
                'method_payment_id' => $paymentMethodId,
                'payment_code' => 'PAY-'.$salesNumber,
                'amount' => $total,
                'change_amount' => 0,
                'status' => 'pending',
            ]);

            DB::commit();

            return [
                'success' => true,
                'order_id' => $order->id,
                'sales_number' => $order->sales_number,
                'total' => (float) $order->total,
                'cod' => true,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function assertOrderOwnedByCustomer(SalesOrder $order): void
    {
        $customer = $this->context->customer();

        if ($order->order_type !== 'web' || $order->customer_id !== $customer->id) {
            abort(404);
        }
    }

    public function cancelOrder(SalesOrder $order): void
    {
        $this->assertOrderOwnedByCustomer($order);

        if (! $order->isCancellableByCustomer()) {
            throw new \InvalidArgumentException('Pesanan ini tidak dapat dibatalkan.');
        }

        DB::transaction(function () use ($order) {
            $locked = SalesOrder::lockForUpdate()->findOrFail($order->id);

            if (! $locked->isCancellableByCustomer()) {
                throw new \InvalidArgumentException('Pesanan ini tidak dapat dibatalkan.');
            }

            $locked->update([
                'status' => 'cancelled',
            ]);

            SalesOrderPayment::where('sales_order_id', $locked->id)
                ->where('status', 'pending')
                ->update(['status' => 'failed']);
        });
    }
}
