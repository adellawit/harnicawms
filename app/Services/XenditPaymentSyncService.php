<?php

namespace App\Services;

use App\Models\PaymentGatewayCallback;
use App\Models\SalesOrder;
use App\Models\SalesOrderPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XenditPaymentSyncService
{
    public function __construct(
        protected XenditService $xendit,
        protected PosCheckoutService $checkout,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordCallback(
        string $source,
        array $payload,
        ?Request $request = null,
        ?SalesOrder $order = null,
        ?SalesOrderPayment $payment = null,
        ?string $processResult = 'pending',
        ?string $errorMessage = null,
    ): PaymentGatewayCallback {
        $externalId = (string) ($payload['external_id'] ?? '');
        $invoiceId = (string) ($payload['id'] ?? $payload['invoice_id'] ?? '');
        $status = strtoupper((string) ($payload['status'] ?? ''));

        if ($order === null && $externalId !== '') {
            $order = $this->resolveOrderFromExternalId($externalId);
        }

        if ($payment === null && $order !== null) {
            $payment = SalesOrderPayment::where('sales_order_id', $order->id)
                ->where('gateway', 'xendit')
                ->latest('created_at')
                ->first();
        }

        return PaymentGatewayCallback::create([
            'sales_order_id' => $order?->id,
            'sales_order_payment_id' => $payment?->id,
            'gateway' => 'xendit',
            'source' => $source,
            'external_id' => $externalId !== '' ? $externalId : null,
            'gateway_reference' => $invoiceId !== '' ? $invoiceId : null,
            'invoice_status' => $status !== '' ? $status : null,
            'process_result' => $processResult,
            'error_message' => $errorMessage,
            'payload' => $payload,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'processed_at' => $processResult !== 'pending' ? now() : null,
        ]);
    }

    /**
     * Proses webhook atau payload invoice dari API Xendit.
     *
     * @param  array<string, mixed>  $payload
     * @return array{result: string, message: string, order?: SalesOrder}
     */
    public function processInvoicePayload(
        array $payload,
        string $source,
        ?Request $request = null,
    ): array {
        $externalId = (string) ($payload['external_id'] ?? '');
        $status = strtoupper((string) ($payload['status'] ?? ''));

        if ($externalId === '' || ! $this->isSupportedExternalId($externalId)) {
            $this->recordCallback($source, $payload, $request, processResult: 'ignored', errorMessage: 'Unsupported external_id');

            return ['result' => 'ignored', 'message' => 'Ignored'];
        }

        $order = $this->resolveOrderFromExternalId($externalId);
        if (! $order) {
            $this->recordCallback($source, $payload, $request, processResult: 'error', errorMessage: 'Order not found');

            return ['result' => 'error', 'message' => 'Order not found'];
        }

        $payment = SalesOrderPayment::where('sales_order_id', $order->id)
            ->where('gateway', 'xendit')
            ->latest('created_at')
            ->first();

        $log = $this->recordCallback($source, $payload, $request, $order, $payment, 'pending');

        try {
            $outcome = $this->applyStatusToOrder($order, $payment, $status, (string) ($payload['id'] ?? ''));

            $log->update([
                'process_result' => $outcome['result'],
                'error_message' => $outcome['result'] === 'error' ? $outcome['message'] : null,
                'processed_at' => now(),
                'sales_order_id' => $order->id,
                'sales_order_payment_id' => $payment?->id,
            ]);

            return array_merge($outcome, ['order' => $order->fresh()]);
        } catch (\Throwable $e) {
            Log::error('Xendit payment process failed', [
                'order_id' => $order->id,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'process_result' => 'error',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            return ['result' => 'error', 'message' => $e->getMessage(), 'order' => $order];
        }
    }

    /**
     * Sinkron status order dari API Xendit (redirect / polling).
     *
     * @return array{result: string, message: string, order?: SalesOrder, invoice_status?: string}
     */
    public function syncOrderFromGateway(SalesOrder $order, string $source, ?Request $request = null): array
    {
        $payment = SalesOrderPayment::where('sales_order_id', $order->id)
            ->where('gateway', 'xendit')
            ->latest('created_at')
            ->first();

        if (! $payment) {
            $this->recordCallback($source, ['external_id' => 'pos-'.$order->id], $request, $order, null, 'error', 'No Xendit payment row');

            return ['result' => 'error', 'message' => 'No Xendit payment found'];
        }

        if ($order->payment_status === 'paid' && $order->status === 'completed') {
            $this->recordCallback($source, [
                'external_id' => 'pos-'.$order->id,
                'status' => 'PAID',
                'note' => 'already_completed',
            ], $request, $order, $payment, 'skipped', 'Order already paid');

            return [
                'result' => 'skipped',
                'message' => 'Already paid',
                'order' => $order,
                'invoice_status' => 'PAID',
            ];
        }

        $invoiceId = $payment->gateway_reference;
        if (! $invoiceId) {
            $this->recordCallback($source, ['external_id' => 'pos-'.$order->id], $request, $order, $payment, 'error', 'Missing gateway_reference');

            return ['result' => 'error', 'message' => 'Missing invoice reference'];
        }

        $invoice = $this->xendit->getInvoice($invoiceId);
        if ($invoice === null) {
            $this->recordCallback($source, [
                'external_id' => 'pos-'.$order->id,
                'id' => $invoiceId,
            ], $request, $order, $payment, 'error', 'Invoice not found on Xendit');

            return ['result' => 'error', 'message' => 'Invoice not found on Xendit'];
        }

        $outcome = $this->processInvoicePayload($invoice, $source, $request);
        $outcome['invoice_status'] = strtoupper((string) ($invoice['status'] ?? ''));

        return $outcome;
    }

    /**
     * @return array{result: string, message: string}
     */
    protected function applyStatusToOrder(
        SalesOrder $order,
        ?SalesOrderPayment $payment,
        string $status,
        string $invoiceId,
    ): array {
        if (in_array($status, ['PAID', 'SETTLED'], true)) {
            DB::transaction(function () use ($order, $payment, $invoiceId) {
                if ($payment && $invoiceId !== '') {
                    $payment->update([
                        'gateway_reference' => $invoiceId,
                        'status' => 'completed',
                    ]);
                }

                $this->checkout->completePaidOrder($order, $order->created_by);
            });

            Log::info('Xendit payment completed', [
                'order_id' => $order->id,
                'sales_number' => $order->sales_number,
                'invoice_status' => $status,
            ]);

            return ['result' => 'completed', 'message' => 'Payment completed'];
        }

        if (in_array($status, ['EXPIRED', 'FAILED'], true)) {
            DB::transaction(function () use ($order, $payment) {
                $order->update([
                    'status' => 'cancelled',
                    'payment_status' => 'unpaid',
                ]);

                if ($payment) {
                    $payment->update(['status' => 'failed']);
                }
            });

            Log::info('Xendit payment failed', [
                'order_id' => $order->id,
                'invoice_status' => $status,
            ]);

            return ['result' => 'failed', 'message' => 'Payment '.$status];
        }

        if (in_array($status, ['PENDING', ''], true)) {
            return ['result' => 'pending', 'message' => 'Payment still pending'];
        }

        return ['result' => 'ignored', 'message' => 'Unhandled status: '.$status];
    }

    /**
     * Redirect failed: sync API dulu; jika tetap pending, tandai gagal.
     *
     * @return array{result: string, message: string, order?: SalesOrder}
     */
    public function handleRedirectFailed(SalesOrder $order, ?Request $request = null): array
    {
        $sync = $this->syncOrderFromGateway($order, 'redirect_failed', $request);

        if (in_array($sync['result'], ['completed', 'skipped'], true)) {
            return $sync;
        }

        if ($sync['result'] === 'pending' || $sync['result'] === 'error') {
            $order = $order->fresh();
            if ($order && $order->payment_status !== 'paid') {
                DB::transaction(function () use ($order) {
                    $order->update([
                        'status' => 'cancelled',
                        'payment_status' => 'unpaid',
                    ]);

                    SalesOrderPayment::where('sales_order_id', $order->id)
                        ->where('gateway', 'xendit')
                        ->where('status', 'pending')
                        ->update(['status' => 'failed']);
                });

                $this->recordCallback('redirect_failed_marked', [
                    'external_id' => 'pos-'.$order->id,
                    'status' => 'FAILED',
                    'note' => 'marked_after_redirect_failed',
                ], $request, $order, null, 'failed', 'Marked failed after redirect');
            }

            return [
                'result' => 'failed',
                'message' => 'Payment marked as failed',
                'order' => $order->fresh(),
            ];
        }

        return $sync;
    }

    public function externalIdForOrder(SalesOrder $order): string
    {
        $prefix = $order->order_type === 'web' ? 'web' : 'pos';

        return $prefix.'-'.$order->id;
    }

    protected function isSupportedExternalId(string $externalId): bool
    {
        return str_starts_with($externalId, 'pos-') || str_starts_with($externalId, 'web-');
    }

    protected function resolveOrderFromExternalId(string $externalId): ?SalesOrder
    {
        foreach (['pos-', 'web-'] as $prefix) {
            if (str_starts_with($externalId, $prefix)) {
                return SalesOrder::find(substr($externalId, strlen($prefix)));
            }
        }

        return null;
    }
}
