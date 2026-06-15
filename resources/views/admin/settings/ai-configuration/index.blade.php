<x-app-layout>

    @section('title', 'AI Chat Configuration | ')

    @push('page-css')
        <style>
            .provider-panel { display: none; }
            .provider-panel.active { display: block; }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Settings', 'url' => 'javascript:void(0);'],
                ['label' => 'AI Chat Configuration', 'active' => true],
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
                            <span class="badge bg-label-primary me-2">{{ $config['status']['provider_label'] }}</span>
                            @if ($config['status']['configured'])
                                <span class="badge bg-label-success me-2">Provider Terhubung</span>
                            @else
                                <span class="badge bg-label-warning me-2">Provider Belum Siap</span>
                            @endif
                            @if ($config['status']['widget_enabled'] && $config['status']['service_enabled'])
                                <span class="badge bg-label-success">Widget Ditampilkan</span>
                            @else
                                <span class="badge bg-label-secondary">Widget Disembunyikan</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('settings.ai-configuration.test') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="ti ti-plug-connected me-1"></i> Test Koneksi
                            </button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.ai-configuration.update') }}">
                    @csrf

                    <div class="card mb-4">
                        <h5 class="card-header">Widget Chat AI</h5>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                Atur apakah tombol floating <strong>WMS Assistant</strong> muncul di panel admin.
                                Layanan AI tetap bisa dinonaktifkan terpisah di bawah.
                            </p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="agent_widget_enabled" value="1" id="agent_widget_enabled"
                                            @checked(old('agent_widget_enabled', $config['agent']['widget_enabled']))>
                                        <label class="form-check-label" for="agent_widget_enabled">
                                            Tampilkan widget chat di panel admin
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="agent_enabled" value="1" id="agent_enabled"
                                            @checked(old('agent_enabled', $config['agent']['enabled']))>
                                        <label class="form-check-label" for="agent_enabled">
                                            Aktifkan layanan AI Chat (API &amp; backend)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <h5 class="card-header">Umum</h5>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Provider Aktif</label>
                                    <select name="provider" id="provider" class="form-select" required>
                                        <option value="deepseek" @selected(old('provider', $config['provider']) === 'deepseek')>DeepSeek</option>
                                        <option value="chatai" @selected(old('provider', $config['provider']) === 'chatai')>ChatAI</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Menu Permission</label>
                                    <input type="text" name="agent_permission_menu" class="form-control"
                                        value="{{ old('agent_permission_menu', $config['agent']['permission_menu']) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Max Tool Rounds</label>
                                    <input type="number" name="agent_max_tool_rounds" class="form-control" min="1" max="20"
                                        value="{{ old('agent_max_tool_rounds', $config['agent']['max_tool_rounds']) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Max Panjang Pesan</label>
                                    <input type="number" name="agent_max_message_length" class="form-control" min="100" max="10000"
                                        value="{{ old('agent_max_message_length', $config['agent']['max_message_length']) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Rate Limit (/menit)</label>
                                    <input type="number" name="agent_rate_limit_per_minute" class="form-control" min="1" max="300"
                                        value="{{ old('agent_rate_limit_per_minute', $config['agent']['rate_limit_per_minute']) }}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="panel-deepseek" class="provider-panel {{ old('provider', $config['provider']) === 'deepseek' ? 'active' : '' }}">
                        <div class="card mb-4">
                            <h5 class="card-header">DeepSeek</h5>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4 d-flex align-items-end">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="deepseek_enabled" value="1" id="deepseek_enabled"
                                                @checked(old('deepseek_enabled', $config['deepseek']['enabled']))>
                                            <label class="form-check-label" for="deepseek_enabled">Aktifkan DeepSeek</label>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label">API Key Saat Ini</label>
                                        <input type="text" class="form-control font-monospace mb-2"
                                            value="{{ $config['deepseek']['api_key_masked'] ?: 'Belum diisi' }}"
                                            readonly>
                                        <label class="form-label">API Key Baru</label>
                                        <input type="text" name="deepseek_api_key" class="form-control font-monospace"
                                            placeholder="Kosongkan jika tidak ingin mengubah"
                                            autocomplete="off">
                                        <small class="text-muted">Hanya isi jika ingin mengganti API key.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Base URL</label>
                                        <input type="url" name="deepseek_base_url" class="form-control"
                                            value="{{ old('deepseek_base_url', $config['deepseek']['base_url']) }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Beta URL (Strict Tools)</label>
                                        <input type="url" name="deepseek_beta_url" class="form-control"
                                            value="{{ old('deepseek_beta_url', $config['deepseek']['beta_url']) }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Model</label>
                                        <input type="text" name="deepseek_model" class="form-control"
                                            value="{{ old('deepseek_model', $config['deepseek']['model']) }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Timeout (detik)</label>
                                        <input type="number" name="deepseek_timeout" class="form-control" min="5" max="120"
                                            value="{{ old('deepseek_timeout', $config['deepseek']['timeout']) }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Max Tokens</label>
                                        <input type="number" name="deepseek_max_tokens" class="form-control" min="100" max="8000"
                                            value="{{ old('deepseek_max_tokens', $config['deepseek']['max_tokens']) }}" required>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="deepseek_use_strict_tools" value="1" id="deepseek_use_strict_tools"
                                                @checked(old('deepseek_use_strict_tools', $config['deepseek']['use_strict_tools']))>
                                            <label class="form-check-label" for="deepseek_use_strict_tools">Gunakan Strict Tools</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="panel-chatai" class="provider-panel {{ old('provider', $config['provider']) === 'chatai' ? 'active' : '' }}">
                        <div class="card mb-4">
                            <h5 class="card-header">ChatAI (OpenAI-compatible)</h5>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4 d-flex align-items-end">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="chatai_enabled" value="1" id="chatai_enabled"
                                                @checked(old('chatai_enabled', $config['chatai']['enabled']))>
                                            <label class="form-check-label" for="chatai_enabled">Aktifkan ChatAI</label>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label">API Key Saat Ini</label>
                                        <input type="text" class="form-control font-monospace mb-2"
                                            value="{{ $config['chatai']['api_key_masked'] ?: 'Belum diisi' }}"
                                            readonly>
                                        <label class="form-label">API Key Baru</label>
                                        <input type="text" name="chatai_api_key" class="form-control font-monospace"
                                            placeholder="Kosongkan jika tidak ingin mengubah"
                                            autocomplete="off">
                                        <small class="text-muted">Hanya isi jika ingin mengganti API key.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Base URL</label>
                                        <input type="url" name="chatai_base_url" class="form-control"
                                            value="{{ old('chatai_base_url', $config['chatai']['base_url']) }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Model</label>
                                        <input type="text" name="chatai_model" class="form-control"
                                            value="{{ old('chatai_model', $config['chatai']['model']) }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Timeout (detik)</label>
                                        <input type="number" name="chatai_timeout" class="form-control" min="5" max="120"
                                            value="{{ old('chatai_timeout', $config['chatai']['timeout']) }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Max Tokens</label>
                                        <input type="number" name="chatai_max_tokens" class="form-control" min="100" max="8000"
                                            value="{{ old('chatai_max_tokens', $config['chatai']['max_tokens']) }}" required>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="chatai_use_strict_tools" value="1" id="chatai_use_strict_tools"
                                                @checked(old('chatai_use_strict_tools', $config['chatai']['use_strict_tools']))>
                                            <label class="form-check-label" for="chatai_use_strict_tools">Gunakan Strict Tools</label>
                                        </div>
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
                var providerSelect = document.getElementById('provider');
                var panels = {
                    deepseek: document.getElementById('panel-deepseek'),
                    chatai: document.getElementById('panel-chatai'),
                };

                function syncPanels() {
                    var selected = providerSelect.value;
                    Object.keys(panels).forEach(function (key) {
                        panels[key].classList.toggle('active', key === selected);
                    });
                }

                providerSelect.addEventListener('change', syncPanels);
                syncPanels();
            })();
        </script>
    @endpush

</x-app-layout>
