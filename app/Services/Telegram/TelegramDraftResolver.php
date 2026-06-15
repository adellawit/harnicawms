<?php

namespace App\Services\Telegram;

use App\Models\MethodPayment;
use App\Models\User;
use App\Services\DeepSeek\DeepSeekTransactionParser;
use Illuminate\Support\Collection;

class TelegramDraftResolver
{
    public function __construct(
        protected DeepSeekTransactionParser $parser,
        protected TelegramProductResolver $products,
        protected TelegramCustomerService $customers,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   message?: string,
     *   draft?: array<string, mixed>,
     *   customer_choices?: array<int, array{id: string, name: string, code: string}>,
     *   variant_choices?: array<int, array{index: int, query: string, options: array<int, array<string, mixed>>}>
     * }
     */
    public function buildFromNaturalLanguage(string $message, User $user): array
    {
        $branchId = $user->getBranchIdForTransaction();

        if ($branchId === null) {
            return [
                'success' => false,
                'message' => 'Cabang aktif tidak ditemukan. Set branch di akun kasir.',
            ];
        }

        $companyId = $user->getCompanyIdForProduct();
        $priceListId = $this->products->resolveDefaultPriceListId($branchId, $companyId);

        $productContext = implode("\n", $this->products->popularProductContext($branchId));

        try {
            $parsed = $this->parser->parse($message, $productContext);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Gagal parse pesan: '.$e->getMessage(),
            ];
        }

        if (($parsed['intent'] ?? 'unknown') !== 'create_transaction') {
            return [
                'success' => false,
                'message' => 'Pesan tidak dikenali sebagai transaksi. Contoh: "3 kopi arabica untuk Budi bayar tunai"',
            ];
        }

        $items = $parsed['items'] ?? [];

        if ($items === []) {
            return [
                'success' => false,
                'message' => 'Item transaksi tidak ditemukan. Sebutkan produk dan jumlahnya.',
            ];
        }

        $resolvedItems = [];
        $variantChoices = [];

        foreach ($items as $index => $item) {
            $query = trim((string) ($item['product_query'] ?? ''));

            if ($query === '') {
                continue;
            }

            $quantity = $item['quantity'] ?? null;

            if ($quantity === null || (float) $quantity <= 0) {
                return [
                    'success' => false,
                    'message' => "Quantity tidak jelas untuk \"{$query}\". Contoh: 2 {$query}",
                ];
            }

            $matches = $this->products->search($query, $branchId, $companyId, $priceListId, 5);

            if ($matches->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "Produk \"{$query}\" tidak ditemukan.",
                ];
            }

            if ($matches->count() > 1) {
                $variantChoices[] = [
                    'index' => $index,
                    'query' => $query,
                    'quantity' => (float) $quantity,
                    'options' => $matches->values()->all(),
                ];

                continue;
            }

