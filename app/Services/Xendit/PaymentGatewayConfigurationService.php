<?php

namespace App\Services\Xendit;

use App\Support\EnvWriter;
use Illuminate\Support\Facades\Artisan;

class PaymentGatewayConfigurationService
{
    public function __construct(
        protected EnvWriter $env,
        protected XenditService $xendit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $secretKey = $this->env->get('XENDIT_SECRET_KEY', config('xendit.secret_key'));
        $webhookToken = $this->env->get('XENDIT_WEBHOOK_TOKEN', config('xendit.webhook_token'));

        return [
            'use_payment_gateway' => (bool) config('xendit.use_payment_gateway'),
            'enabled' => (bool) config('xendit.enabled'),
            'secret_key' => $secretKey,
            'secret_key_masked' => $this->env->maskSecret($secretKey),
            'webhook_token' => $webhookToken,
            'webhook_token_masked' => $this->env->maskSecret($webhookToken, 12),
            'api_base_url' => (string) config('xendit.api_base_url'),
            'method_codes' => implode(', ', config('xendit.method_codes', [])),
            'invoice_duration' => (int) config('xendit.invoice_duration', 900),
            'sync_channels_from_api' => (bool) config('xendit.sync_channels_from_api', true),
            'allowed_payment_methods' => implode(', ', config('xendit.allowed_payment_methods', [])),
            'channels_cache_ttl' => (int) config('xendit.channels_cache_ttl', 3600),
            'channel_probe_amount' => (int) config('xendit.channel_probe_amount', 10000),
            'status' => [
                'mode_label' => config('xendit.use_payment_gateway')
                    ? 'Payment Gateway + Cash'
                    : 'Metode Pembayaran Biasa',
                'api_configured' => $this->xendit->isConfigured(),
                'payment_gateway_active' => $this->xendit->isPaymentGatewayReady(),
                'active_channel_count' => $this->xendit->isConfigured()
                    ? count($this->xendit->getMerchantActiveChannelCodes())
                    : 0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): void
    {
        $values = [
            'XENDIT_USE_PAYMENT_GATEWAY' => $this->boolValue($input['use_payment_gateway'] ?? false),
            'XENDIT_ENABLED' => $this->boolValue($input['enabled'] ?? false),
            'XENDIT_API_BASE_URL' => (string) ($input['api_base_url'] ?? 'https://api.xendit.co'),
            'XENDIT_METHOD_CODES' => (string) ($input['method_codes'] ?? 'QRIS,TRANSFER,EWALLET'),
            'XENDIT_INVOICE_DURATION' => (int) ($input['invoice_duration'] ?? 900),
            'XENDIT_SYNC_CHANNELS' => $this->boolValue($input['sync_channels_from_api'] ?? true),
            'XENDIT_CHANNELS_CACHE_TTL' => (int) ($input['channels_cache_ttl'] ?? 3600),
            'XENDIT_CHANNEL_PROBE_AMOUNT' => (int) ($input['channel_probe_amount'] ?? 10000),
            'XENDIT_ALLOWED_PAYMENT_METHODS' => (string) ($input['allowed_payment_methods'] ?? ''),
        ];

        if (filled($input['secret_key'] ?? null)) {
            $values['XENDIT_SECRET_KEY'] = (string) $input['secret_key'];
        }

        if (filled($input['webhook_token'] ?? null)) {
            $values['XENDIT_WEBHOOK_TOKEN'] = (string) $input['webhook_token'];
        }

        $this->env->setMany($values);
        Artisan::call('config:clear');
    }

    protected function boolValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
