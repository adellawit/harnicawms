<x-app-layout>
    @section('title', 'Appearance & Theme | ')

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Settings', 'url' => 'javascript:void(0);'],
            ['label' => 'Appearance & Theme', 'active' => true],
        ]" />

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

        <form method="POST" action="{{ route('settings.theme-configuration.update') }}" enctype="multipart/form-data" id="theme-settings-form">
            @csrf

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Warna Tema</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Atur warna primary & secondary untuk seluruh aplikasi. Pilih ekstraksi otomatis dari logo atau warna kustom.
                            </p>

                            <div class="mb-3">
                                <label class="form-label d-block">Sumber Warna</label>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="color_mode" id="color_mode_logo" value="logo_extract"
                                        @checked(old('color_mode', $theme->color_mode) === 'logo_extract')>
                                    <label class="form-check-label" for="color_mode_logo">
                                        Otomatis dari logo <x-info-tip text="Warna dominan diekstrak dari logo saat halaman dimuat. Cocok untuk branding konsisten." />
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="color_mode" id="color_mode_custom" value="custom"
                                        @checked(old('color_mode', $theme->color_mode) === 'custom')>
                                    <label class="form-check-label" for="color_mode_custom">
                                        Warna kustom <x-info-tip text="Gunakan color picker di bawah. Warna diterapkan langsung dari server." />
                                    </label>
                                </div>
                            </div>

                            <div class="row g-3 theme-custom-field">
                                <div class="col-md-6">
                                    <label class="form-label" for="primary_color">Primary Color</label>
                                    <input type="color" class="form-control form-control-color w-100" name="primary_color" id="primary_color"
                                        value="{{ old('primary_color', $theme->primary_color) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="secondary_color">Secondary Color</label>
                                    <input type="color" class="form-control form-control-color w-100" name="secondary_color" id="secondary_color"
                                        value="{{ old('secondary_color', $theme->secondary_color) }}">
                                </div>
                            </div>

                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="extractFromLogoBtn">
                                <i class="ti ti-color-swatch me-1"></i> Ekstrak dari Logo Preview
                            </button>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Logo & Favicon</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="theme_logo">Logo Aplikasi</label>
                                    <input type="file" class="form-control" name="logo" id="theme_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                                    <small class="text-muted">PNG/JPG/SVG. Maks 2MB.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="theme_favicon">Favicon</label>
                                    <input type="file" class="form-control" name="favicon" id="theme_favicon" accept="image/png,image/x-icon,image/jpeg">
                                    <small class="text-muted">ICO/PNG. Maks 512KB.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Efek Tampilan</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="glass_enabled" id="glass_enabled" value="1"
                                    @checked(old('glass_enabled', $theme->glass_enabled))>
                                <label class="form-check-label" for="glass_enabled">
                                    Glassmorphism / Liquid Glass <x-info-tip text="Efek kaca buram pada card, navbar, dan sidebar. Tetap menjaga keterbacaan tabel operasional." />
                                </label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="motion_enabled" id="motion_enabled" value="1"
                                    @checked(old('motion_enabled', $theme->motion_enabled))>
                                <label class="form-check-label" for="motion_enabled">
                                    Animasi Transisi Halus <x-info-tip text="Transisi subtle pada tombol, card, dan menu untuk kesan premium." />
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Simpan Pengaturan
                    </button>
                </div>

                <div class="col-lg-5">
                    <div class="card sticky-top" style="top: 5.5rem;">
                        <div class="card-header">
                            <h5 class="mb-0">Preview</h5>
                        </div>
                        <div class="card-body theme-preview-panel">
                            <div class="text-center mb-3">
                                <img src="{{ $themeView['logo_url'] }}" alt="Logo" id="previewLogo"
                                     data-brand-logo="{{ $themeView['logo_url'] }}"
                                     style="height: 48px; width: auto; max-width: 180px; object-fit: contain;">
                            </div>
                            <div class="theme-preview-card mb-3">
                                <div class="d-flex gap-2 mb-3">
                                    <span id="previewPrimary" class="rounded" style="width:40px;height:40px;background:{{ $theme->primary_color }}"></span>
                                    <span id="previewSecondary" class="rounded" style="width:40px;height:40px;background:{{ $theme->secondary_color }}"></span>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm me-2">Primary</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm">Secondary</button>
                            </div>
                            <p class="text-muted small mb-0">
                                Perubahan warna kustom terlihat langsung di preview. Mode logo akan diterapkan saat halaman dimuat ulang.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @push('page-js')
        <script src="{{ asset('assets/js/theme-settings.js') }}"></script>
    @endpush
</x-app-layout>
