<?php

namespace App\Services\Ai\Actions;

use App\Models\Customer;
use App\Models\MethodPayment;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderPayment;
use App\Models\User;
use App\Services\Ai\AgentContext;
use App\Services\PosCheckoutService;
use App\Services\Product\ProductSearchService;
use App\Services\Telegram\TelegramCustomerService;
use App\Services\Telegram\TelegramProductResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentSaleActionService
{
    public function __construct(
        protected AgentDraftStore $drafts,
        protected SaleDraftCalculator $calculator,
        protected ProductSearchService $productSearch,
        protected TelegramProductResolver $productResolver,
        protected TelegramCustomerService $customers,
        protected PosCheckoutService $checkout,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments, AgentContext $context): array
    {
        $conversationId = $context->conversationId;

        if ($conversationId === null || $conversationId === '') {
            return [
                'success' => false,
                'message' => 'Percakapan tidak ditemukan. Mulai chat baru lalu coba lagi.',
            ];
        }

        $operation = strtolower(trim((string) ($arguments['operation'] ?? '')));

        return match ($operation) {
            'add_item' => $this->addItem($arguments, $context, $conversationId),
            'set_customer' => $this->setCustomer($arguments, $context, $conversationId),
            'set_payment' => $this->setPayment($arguments, $context, $conversationId),
            'show' => $this->show($context, $conversationId),
            'clear' => $this->clear($conversationId),
            'propose' => $this->propose($context, $conversationId),
            default => [
                'success' => false,
                'message' => 'Operasi tidak dikenali. Gunakan add_item, set_customer, set_payment, show, clear, atau propose.',
            ],
        };
    }

    public function peek(string $conversationId): ?array
    {
        return $this->drafts->get($conversationId);
    }

    /**
     * @param  array<string, mixed>|null  $draft
     */
    public function tokenMatches(?array $draft, string $token): bool
    {
        if ($draft === null) {
            return false;
        }

        return $this->calculator->tokenMatches($draft, $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function confirm(User $user, string $conversationId, string $token): array
    {
        $draft = $this->drafts->get($conversationId);

        if ($draft === null || ($draft['items'] ?? []) === []) {
            return [
                'success' => false,
                'message' => 'Tidak ada draf penjualan yang bisa dikonfirmasi.',
            ];
        }

        if (! $this->calculator->tokenMatches($draft, $token)) {
            return [
                'success' => false,
                'message' => 'Konfirmasi tidak valid atau sudah kedaluwarsa. Ajukan ulang draf penjualan.',
            ];
        }

        $context = AgentContext::fromUser($user, 'web', $conversationId);

        if (! $context->hasPermission(['menu' => 'POS', 'action' => 'is_create'])) {
            return [
                'success' => false,
                'message' => 'Anda tidak memiliki izin membuat transaksi POS.',
            ];
        }

        try {
            $branchId = $context->requireBranch();
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        if (($draft['branch_id'] ?? null) !== $branchId) {
            return [
                'success' => false,
                'message' => 'Cabang draf tidak sama dengan cabang aktif.',
            ];
        }

        $result = $this->submitCashSale($draft, $user, $branchId, $context->companyId);

        if ($result['success'] ?? false) {
            $this->drafts->forget($conversationId);
        }

        return $result;
    }

    public function cancel(string $conversationId, string $token): array
    {
        $draft = $this->drafts->get($conversationId);

        if ($draft === null) {
            return [
                'success' => true,
                'message' => 'Tidak ada draf yang perlu dibatalkan.',
            ];
        }

        if (! $this->calculator->tokenMatches($draft, $token)) {
            return [
                'success' => false,
                'message' => 'Pembatalan tidak valid.',
            ];
        }

        $this->drafts->forget($conversationId);

        return [
            'success' => true,
            'message' => 'Draf penjualan dibatalkan.',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function addItem(array $arguments, AgentContext $context, string $conversationId): array
    {
        $branchId = $context->requireBranch();
        $quantity = (float) ($arguments['quantity'] ?? 0);

        if ($quantity <= 0) {
            return [
                'success' => false,
                'message' => 'Jumlah item harus lebih dari 0.',
            ];
        }

        $priceListId = $this->productResolver->resolveDefaultPriceListId($branchId, $context->companyId);
        $draft = $this->drafts->get($conversationId)
            ?? $this->drafts->emptyDraft($branchId, $context->companyId, $priceListId);

        $variantId = trim((string) ($arguments['variant_id'] ?? ''));
        $query = trim((string) ($arguments['product_query'] ?? ''));

        if ($variantId !== '') {
            $variant = ProductVariant::query()
                ->with('product')
                ->where('id', $variantId)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->first();

            $match = $variant
                ? $this->productSearch->mapVariant($variant, $branchId, $priceListId)
                : null;

            if ($match === null) {
                return [
                    'success' => false,
                    'message' => 'Varian produk tidak ditemukan di cabang aktif.',
                ];
            }
        } else {
            if ($query === '') {
                return [
                    'success' => false,
                    'message' => 'Sebutkan nama atau SKU produk yang mau ditambahkan.',
                ];
            }

            $matches = $this->productSearch->search($query, $branchId, $context->companyId, $priceListId, 5);

            if ($matches->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "Produk \"{$query}\" tidak ditemukan.",
                ];
            }

            if ($matches->count() > 1) {
                return [
                    'success' => false,
                    'needs_choice' => true,
                    'message' => 'Beberapa produk cocok. Pilih salah satu lalu panggil add_item dengan variant_id.',
                    'choices' => $matches->map(fn (array $row) => [
                        'variant_id' => $row['variant_id'],
                        'label' => $row['label'],
                        'sku' => $row['sku'],
                        'price' => $row['unit_price'],
                        'price_formatted' => 'Rp '.number_format((float) $row['unit_price'], 0, ',', '.'),
                        'stock' => $row['stock'],
                    ])->values()->all(),
                ];
            }

            $match = $matches->first();
        }

        if ((float) $match['stock'] < $quantity) {
            return [
                'success' => false,
                'message' => "Stok {$match['label']} tidak cukup (tersedia {$match['stock']}).",
            ];
        }

        $match['quantity'] = $quantity;
        $next = $this->calculator->addItem($draft, $match);
        $this->drafts->put($conversationId, $next);

        return $this->draftPayload($next, 'Item ditambahkan ke draf penjualan.');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function setCustomer(array $arguments, AgentContext $context, string $conversationId): array
    {
        $branchId = $context->requireBranch();
        $draft = $this->requireDraft($conversationId, $branchId, $context->companyId);

        $customerId = trim((string) ($arguments['customer_id'] ?? ''));
        $query = trim((string) ($arguments['customer_query'] ?? ''));

        if ($customerId === '' && $query === '') {
            $next = $this->calculator->withCustomer($draft, null, 'Walk-in Customer');
            $this->drafts->put($conversationId, $next);

            return $this->draftPayload($next, 'Customer di-set sebagai walk-in.');
        }

        if ($customerId !== '') {
            $match = Customer::query()
                ->where('id', $customerId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->whereHas('customerGroup', fn ($q) => $q->where('branch_id', $branchId))
                ->first();

            if ($match === null) {
                return [
                    'success' => false,
                    'message' => 'Customer tidak ditemukan di cabang aktif.',
                ];
            }

            $next = $this->calculator->withCustomer($draft, $match->id, $match->name);
            $this->drafts->put($conversationId, $next);

            return $this->draftPayload($next, 'Customer draf diperbarui.');
        }

        $matches = $this->customers->search($query, $branchId, 5);

        if ($matches === []) {
            return [
                'success' => false,
                'message' => "Customer \"{$query}\" tidak ditemukan. Gunakan walk-in atau pilih customer yang sudah ada.",
            ];
        }

        if (count($matches) > 1) {
            return [
                'success' => false,
                'needs_choice' => true,
                'message' => 'Beberapa customer cocok. Pilih salah satu lalu panggil set_customer dengan customer_id.',
                'choices' => collect($matches)->map(fn ($c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                ])->all(),
            ];
        }

        $match = $matches[0];
        $next = $this->calculator->withCustomer($draft, $match->id, $match->name);
        $this->drafts->put($conversationId, $next);

        return $this->draftPayload($next, 'Customer draf diperbarui.');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function setPayment(array $arguments, AgentContext $context, string $conversationId): array
    {
        $branchId = $context->requireBranch();
        $draft = $this->requireDraft($conversationId, $branchId, $context->companyId);
        $methods = $this->cashMethods($branchId);

        if ($methods->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Tidak ada metode pembayaran tunai aktif di cabang ini.',
            ];
        }

        $paymentId = trim((string) ($arguments['payment_method_id'] ?? ''));
        $query = strtoupper(trim((string) ($arguments['payment_query'] ?? '')));

        $match = null;

        if ($paymentId !== '') {
            $match = $methods->first(fn (MethodPayment $m) => $m->id === $paymentId);
        } elseif ($query !== '') {
            $match = $methods->first(function (MethodPayment $m) use ($query) {
                return str_contains(strtoupper((string) $m->code), $query)
                    || str_contains(strtoupper((string) $m->name), $query);
            });
        } else {
            $match = $methods->first();
        }

        if ($match === null) {
            return [
                'success' => false,
                'message' => 'Metode pembayaran tunai tidak ditemukan.',
                'choices' => $methods->map(fn (MethodPayment $m) => [
                    'id' => $m->id,
                    'code' => $m->code,
                    'name' => $m->name,
                ])->values()->all(),
            ];
        }

        $next = $this->calculator->withPayment($draft, $match->id, (string) $match->name, (string) $match->code);
        $this->drafts->put($conversationId, $next);

        return $this->draftPayload($next, 'Metode pembayaran draf diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function show(AgentContext $context, string $conversationId): array
    {
        $branchId = $context->requireBranch();
        $draft = $this->drafts->get($conversationId);

        if ($draft === null) {
            $priceListId = $this->productResolver->resolveDefaultPriceListId($branchId, $context->companyId);
            $draft = $this->drafts->emptyDraft($branchId, $context->companyId, $priceListId);
        }

        return $this->draftPayload($draft, ($draft['items'] ?? []) === []
            ? 'Draf penjualan masih kosong.'
            : 'Draf penjualan saat ini.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function clear(string $conversationId): array
    {
        $this->drafts->forget($conversationId);

        return [
            'success' => true,
            'message' => 'Draf penjualan dikosongkan.',
            'items' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function propose(AgentContext $context, string $conversationId): array
    {
        $branchId = $context->requireBranch();
        $draft = $this->drafts->get($conversationId);

        if ($draft === null || ($draft['items'] ?? []) === []) {
            return [
                'success' => false,
                'message' => 'Draf masih kosong. Tambahkan item dulu.',
            ];
        }

        if (empty($draft['payment_method_id'])) {
            $cash = $this->cashMethods($branchId)->first();

            if ($cash === null) {
                return [
                    'success' => false,
                    'message' => 'Tidak ada metode pembayaran tunai. Set pembayaran dulu.',
                ];
            }

            $draft = $this->calculator->withPayment($draft, $cash->id, (string) $cash->name, (string) $cash->code);
        }

        $token = Str::random(40);
        $draft = $this->calculator->withConfirmationToken($draft, $token);
        $this->drafts->put($conversationId, $draft);

        $payload = $this->draftPayload($draft, 'Draf siap. User harus menekan tombol konfirmasi di chat sebelum transaksi dibuat.');
        $payload['needs_confirmation'] = true;
        $payload['confirmation_token'] = $token;
        $payload['action'] = 'confirm_sale';

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    protected function submitCashSale(array $draft, User $user, string $branchId, ?string $companyId): array
    {
        $payment = MethodPayment::query()
            ->where('id', $draft['payment_method_id'] ?? null)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if ($payment === null || ! $this->isCashMethod($payment)) {
            return [
                'success' => false,
                'message' => 'Hanya pembayaran tunai yang bisa dibuat dari chatbot.',
            ];
        }

        $request = $this->buildCheckoutRequest($draft, $payment->id);

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
                'pos',
            );

            SalesOrderPayment::create([
                'sales_order_id' => $order->id,
                'method_payment_id' => $payment->id,
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
                'message' => 'Transaksi penjualan berhasil dibuat.',
                'sales_number' => $order->sales_number,
                'total' => (float) $order->total,
                'total_formatted' => 'Rp '.number_format((float) $order->total, 0, ',', '.'),
                'customer_name' => $order->customer_name,
                'payment_method' => $payment->name,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::warning('Assistant sale checkout failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal membuat transaksi. Periksa stok, cabang, dan metode pembayaran lalu coba lagi.',
            ];
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, MethodPayment>
     */
    protected function cashMethods(string $branchId)
    {
        $allowed = array_map('strtoupper', config('agent.allowed_payment_codes', ['CASH', 'TUNAI']));

        return MethodPayment::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn (MethodPayment $method) => in_array(strtoupper((string) $method->code), $allowed, true)
                && ! $method->uses_payment_gateway)
            ->values();
    }

    protected function isCashMethod(MethodPayment $method): bool
    {
        $allowed = array_map('strtoupper', config('agent.allowed_payment_codes', ['CASH', 'TUNAI']));

        return in_array(strtoupper((string) $method->code), $allowed, true)
            && ! $method->uses_payment_gateway;
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireDraft(string $conversationId, string $branchId, ?string $companyId): array
    {
        $draft = $this->drafts->get($conversationId);

        if ($draft !== null) {
            return $draft;
        }

        $priceListId = $this->productResolver->resolveDefaultPriceListId($branchId, $companyId);

        return $this->drafts->emptyDraft($branchId, $companyId, $priceListId);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    protected function draftPayload(array $draft, string $message): array
    {
        $items = collect($draft['items'] ?? [])->map(fn (array $item) => [
            'label' => $item['label'] ?? '-',
            'sku' => $item['sku'] ?? '-',
            'quantity' => $item['quantity'] ?? 0,
            'price_formatted' => 'Rp '.number_format((float) ($item['unit_price'] ?? 0), 0, ',', '.'),
            'line_total_formatted' => 'Rp '.number_format((float) ($item['line_total'] ?? 0), 0, ',', '.'),
        ])->values()->all();

        return [
            'success' => true,
            'message' => $message,
            'customer_name' => $draft['customer_name'] ?? 'Walk-in Customer',
            'payment_method' => $draft['payment_method_name'] ?? 'Belum dipilih (akan memakai tunai saat konfirmasi)',
            'subtotal' => (float) ($draft['subtotal'] ?? 0),
            'subtotal_formatted' => 'Rp '.number_format((float) ($draft['subtotal'] ?? 0), 0, ',', '.'),
            'item_count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function buildCheckoutRequest(array $draft, string $paymentMethodId): Request
    {
        $items = collect($draft['items'] ?? [])->map(fn (array $item) => [
            'variant_id' => $item['variant_id'],
            'unit_id' => $item['unit_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'discount_type' => 'percent',
            'discount_value' => 0,
        ])->all();

        return Request::create('/', 'POST', [
            'price_list_id' => $draft['price_list_id'],
            'customer_id' => $draft['customer_id'] ?? null,
            'payment_method_id' => $paymentMethodId,
            'items' => $items,
            'tax_rate' => 0,
            'tax_enabled' => false,
            'discount_type' => 'percent',
            'discount_value' => 0,
            'amount_paid' => $this->calculator->subtotal($draft['items'] ?? []),
            'notes' => 'TITANIE Assistant',
        ]);
    }

    protected function generateSalesNumber(string $branchId): string
    {
        $prefix = (string) config('agent.sales_number_prefix', 'AIT');
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
