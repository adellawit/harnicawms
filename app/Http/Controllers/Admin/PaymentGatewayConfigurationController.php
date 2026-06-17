<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Xendit\PaymentGatewayConfigurationService;
use App\Services\Xendit\XenditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentGatewayConfigurationController extends Controller
{
    public function __construct(
        protected PaymentGatewayConfigurationService $configuration,
        protected XenditService $xendit,
    ) {}

    public function indexView(): View
    {
        return view('admin.settings.payment-gateway-configuration.index', [
            'config' => $this->configuration->current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'use_payment_gateway' => ['required', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'webhook_token' => ['nullable', 'string', 'max:255'],
            'api_base_url' => ['required', 'url', 'max:255'],
            'method_codes' => ['required', 'string', 'max:255'],
            'invoice_duration' => ['required', 'integer', 'min:60', 'max:86400'],
            'sync_channels_from_api' => ['nullable', 'boolean'],
            'allowed_payment_methods' => ['nullable', 'string', 'max:500'],
            'channels_cache_ttl' => ['required', 'integer', 'min:60', 'max:86400'],
            'channel_probe_amount' => ['required', 'integer', 'min:1000', 'max:1000000'],
        ]);

        if ($this->boolValue($validated['use_payment_gateway']) && ! $this->boolValue($validated['enabled'] ?? false)) {
            return redirect()
                ->route('settings.payment-gateway-configuration.index.view')
                ->withErrors(['enabled' => 'Aktifkan Xendit API jika mode Payment Gateway dipilih.'])
                ->withInput();
        }

        $this->configuration->update($validated);

        return redirect()
            ->route('settings.payment-gateway-configuration.index.view')
            ->with('success', 'Konfigurasi Payment Gateway berhasil disimpan.');
    }

    public function testConnection(): RedirectResponse
    {
        if (! $this->xendit->isConfigured()) {
            return redirect()
                ->route('settings.payment-gateway-configuration.index.view')
                ->withErrors(['error' => 'Xendit belum dikonfigurasi. Isi Secret Key dan aktifkan Xendit API.']);
        }

        try {
            $channels = $this->xendit->getMerchantActiveChannelCodes();
            $count = count($channels);

            return redirect()
                ->route('settings.payment-gateway-configuration.index.view')
                ->with('success', "Koneksi ke Xendit berhasil. {$count} channel aktif terdeteksi.");
        } catch (\Throwable $e) {
            return redirect()
                ->route('settings.payment-gateway-configuration.index.view')
                ->withErrors(['error' => 'Gagal menghubungi Xendit: '.$e->getMessage()]);
        }
    }

    protected function boolValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