            $match = $matches->first();
            $resolvedItems[] = $this->buildResolvedItem($match, (float) $quantity);
        }

        if ($variantChoices !== []) {
            return [
                'success' => false,
                'message' => 'Beberapa produk perlu dipilih.',
                'variant_choices' => $variantChoices,
                'partial_draft' => [
                    'branch_id' => $branchId,
                    'company_id' => $companyId,
                    'price_list_id' => $priceListId,
                    'customer_name' => $parsed['customer_name'] ?? null,
                    'payment_hint' => $parsed['payment_hint'] ?? null,
                    'resolved_items' => $resolvedItems,
                    'variant_choices' => $variantChoices,
                ],
            ];
        }

        $customerName = trim((string) ($parsed['customer_name'] ?? ''));
        $customerChoices = [];

        if ($customerName !== '') {
            $matches = $this->customers->search($customerName, $branchId, 5);

            if (count($matches) > 1) {
                $exactCount = collect($matches)->filter(
                    fn ($c) => mb_strtolower($c->name) === mb_strtolower($customerName)
                )->count();

                if ($exactCount !== 1) {
                    $customerChoices = collect($matches)->map(fn ($c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'code' => $c->code,
                    ])->all();
                }
            }
        }

        if ($customerChoices !== []) {
            return [
                'success' => false,
                'message' => 'Pilih customer yang dimaksud.',
                'customer_choices' => $customerChoices,
                'partial_draft' => [
                    'branch_id' => $branchId,
                    'company_id' => $companyId,
                    'price_list_id' => $priceListId,
                    'customer_name' => $customerName,
                    'customer_mode' => 'pick',
                    'payment_hint' => $parsed['payment_hint'] ?? null,
                    'items' => $resolvedItems,
                ],
            ];
        }

        $paymentMethod = $this->resolvePaymentMethod($branchId, $parsed['payment_hint'] ?? null);

        if ($paymentMethod === null) {
            return [
                'success' => true,
                'draft' => [
                    'branch_id' => $branchId,
                    'company_id' => $companyId,
                    'price_list_id' => $priceListId,
                    'customer_id' => null,
                    'customer_name' => $customerName !== '' ? $customerName : null,
                    'customer_mode' => $customerName !== '' ? 'new_or_existing' : 'walk_in',
                    'items' => $resolvedItems,
                    'payment_method_id' => null,
                    'payment_hint' => $parsed['payment_hint'] ?? null,
                ],
                'needs_payment' => true,
            ];
        }

        return [
            'success' => true,
            'draft' => [
                'branch_id' => $branchId,
                'company_id' => $companyId,
                'price_list_id' => $priceListId,
                'customer_id' => null,
                'customer_name' => $customerName !== '' ? $customerName : null,
                'customer_mode' => $customerName !== '' ? 'new_or_existing' : 'walk_in',
                'items' => $resolvedItems,
                'payment_method_id' => $paymentMethod->id,
                'payment_method_name' => $paymentMethod->name,
                'payment_hint' => $parsed['payment_hint'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $match
     * @return array<string, mixed>
     */
    public function buildResolvedItem(array $match, float $quantity): array
    {
        $lineTotal = round($quantity * (float) $match['unit_price'], 2);

        return [
            'variant_id' => $match['variant_id'],
            'product_id' => $match['product_id'],
            'unit_id' => $match['unit_id'],
            'quantity' => $quantity,
            'unit_price' => (float) $match['unit_price'],
            'label' => $match['label'],
            'sku' => $match['sku'],
            'line_total' => $lineTotal,
            'discount_type' => 'percent',
            'discount_value' => 0,
        ];
    }

    public function resolvePaymentMethod(string $branchId, ?string $paymentHint): ?MethodPayment
    {
        $allowedCodes = config('telegram.allowed_payment_codes', ['CASH']);

        $methods = MethodPayment::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('branch_id', $branchId)
            ->whereIn('code', $allowedCodes)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($methods->isEmpty()) {
            return null;
        }

        if ($paymentHint === null) {
            return null;
        }

        $hintMap = [
            'cash' => ['CASH'],
            'transfer' => ['TRANSFER'],
            'qris' => ['QRIS'],
            'credit' => ['CREDIT', 'COD'],
            'ewallet' => ['EWALLET'],
        ];

        $codes = $hintMap[$paymentHint] ?? [];

        if ($codes === []) {
            return $methods->first();
        }

        return $methods->first(fn (MethodPayment $m) => in_array(strtoupper($m->code), $codes, true))
            ?? $methods->first();
    }

    /**
     * @return Collection<int, MethodPayment>
     */
    public function availablePaymentMethods(string $branchId): Collection
    {
        $allowedCodes = config('telegram.allowed_payment_codes', ['CASH']);

        return MethodPayment::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('branch_id', $branchId)
            ->whereIn('code', $allowedCodes)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public function calculateDraftTotal(array $draft): float
    {
        $total = 0.0;

        foreach ($draft['items'] ?? [] as $item) {
            $total += (float) ($item['line_total'] ?? 0);
        }

        if (config('telegram.default_tax_enabled')) {
            $rate = (float) config('telegram.default_tax_rate', 0);
            $total += round($total * $rate / 100, 2);
        }

        return round($total, 2);
    }
}
