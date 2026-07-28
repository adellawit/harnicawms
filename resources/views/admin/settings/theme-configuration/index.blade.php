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
                            <h5 class="mb-0">Warna Surface</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Opsional: override warna navbar, sidebar, dan background halaman admin.
                                Matikan override untuk kembali ke default template. Teks/ikon menyesuaikan otomatis.
                            </p>

                            @php
                                $navbarOverride = old('override_navbar', filled($theme->navbar_color));
                                $sidebarOverride = old('override_sidebar', filled($theme->sidebar_color));
                                $backgroundOverride = old('override_background', filled($theme->background_color));
                            @endphp

                            <div class="mb-3">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input surface-override-toggle" type="checkbox"
                                           name="override_navbar" id="override_navbar" value="1"
                                           data-target="navbar_color"
                                           @checked($navbarOverride)>
                                    <label class="form-check-label" for="override_navbar">
                                        Override Navbar <x-info-tip text="Aktifkan untuk memilih warna latar navbar admin." />
                                    </label>
                                </div>
                                <div class="surface-color-field ms-4" data-for="navbar_color">
                                    <label class="form-label" for="navbar_color">Navbar Color</label>
                                    <input type="color" class="form-control form-control-color w-100" name="navbar_color" id="navbar_color"
                                           value="{{ old('navbar_color', $theme->navbar_color ?: '#FFFFFF') }}"
                                           @disabled(! $navbarOverride)>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input surface-override-toggle" type="checkbox"
                                           name="override_sidebar" id="override_sidebar" value="1"
                                           data-target="sidebar_color"
                                           @checked($sidebarOverride)>
                                    <label class="form-check-label" for="override_sidebar">
                                        Override Sidebar <x-info-tip text="Aktifkan untuk memilih warna latar sidebar admin." />
                                    </label>
                                </div>
                                <div class="surface-color-field ms-4" data-for="sidebar_color">
                                    <label class="form-label" for="sidebar_color">Sidebar Color</label>
                                    <input type="color" class="form-control form-control-color w-100" name="sidebar_color" id="sidebar_color"
                                           value="{{ old('sidebar_color', $theme->sidebar_color ?: '#FFFFFF') }}"
                                           @disabled(! $sidebarOverride)>
                                </div>
                            </div>

                            <div class="mb-0">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input surface-override-toggle" type="checkbox"
                                           name="override_background" id="override_background" value="1"
                                           data-target="background_color"
                                           @checked($backgroundOverride)>
                                    <label class="form-check-label" for="override_background">
                                        Override Background <x-info-tip text="Aktifkan untuk memilih warna background area konten admin." />
                                    </label>
                                </div>
                                <div class="surface-color-field ms-4" data-for="background_color">
                                    <label class="form-label" for="background_color">Background Color</label>
                                    <input type="color" class="form-control form-control-color w-100" name="background_color" id="background_color"
                                           value="{{ old('background_color', $theme->background_color ?: '#F4F6F9') }}"
                                           @disabled(! $backgroundOverride)>
                                </div>
                            </div>
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

                            <div class="theme-surface-preview border rounded overflow-hidden mb-3" style="height: 140px;">
                                <div id="previewNavbar" class="px-2 py-1 small fw-semibold"
                                     style="background:{{ $theme->navbar_color ?: '#fff' }};color:#2f3a44;border-bottom:1px solid rgba(0,0,0,.06);">
                                    Navbar
                                </div>
                                <div class="d-flex" style="height: calc(100% - 28px);">
                                    <div id="previewSidebar" class="small p-2"
                                         style="width:34%;background:{{ $theme->sidebar_color ?: '#fff' }};color:#2f3a44;border-right:1px solid rgba(0,0,0,.06);">
                                        Sidebar
                                    </div>
                                    <div id="previewPageBg" class="flex-grow-1 small p-2 text-muted"
                                         style="background:{{ $theme->background_color ?: '#f4f6f9' }};">
                                        Content
                                    </div>
                                </div>
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
