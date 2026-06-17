<x-app-layout>

    @section('title', 'Payment Gateway Configuration | ')

    @push('page-css')
        <style>
            .pg-api-panel { display: none; }
            .pg-api-panel.active { display: block; }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Settings', 'url' => 'javascript:void(0);'],
                ['label' => 'Payment Gateway Configuration', 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="mb-1">Status</h5>
                            <span class="badge bg-label-primary me-2">{{ $config['status']['mode_label'] }}</span>
                            @if ($config['status']['api_configured'])
                                <span class="badge bg-label-success me-2">Xendit API Terhubung</span>
                            @else
                                <span class="badge bg-label-warning me-2">Xendit API Belum Siap</span>
                            @endif
                            @if ($config['status']['payment_gateway_active'])
                                <span class="badge bg-label-success">PG Aktif di POS / Shop</span>
                            @else
                                <span class="badge bg-label-secondary">PG Nonaktif</span>
                            @endif
                            @if ($config['status']['api_configured'])
                                <span class="badge bg-label-info">{{ $config['status']['active_channel_count'] }} channel aktif</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('settings.payment-gateway-configuration.test') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="ti ti-plug-connected me-1"></i> Test Koneksi Xendit
                            </button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.payment-gateway-configuration.update') }}">
                    @csrf

                    <div class="card mb-4">
                        <h5 class="card-header">Mode Pembayaran</h5>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                Pilih apakah transaksi memakai <strong>Payment Gateway (Xendit) + Cash</strong>
                                atau hanya <strong>metode pembayaran biasa</strong> tanpa PG.
                            </p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check custom-option custom-option-basic">
                                        <label class="form-check-label custom-option-content" for="pg_mode_yes">
                                            <input class="form-check-input" type="radio" name="use_payment_gateway" id="pg_mode_yes" value="1"
                                                @checked(old('use_payment_gateway', $config['use_payment_gateway'] ? '1' : '0') == '1')>
                                            <span class="custom-option-header">
                                                <span class="h6 mb-0">Yes — Payment Gateway + Cash</span>
                                            </span>
                                            <small class="custom-option-body text-muted">
                                                POS &amp; Shop menampilkan channel Xendit (Transfer, QRIS, E-Wallet) plus kolom Cash.
                                            </small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check custom-option custom-option-basic">
                                        <label class="form-check-label custom-option-content" for="pg_mode_no">
                                            <input class="form-check-input" type="radio" name="use_payment_gateway" id="pg_mode_no" value="0"
                                                @checked(old('use_payment_gateway', $config['use_payment_gateway'] ? '1' : '0') == '0')>
                                            <span class="custom-option-header">
                                                <span class="h6 mb-0">No — Metode Pembayaran Biasa</span>
                                            </span>
                                            <small class="custom-option-body text-muted">
                                                Tanpa Payment Gateway. Hanya metode manual yang dikonfigurasi di Master Metode Pembayaran.
                                            </small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="pgApiPanel" class="pg-api-panel {{ old('use_payment_gateway', $config['use_payment_gateway'] ? '1' : '0') == '1' ? 'active' : '' }}">
                        <div class="card mb-4">
                            <h5 class="card-header">Xendit API</h5>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4 d-flex align-items-end">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="enabled" value="1" id="xendit_enabled"
                                                @checked(old('enabled', $config['enabled']))>
                                            <label class="form-check-label" for="xendit_enabled">Aktifkan Xendit API</label>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label">Secret Key Saat Ini</label>
                                        <input type="text" class="form-control font-monospace mb-2"
                                            value="{{ $config['secret_key_masked'] ?: 'Belum diisi' }}"
                                            readonly>
                                        <label class="form-label">Secret Key Baru</label>
                                        <input type="text" name="secret_key" class="form-control font-monospace"
                                            placeholder="Kosongkan jika tidak ingin mengubah"
                                            autocomplete="off">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Webhook Token Saat Ini</label>
                                        <input type="text" class="form-control font-monospace mb-2"
                                            value="{{ $config['webhook_token_masked'] ?: 'Belum diisi' }}"
                                            readonly>
                                        <label class="form-label">Webhook Token Baru</label>
                                        <input type="text" name="webhook_token" class="form-control font-monospace"
                                            placeholder="Kosongkan jika tidak ingin mengubah"
                                            autocomplete="off">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">API Base URL</label>
                                        <input type="url" name="api_base_url" class="form-control"
                                            value="{{ old('api_base_url', $config['api_base_url']) }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Method Codes (PG)</label>
                                        <input type="text" name="method_codes" class="form-control"
                                            value="{{ old('method_codes', $config['method_codes']) }}"
                                            placeholder="QRIS,TRANSFER,EWALLET" required>
                                        <small class="text-muted">Kode internal method_payment yang diproses via Xendit.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Invoice Duration (detik)</label>
                                        <input type="number" name="invoice_duration" class="form-control" min="60" max="86400"
                                            value="{{ old('invoice_duration', $config['invoice_duration']) }}" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <h5 class="card-header">Sinkronisasi Channel</h5>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4 d-flex align-items-end">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="sync_channels_from_api" value="1" id="sync_channels"
                                                @checked(old('sync_channels_from_api', $config['sync_channels_from_api']))>
                                            <label class="form-check-label" for="sync_channels">Sinkron channel dari API Xendit</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Cache TTL (detik)</label>
                                        <input type="number" name="channels_cache_ttl" class="form-control" min="60" max="86400"
                                            value="{{ old('channels_cache_ttl', $config['channels_cache_ttl']) }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Probe Amount (IDR)</label>
                                        <input type="number" name="channel_probe_amount" class="form-control" min="1000" max="1000000"
                                            value="{{ old('channel_probe_amount', $config['channel_probe_amount']) }}" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Allowed Payment Methods (opsional)</label>
                                        <input type="text" name="allowed_payment_methods" class="form-control"
                                            value="{{ old('allowed_payment_methods', $config['allowed_payment_methods']) }}"
                                            placeholder="BCA,QRIS,OVO — kosongkan untuk semua channel aktif">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i> Simpan Konfigurasi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('page-js')
        <script>
            (function () {
                var panel = document.getElementById('pgApiPanel');
                var radios = document.querySelectorAll('input[name="use_payment_gateway"]');

                function syncPanel() {
                    var usePg = document.querySelector('input[name="use_payment_gateway"]:checked');
                    panel.classList.toggle('active', usePg && usePg.value === '1');
                }

                radios.forEach(function (radio) {
                    radio.addEventListener('change', syncPanel);
                });
                syncPanel();
            })();
        </script>
    @endpush

</x-app-layout>
