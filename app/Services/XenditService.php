<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class XenditService
{
    public function isConfigured(): bool
    {
        return config('xendit.enabled') && ! empty(config('xendit.secret_key'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createInvoice(array $payload): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Xendit is not configured. Set XENDIT_ENABLED=true and XENDIT_SECRET_KEY in .env');
        }

        $payload = $this->applyInvoicePaymentOptions($payload);

        try {
            return $this->postInvoice($payload);
        } catch (RuntimeException $e) {
            if ($this->shouldRetryWithoutPaymentMethods($e, $payload)) {
                Log::warning('Xendit invoice: payment_methods not supported on account, retrying without filter', [
                    'payment_methods' => $payload['payment_methods'] ?? [],
                ]);

                unset($payload['payment_methods']);

                return $this->postInvoice($payload);
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function postInvoice(array $payload): array
    {
        $response = Http::withBasicAuth(config('xendit.secret_key'), '')
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post(rtrim((string) config('xendit.api_base_url'), '/') . '/v2/invoices', $payload);

        if (! $response->successful()) {
            $message = $response->json('message') ?? $response->body();
            throw new RuntimeException('Xendit API error: ' . $message);
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function applyInvoicePaymentOptions(array $payload): array
    {
        $methods = $payload['payment_methods'] ?? [];
        $isCredit = in_array('CREDIT_CARD', array_map('strtoupper', (array) $methods), true);

        if (! $isCredit) {
            $payload['should_exclude_credit_card'] = true;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function shouldRetryWithoutPaymentMethods(RuntimeException $e, array $payload): bool
    {
        if (! isset($payload['payment_methods']) || $payload['payment_methods'] === []) {
            return false;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'unsupported')
            && str_contains($message, 'payment method');
    }

    public function normalizeChannelCode(string $code): string
    {
        $upper = strtoupper(trim($code));
        $aliases = config('xendit.channel_aliases', []);

        return $aliases[$upper] ?? $upper;
    }

    /**
     * @param  array<int, string>  $methods
     * @return array<int, string>|null
     */
    /**
     * Channel aktif di akun Xendit (dari API, di-cache).
     *
     * @return array<int, string>
     */
    public function getMerchantActiveChannelCodes(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $envRestrict = config('xendit.allowed_payment_methods', []);

        if (! config('xendit.sync_channels_from_api', true)) {
            return $envRestrict !== [] ? $envRestrict : $this->getConfigCatalogChannelCodes();
        }

        $cacheKey = 'xendit:merchant_channels:' . substr(
            hash('sha256', (string) config('xendit.secret_key')),
            0,
            16
        );
        $ttl = (int) config('xendit.channels_cache_ttl', 3600);

        $fromApi = Cache::remember($cacheKey, $ttl, fn () => $this->fetchChannelsFromProbeInvoice());

        if ($fromApi === []) {
            return $envRestrict !== [] ? $envRestrict : $this->getConfigCatalogChannelCodes();
        }

        if ($envRestrict !== []) {
            return array_values(array_intersect($fromApi, $envRestrict));
        }

        return $fromApi;
    }

    public function isChannelActiveOnMerchant(string $channelCode): bool
    {
        $code = $this->normalizeChannelCode($channelCode);
        $active = $this->getMerchantActiveChannelCodes();

        if ($active === []) {
            return true;
        }

        return in_array($code, $active, true);
    }

    /**
     * @return array<int, string>
     */
    protected function getConfigCatalogChannelCodes(): array
    {
        return array_keys(config('xendit.channel_catalog', []));
    }

    /**
     * @return array{label: string, group: string}
     */
    public function resolveChannelMeta(string $code): array
    {
        $code = $this->normalizeChannelCode($code);
        $catalog = config('xendit.channel_catalog', []);

        if (isset($catalog[$code])) {
            return [
                'label' => (string) ($catalog[$code]['label'] ?? $code),
                'group' => strtoupper((string) ($catalog[$code]['group'] ?? 'TRANSFER')),
            ];
        }

        if ($code === 'QRIS') {
            return ['label' => 'QRIS', 'group' => 'QRIS'];
        }

        if (in_array($code, ['OVO', 'DANA', 'SHOPEEPAY', 'LINKAJA', 'ASTRAPAY', 'GOPAY'], true)) {
            return ['label' => ucfirst(strtolower($code)), 'group' => 'EWALLET'];
        }

        return ['label' => ucwords(strtolower(str_replace('_', ' ', $code))), 'group' => 'TRANSFER'];
    }

    /**
     * Ambil channel aktif dari response invoice Xendit (tanpa payment_methods filter).
     *
     * @return array<int, string>
     */
    protected function fetchChannelsFromProbeInvoice(): array
    {
        try {
            $invoice = $this->postInvoice([
                'external_id' => 'pos-ch-probe-' . uniqid(),
                'amount' => max((int) config('xendit.channel_probe_amount', 10000), 1),
                'description' => 'POS payment channel sync',
                'invoice_duration' => 300,
                'currency' => 'IDR',
                'should_exclude_credit_card' => true,
            ]);

            $channels = $this->parseInvoiceAvailableChannels($invoice);

            Log::info('Xendit POS channels synced from account', ['channels' => $channels]);

            return $channels;
        } catch (\Throwable $e) {
            Log::warning('Xendit channel sync failed, using config fallback', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array<int, string>
     */
    protected function parseInvoiceAvailableChannels(array $invoice): array
    {
        $codes = [];

        foreach ($invoice['available_banks'] ?? [] as $bank) {
            $code = strtoupper((string) ($bank['bank_code'] ?? ''));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        foreach ($invoice['available_ewallets'] ?? [] as $wallet) {
            $code = strtoupper((string) ($wallet['ewallet_type'] ?? ''));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        foreach ($invoice['available_qr_codes'] ?? [] as $qr) {
            $code = strtoupper((string) ($qr['qr_code_type'] ?? 'QRIS'));
            $codes[] = $code === 'QRIS' ? 'QRIS' : $code;
        }

        return array_values(array_unique($codes));
    }

    protected function filterAllowedPaymentMethods(array $methods): ?array
    {
        $normalized = array_values(array_unique(array_map(
            fn (string $m) => $this->normalizeChannelCode($m),
            $methods
        )));

        $active = $this->getMerchantActiveChannelCodes();
        if ($active === []) {
            return $normalized !== [] ? $normalized : null;
        }

        $filtered = array_values(array_intersect($normalized, $active));

        return $filtered !== [] ? $filtered : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getInvoice(string $invoiceId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $response = Http::withBasicAuth(config('xendit.secret_key'), '')
            ->acceptJson()
            ->timeout(30)
            ->get(rtrim((string) config('xendit.api_base_url'), '/') . '/v2/invoices/' . $invoiceId);

        return $response->successful() ? $response->json() : null;
    }

    public function verifyWebhookToken(?string $token): bool
    {
        $expected = config('xendit.webhook_token');

        if (empty($expected)) {
            return false;
        }

        return hash_equals((string) $expected, (string) $token);
    }

    public function usesXenditForMethod(?string $methodCode, ?\App\Models\MethodPayment $method = null): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        if ($method !== null) {
            return (bool) $method->uses_payment_gateway
                && ($method->gateway_provider === null || $method->gateway_provider === 'xendit');
        }

        if (empty($methodCode)) {
            return false;
        }

        return in_array(strtoupper($methodCode), config('xendit.method_codes', []), true);
    }

    /**
     * @return array<string, array<int, array{code: string, label: string}>>
     */
    public function channelCatalogByGroup(): array
    {
        $byGroup = [];

        foreach (config('xendit.channel_catalog', []) as $code => $meta) {
            $group = strtoupper((string) ($meta['group'] ?? ''));
            if ($group === '') {
                continue;
            }
            $byGroup[$group][] = [
                'code' => strtoupper((string) $code),
                'label' => (string) ($meta['label'] ?? $code),
            ];
        }

        foreach ($byGroup as &$channels) {
            usort($channels, fn ($a, $b) => strcmp($a['label'], $b['label']));
        }

        return $byGroup;
    }

    /**
     * @return array<int, string>
     */
    public function paymentGroupCodes(): array
    {
        return array_values(array_unique(array_map(
            fn (array $group) => strtoupper((string) ($group['method_code'] ?? '')),
            config('xendit.payment_channel_groups', [])
        )));
    }

    /**
     * @return array<int, string>|null
     */
    public function paymentMethodsForCode(string $methodCode): ?array
    {
        $map = config('xendit.payment_methods_map', []);
        $code = strtoupper($methodCode);

        if (! isset($map[$code])) {
            return null;
        }

        $methods = $map[$code];

        return is_array($methods) && $methods !== [] ? $methods : null;
    }

    /**
     * Channel spesifik dari POS, atau fallback per method_code.
     *
     * @return array<int, string>|null
     */
    public function resolvePaymentMethods(string $methodCode, ?string $channelCode): ?array
    {
        if ($channelCode !== null && $channelCode !== '') {
            return $this->filterAllowedPaymentMethods([$channelCode]);
        }

        $fallback = $this->paymentMethodsForCode($methodCode);

        return $fallback !== null ? $this->filterAllowedPaymentMethods($fallback) : null;
    }

    public function channelIconUrl(string $code, ?string $label = null, ?string $customPath = null): string
    {
        if ($customPath !== null && $customPath !== '') {
            return str_starts_with($customPath, 'http')
                ? $customPath
                : asset($customPath);
        }

        $labelLower = strtolower((string) $label);
        $codeUpper = strtoupper($code);

        if ($codeUpper === 'COD' || str_contains($labelLower, 'delivery')) {
            return asset('assets/img/payments/cod.svg');
        }

        if (str_contains($labelLower, 'visa') && str_contains($labelLower, 'master')) {
            return asset('assets/img/payments/credit_card.svg');
        }
        if (str_contains($labelLower, 'visa')) {
            return asset('assets/img/payments/visa.svg');
        }
        if (str_contains($labelLower, 'master')) {
            return asset('assets/img/payments/mastercard.svg');
        }

        $labelBanks = [
            'mandiri' => 'mandiri.svg',
            'bca' => 'bca.svg',
            'bni' => 'bni.svg',
            'bri' => 'bri.svg',
            'permata' => 'permata.svg',
            'cimb' => 'cimb.svg',
            'bsi' => 'bsi.svg',
            'bjb' => 'bjb.svg',
            'bss' => 'bss.svg',
            'sahabat' => 'bss.svg',
            'sampoerna' => 'bss.svg',
            'ovo' => 'ovo.svg',
            'dana' => 'dana.svg',
            'shopee' => 'shopeepay.svg',
            'linkaja' => 'linkaja.svg',
            'astra' => 'astrapay.svg',
        ];

        foreach ($labelBanks as $needle => $file) {
            if (str_contains($labelLower, $needle)) {
                return asset('assets/img/payments/' . $file);
            }
        }

        $icons = config('xendit.channel_icons', []);
        $codeKey = strtoupper($code);
        if (isset($icons[$codeKey])) {
            return asset($icons[$codeKey]);
        }

        $fileCandidates = [
            'assets/img/payments/' . strtolower($codeKey) . '.svg',
            'assets/img/payments/' . strtolower(str_replace('_', '-', $codeKey)) . '.svg',
        ];
        foreach ($fileCandidates as $file) {
            if (file_exists(public_path($file))) {
                return asset($file);
            }
        }

        return asset('assets/img/payments/default.svg');
    }

    public function groupIconClass(string $methodCode): string
    {
        $icons = config('xendit.group_icons', []);

        return $icons[strtoupper($methodCode)] ?? 'ti-credit-card';
    }

    /**
     * Grup channel untuk ditampilkan di modal POS.
     *
     * @param  iterable<object{id: string, code: string, name: string}>  $methodPayments
     * @return array<int, array{title: string, method_id: string, method_code: string, group_icon: string, channels: array<int, array{code: string, label: string, icon: string}>}>
     */
    public function buildPaymentChannelGroups(iterable $methodPayments): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $methods = collect($methodPayments);
        $methodsByCode = [];
        foreach ($methods as $method) {
            $methodsByCode[strtoupper((string) $method->code)] = $method;
        }

        $dbChannelsByGroup = [];
        foreach ($methods as $method) {
            if (! $method->uses_payment_gateway || blank($method->gateway_channel_code)) {
                continue;
            }

            $groupKey = strtoupper((string) ($method->payment_group_code ?: $method->code));
            $channelCode = strtoupper((string) $method->gateway_channel_code);
            $meta = $this->resolveChannelMeta($channelCode);

            $dbChannelsByGroup[$groupKey][] = [
                'code' => $channelCode,
                'label' => $method->name ?: $meta['label'],
                'icon' => $this->channelIconUrl($channelCode, $method->name ?: $meta['label']),
                'method_id' => $method->id,
            ];
        }

        $apiChannelsByGroup = [];
        foreach ($this->getMerchantActiveChannelCodes() as $code) {
            $meta = $this->resolveChannelMeta($code);
            $groupKey = $meta['group'];
            $apiChannelsByGroup[$groupKey][] = [
                'code' => $code,
                'label' => $meta['label'],
                'icon' => $this->channelIconUrl($code, $meta['label']),
                'method_id' => null,
            ];
        }

        $groups = [];
        foreach (config('xendit.payment_channel_groups', []) as $groupDef) {
            $methodCode = strtoupper((string) ($groupDef['method_code'] ?? ''));
            if ($methodCode === '') {
                continue;
            }

            $method = $methodsByCode[$methodCode] ?? null;
            if (! $method || ! $this->usesXenditForMethod($methodCode, $method)) {
                continue;
            }

            $channels = $dbChannelsByGroup[$methodCode] ?? [];
            if ($channels === []) {
                $channels = $apiChannelsByGroup[$methodCode] ?? [];
            }

            if ($channels === []) {
                continue;
            }

            usort($channels, fn ($a, $b) => strcmp($a['label'], $b['label']));

            $groups[] = [
                'title' => (string) ($groupDef['title'] ?? $method->name),
                'method_id' => $method->id,
                'method_code' => $methodCode,
                'group_icon' => $this->groupIconClass($methodCode),
                'channels' => array_map(fn (array $ch) => [
                    'code' => $ch['code'],
                    'label' => $ch['label'],
                    'icon' => $ch['icon'],
                    'method_id' => $ch['method_id'] ?? $method->id,
                ], $channels),
            ];
        }

        return $groups;
    }
}
