<?php

namespace App\Services\Telegram;

use App\Models\MethodPayment;
use App\Models\SalesOrder;
use App\Models\SalesOrderPayment;
use App\Models\User;
use App\Services\PosCheckoutService;
use App\Services\XenditPaymentSyncService;
use App\Services\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelegramCheckoutService
{
    public function __construct(
        protected PosCheckoutService $checkout,
        protected TelegramCustomerService $customers,
        protected XenditService $xendit,
        protected XenditPaymentSyncService $paymentSync,
    ) {}

    /**
     * @param  array<string, mixed>  $draft
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function submit(array $draft, User $user): array
    {
        $branchId = $draft['branch_id'] ?? $user->getBranchIdForTransaction();
        $companyId = $draft['company_id'] ?? $user->getCompanyIdForProduct();

        if ($branchId === null) {
            return [
                'success' => false,
                'message' => 'Cabang aktif tidak ditemukan.',
            ];
        }

        $items = $draft['items'] ?? [];

        if ($items === []) {
            return [
                'success' => false,
                'message' => 'Item transaksi kosong.',
            ];
        }

        $paymentMethodId = $draft['payment_method_id'] ?? null;

        if ($paymentMethodId === null) {
            return [
                'success' => false,
                'message' => 'Metode pembayaran belum dipilih.',
            ];
        }

        $methodPayment = MethodPayment::query()
            ->where('id', $paymentMethodId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if ($methodPayment === null) {
            return [
                'success' => false,
                'message' => 'Metode pembayaran tidak valid.',
            ];
        }

        $methodCode = strtoupper((string) $methodPayment->code);

        if (in_array($methodCode, ['DEBIT', 'CREDIT'], true)) {
            return [
                'success' => false,
                'message' => 'Pembayaran kartu debit/kredit belum tersedia via Telegram.',
            ];
        }

        $customerContext = $this->resolveCustomer($draft, $branchId, $companyId, $user);
        $draft = $customerContext['draft'];
        $customerId = $customerContext['customer_id'];
        $customerCreated = $customerContext['customer_created'];

        $request = $this->buildCheckoutRequest($draft, $customerId, $paymentMethodId);

        if ($this->xendit->usesXenditForMethod($methodPayment->code, $methodPayment)) {
            if (! $this->xendit->isConfigured()) {
                return [
                    'success' => false,
                    'message' => 'Payment gateway Xendit belum dikonfigurasi.',
                ];
            }

            return $this->submitXenditPayment(
                $request,
                $draft,
                $branchId,
                $companyId,
                $user,
                $methodPayment,
                $customerCreated,
            );
        }

        return $this->submitCashPayment(
            $request,
            $draft,
            $branchId,
            $companyId,
            $user,
            $methodPayment,
            $customerCreated,
        );
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array{draft: array<string, mixed>, customer_id: ?string, customer_created: bool}
     */
    protected function resolveCustomer(array $draft, string $branchId, ?string $companyId, User $user): array
    {
        $customerId = $draft['customer_id'] ?? null;
        $customerCreated = false;

        if ($customerId === null && filled($draft['customer_name'] ?? null)) {
            $resolved = $this->customers->resolveOrCreate(
                (string) $draft['customer_name'],
                $branchId,
                $companyId,
                $user
            );
            $customerId = $resolved['customer_id'] ?: null;
            $customerCreated = $resolved['created'];
            $draft['customer_name'] = $resolved['customer_name'];
        }

        return [
            'draft' => $draft,
            'customer_id' => $customerId,
            'customer_created' => $customerCreated,
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    protected function submitCashPayment(
        Request $request,
        array $draft,
        string $branchId,
        ?string $companyId,
        User $user,
        MethodPayment $methodPayment,
        bool $customerCreated,
    ): array {
        DB::beginTransaction();

        try {
            $totals = $this->checkout->buildCartTotals($request);
            $total = $totals['total'];

            if ($total <= 0) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'Total transaksi tidak valid.',
                ];
            }

            $salesNumber = $this->generateSalesNumber($branchId);
            $order = $this->checkout->createSalesOrder(
                $request,
                $totals,
                $salesNumber,
                $branchId,
                $companyId,
                $user->id,
                'pending',
                'unpaid',
                'telegram',
            );

            SalesOrderPayment::create([
                'sales_order_id' => $order->id,
                'method_payment_id' => $methodPayment->id,
                'payment_code' => 'PAY-'.$salesNumber,
                'amount' => $total,
                'change_amount' => 0,
                'status' => 'completed',
                'created_by' => $user->id,
            ]);

            $this->checkout->completePaidOrder($order->fresh(), $user->id);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Transaksi berhasil.',
                'data' => [
                    'sales_number' => $order->sales_number,
                    'total' => (float) $order->total,
                    'customer_name' => $order->customer_name,
                    'customer_created' => $customerCreated,
                    'payment_method' => $methodPayment->name,
                    'payment_status' => 'paid',
                ],
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Telegram cash checkout failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Checkout gagal: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    protected function submitXenditPayment(
        Request $request,
        array $draft,
        string $branchId,
        ?string $companyId,
        User $user,
        MethodPayment $methodPayment,
        bool $customerCreated,
    ): array {
        DB::beginTransaction();

        try {
            $totals = $this->checkout->buildCartTotals($request);
            $total = $totals['total'];

            if ($total <= 0) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'Total transaksi tidak valid.',
                ];
            }

            $salesNumber = $this->generateSalesNumber($branchId);
            $order = $this->checkout->createSalesOrder(
                $request,
                $totals,
                $salesNumber,
                $branchId,
                $companyId,
                $user->id,
                'pending',
                'unpaid',
                'telegram',
            );

            $payment = SalesOrderPayment::create([
                'sales_order_id' => $order->id,
                'method_payment_id' => $methodPayment->id,
                'payment_code' => 'PAY-'.$salesNumber,
                'gateway' => 'xendit',
                'amount' => $total,
                'change_amount' => 0,
                'status' => 'pending',
                'created_by' => $user->id,
            ]);

            $xenditChannel = $draft['xendit_channel'] ?? null;
            if (! is_string($xenditChannel) || $xenditChannel === '') {
                $xenditChannel = $methodPayment->gateway_channel_code;
            }

            $channelLabel = $xenditChannel
                ? strtoupper($xenditChannel)
                : $methodPayment->name;

            $invoicePayload = [
                'external_id' => $this->paymentSync->externalIdForOrder($order),
                'amount' => (int) round($total),
                'description' => 'Telegram POS '.$salesNumber.' - '.$channelLabel,
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
                throw new \RuntimeException('Xendit tidak mengembalikan invoice_url.');
            }

            $payment->update([
                'gateway_reference' => $invoiceId,
                'gateway_url' => $invoiceUrl,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Order dibuat. Selesaikan pembayaran via link Xendit.',
                'data' => [
                    'sales_number' => $order->sales_number,
                    'sales_order_id' => $order->id,
                    'total' => (float) $order->total,
                    'customer_name' => $order->customer_name,
                    'customer_created' => $customerCreated,
                    'payment_method' => $methodPayment->name,
                    'payment_status' => 'pending',
                    'invoice_url' => $invoiceUrl,
                    'invoice_id' => $invoiceId,
                    'xendit' => true,
                ],
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Telegram Xendit checkout failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Checkout Xendit gagal: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function buildCheckoutRequest(array $draft, ?string $customerId, string $paymentMethodId): Request
    {
        $items = collect($draft['items'] ?? [])->map(fn (array $item) => [
            'variant_id' => $item['variant_id'],
            'unit_id' => $item['unit_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'discount_type' => $item['discount_type'] ?? 'percent',
            'discount_value' => $item['discount_value'] ?? 0,
        ])->all();

        return Request::create('/', 'POST', [
            'price_list_id' => $draft['price_list_id'],
            'customer_id' => $customerId,
            'payment_method_id' => $paymentMethodId,
            'items' => $items,
            'tax_rate' => config('telegram.default_tax_rate', 0),
            'tax_enabled' => config('telegram.default_tax_enabled', false),
            'discount_type' => 'percent',
            'discount_value' => 0,
            'amount_paid' => $this->sumItems($draft),
            'notes' => 'Telegram POS',
        ]);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function sumItems(array $draft): float
    {
        $subtotal = 0.0;

        foreach ($draft['items'] ?? [] as $item) {
            $subtotal += (float) ($item['line_total'] ?? 0);
        }

        if (config('telegram.default_tax_enabled')) {
            $rate = (float) config('telegram.default_tax_rate', 0);
            $subtotal += round($subtotal * $rate / 100, 2);
        }

        return round($subtotal, 2);
    }

    protected function generateSalesNumber(string $branchId): string
    {
        $prefix = config('telegram.sales_number_prefix', 'TGM');
        $dateKey = now()->format('dmy');
        $pattern = "{$prefix}-{$dateKey}-%";

        $lastOrder = SalesOrder::query()
            ->where('branch_id', $branchId)
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
