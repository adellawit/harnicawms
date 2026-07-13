<x-app-layout>
    @section('title', 'Design System | ')

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/css/design-system-showcase.css') }}" />
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y ds-showcase">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Design System', 'active' => true],
            ]"
            title="Design System"
            subtitle="Token desain, komponen Blade, dan pola UI WMS — sinkron dengan tema aktif."
        />

        <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="ds-status-pill text-primary" style="background: rgba(var(--brand-primary-rgb), 0.12);">
                Glass {{ ($themeView['glass_enabled'] ?? true) ? 'ON' : 'OFF' }}
            </span>
            <span class="ds-status-pill text-primary" style="background: rgba(var(--brand-primary-rgb), 0.12);">
                Motion {{ ($themeView['motion_enabled'] ?? true) ? 'ON' : 'OFF' }}
            </span>
            <span class="ds-status-pill text-secondary" style="background: rgba(var(--brand-secondary-rgb), 0.12);">
                Mode: {{ ($themeView['color_mode'] ?? 'logo_extract') === 'custom' ? 'Kustom' : 'Logo' }}
            </span>
            @if (Route::has('settings.theme-configuration.index.view'))
                <a href="{{ route('settings.theme-configuration.index.view') }}" class="btn btn-sm btn-label-primary ms-auto">
                    <i class="ti ti-palette me-1"></i> Atur Tema
                </a>
            @endif
        </div>

        <nav class="ds-nav">
            <ul class="nav nav-pills flex-wrap gap-1">
                <li class="nav-item"><a class="nav-link active" href="#ds-tokens">Tokens</a></li>
                <li class="nav-item"><a class="nav-link" href="#ds-surfaces">Surfaces</a></li>
                <li class="nav-item"><a class="nav-link" href="#ds-typography">Typography</a></li>
                <li class="nav-item"><a class="nav-link" href="#ds-buttons">Buttons</a></li>
                <li class="nav-item"><a class="nav-link" href="#ds-forms">Forms</a></li>
                <li class="nav-item"><a class="nav-link" href="#ds-feedback">Feedback</a></li>
                <li class="nav-item"><a class="nav-link" href="#ds-data">Data</a></li>
                <li class="nav-item"><a class="nav-link" href="#ds-overlays">Overlays</a></li>
            </ul>
        </nav>

        {{-- TOKENS --}}
        <section id="ds-tokens" class="ds-section ds-reveal">
            <x-card title="Design Tokens">
                <p class="text-muted small mb-4">
                    Variabel CSS global dari <code>design-system.css</code> &amp; <code>theme-vars</code>.
                    Nilai di bawah mengikuti tema aktif.
                </p>

                <div class="ds-subsection">
                    <div class="ds-section-title">Brand Colors</div>
                    <div class="ds-token-grid">
                        @foreach ([
                            ['label' => 'Primary', 'bg' => $themeView['primary'], 'value' => $themeView['primary']],
                            ['label' => 'Primary 600', 'bg' => $themeView['primary_600'], 'value' => $themeView['primary_600']],
                            ['label' => 'Primary 700', 'bg' => $themeView['primary_700'], 'value' => $themeView['primary_700']],
                            ['label' => 'Primary Soft', 'bg' => $themeView['primary_soft'], 'value' => $themeView['primary_soft']],
                            ['label' => 'Secondary', 'bg' => $themeView['secondary'], 'value' => $themeView['secondary']],
                            ['label' => 'Secondary 600', 'bg' => $themeView['secondary_600'], 'value' => $themeView['secondary_600']],
                            ['label' => 'Secondary Soft', 'bg' => $themeView['secondary_soft'], 'value' => $themeView['secondary_soft']],
                            ['label' => 'Ink', 'bg' => '#2f3a44', 'value' => '#2f3a44'],
                            ['label' => 'Ink Soft', 'bg' => '#5a6672', 'value' => '#5a6672'],
                        ] as $swatch)
                            <div>
                                <div class="ds-swatch" style="background: {{ $swatch['bg'] }}; color: {{ in_array($swatch['label'], ['Primary Soft', 'Secondary Soft']) ? 'var(--brand-ink)' : '#fff' }};">
                                    <span class="ds-swatch-label">{{ $swatch['label'] }}</span>
                                    <span class="ds-swatch-value">{{ $swatch['value'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="ds-subsection">
                    <div class="ds-section-title">Border Radius</div>
                    <div class="d-flex flex-wrap gap-4">
                        <div class="text-center">
                            <div class="ds-radius-demo" style="border-radius: var(--brand-radius-sm);">SM</div>
                            <small class="text-muted d-block mt-1">12px</small>
                        </div>
                        <div class="text-center">
                            <div class="ds-radius-demo" style="border-radius: var(--brand-radius-md);">MD</div>
                            <small class="text-muted d-block mt-1">16px</small>
                        </div>
                        <div class="text-center">
                            <div class="ds-radius-demo" style="border-radius: var(--brand-radius-lg);">LG</div>
                            <small class="text-muted d-block mt-1">24px</small>
                        </div>
                    </div>
                </div>

                <div class="ds-subsection">
                    <div class="ds-section-title">Theme Preview</div>
                    <div class="theme-preview-panel">
                        <div class="theme-preview-card">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <img src="{{ $themeView['logo_url'] ?? $themeView['default_logo_url'] }}" alt="Logo" height="36">
                                <div>
                                    <strong>Brand Preview</strong>
                                    <div class="text-muted small">Glass card dengan token aktif</div>
                                </div>
                            </div>
                            <div class="ds-demo-row">
                                <x-button color="primary" size="sm">Primary</x-button>
                                <x-button color="primary" variant="outline" size="sm">Outline</x-button>
                                <x-badge color="primary">Badge</x-badge>
                            </div>
                        </div>
                    </div>
                </div>
            </x-card>
        </section>

        {{-- SURFACES --}}
        <section id="ds-surfaces" class="ds-section ds-reveal">
            <x-card title="Surfaces & Glass">
                <div class="ds-surface-compare">
                    <div class="ds-surface-compare-labels row g-2 mb-2">
                        <div class="col-md-6">
                            <div class="ds-section-title mb-0">Standard Card</div>
                        </div>
                        <div class="col-md-6">
                            <div class="ds-section-title mb-0">Surface Glass <span class="ds-code">.surface-glass</span></div>
                        </div>
                    </div>
                    <div class="row g-3 align-items-stretch">
                        <div class="col-md-6 d-flex">
                            <div class="ds-surface-panel card flex-fill">
                                <div class="card-body">
                                    <strong class="d-block mb-1">Standard Card</strong>
                                    <p class="mb-0 small text-muted">Card Bootstrap default — mengikuti glass mode jika aktif.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex">
                            <div class="ds-surface-panel surface-glass surface-glass-demo flex-fill">
                                <strong class="d-block mb-1">Glass Surface</strong>
                                <p class="mb-0 small text-muted">Backdrop blur + border transparan untuk panel premium.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </x-card>
        </section>

        {{-- TYPOGRAPHY --}}
        <section id="ds-typography" class="ds-section ds-reveal">
            <x-card title="Typography">
                <div class="ds-type-scale">
                    <h1 class="fw-bold">Heading 1 — DM Sans / Inter</h1>
                    <h2 class="fw-bold">Heading 2</h2>
                    <h3 class="fw-semibold">Heading 3</h3>
                    <h4 class="fw-semibold">Heading 4</h4>
                    <h5>Heading 5</h5>
                    <h6>Heading 6</h6>
                    <p class="mb-2">Body text — paragraf standar untuk konten form dan tabel.</p>
                    <p class="text-muted small mb-0">Small muted — helper text, caption, metadata.</p>
                </div>
            </x-card>
        </section>

        {{-- BUTTONS --}}
        <section id="ds-buttons" class="ds-section ds-reveal">
            <x-card title="Buttons & Badges">
                <div class="ds-subsection">
                    <div class="ds-section-title"><span class="ds-code">&lt;x-button&gt;</span> Variants</div>
                    <div class="ds-demo-row mb-3">
                        <x-button color="primary">Primary</x-button>
                        <x-button color="success">Success</x-button>
                        <x-button color="warning">Warning</x-button>
                        <x-button color="info">Info</x-button>
                        <x-button color="danger">Danger</x-button>
                        <x-button color="secondary">Secondary</x-button>
                    </div>
                    <div class="ds-demo-row mb-3">
                        <x-button color="primary" variant="outline">Outline</x-button>
                        <x-button color="success" variant="outline">Outline</x-button>
                        <x-button color="primary" variant="label">Label</x-button>
                        <x-button color="success" variant="label">Label</x-button>
                    </div>
                    <div class="ds-demo-row">
                        <x-button color="primary" size="sm" icon="ti-check">Small</x-button>
                        <x-button color="primary" icon="ti-plus">Default</x-button>
                        <x-button color="primary" size="lg" icon="ti-arrow-right" iconPosition="end">Large</x-button>
                    </div>
                </div>

                <div class="ds-subsection">
                    <div class="ds-section-title"><span class="ds-code">&lt;x-badge&gt;</span></div>
                    <div class="ds-demo-row mb-2">
                        <x-badge color="primary">Primary</x-badge>
                        <x-badge color="success">Success</x-badge>
                        <x-badge color="warning">Warning</x-badge>
                        <x-badge color="danger">Danger</x-badge>
                        <x-badge color="secondary">Secondary</x-badge>
                    </div>
                    <div class="ds-demo-row">
                        <x-badge variant="solid" color="primary">Solid</x-badge>
                        <x-badge variant="solid" color="success">Solid</x-badge>
                        <x-button color="primary" size="sm">Notifikasi <x-badge color="light" class="ms-1">4</x-badge></x-button>
                    </div>
                </div>
            </x-card>
        </section>

        {{-- FORMS --}}
        <section id="ds-forms" class="ds-section ds-reveal">
            <x-card title="Form Components">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="ds-section-title"><span class="ds-code">&lt;x-form-group&gt;</span></div>
                        <x-form-group label="Nama Lengkap" name="ds_name" required>
                            <input type="text" class="form-control" id="ds_name" placeholder="Masukkan nama">
                        </x-form-group>
                        <x-form-group label="Email" name="ds_email" class="mt-3">
                            <input type="email" class="form-control" id="ds_email" placeholder="name@example.com">
                        </x-form-group>
                    </div>
                    <div class="col-lg-6">
                        <div class="ds-section-title">Select & Textarea</div>
                        <div class="mb-3">
                            <label class="form-label" for="ds_select">Kategori</label>
                            <select class="form-select" id="ds_select">
                                <option selected>Pilih...</option>
                                <option>Opsi A</option>
                                <option>Opsi B</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="ds_textarea">Catatan</label>
                            <textarea class="form-control" id="ds_textarea" rows="2" placeholder="Tulis catatan..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="ds-subsection">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="ds-section-title"><span class="ds-code">&lt;x-checkbox&gt;</span></div>
                            <x-checkbox id="ds_cb1" label="Default" />
                            <x-checkbox id="ds_cb2" label="Checked" :checked="true" />
                            <x-checkbox id="ds_cb3" label="Disabled" :disabled="true" />
                        </div>
                        <div class="col-md-4">
                            <div class="ds-section-title"><span class="ds-code">&lt;x-radio&gt;</span></div>
                            <x-radio name="ds_radio" id="ds_r1" label="Opsi 1" :checked="true" />
                            <x-radio name="ds_radio" id="ds_r2" label="Opsi 2" />
                            <x-radio name="ds_radio" id="ds_r3" label="Disabled" :disabled="true" />
                        </div>
                        <div class="col-md-4">
                            <div class="ds-section-title"><span class="ds-code">&lt;x-info-tip&gt;</span></div>
                            <p class="small mb-2">
                                Label dengan tooltip
                                <x-info-tip text="Tooltip bantuan global dari design-system.css — hover atau fokus keyboard." />
                            </p>
                            <p class="small text-muted mb-0">
                                Gunakan untuk menjelaskan field tanpa memakan ruang.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="ds-subsection">
                    <div class="ds-section-title"><span class="ds-code">&lt;x-input-group&gt;</span></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-input-group label="Username">
                                <span class="input-group-text">@</span>
                                <input type="text" class="form-control" placeholder="username">
                            </x-input-group>
                        </div>
                        <div class="col-md-6">
                            <x-input-group label="Cari">
                                <input type="text" class="form-control" placeholder="Ketik kata kunci...">
                                <x-button color="primary" type="button" icon="ti-search">Cari</x-button>
                            </x-input-group>
                        </div>
                    </div>
                </div>

                <div class="ds-subsection">
                    <div class="ds-section-title">Validation States</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="ds_valid">Valid</label>
                            <input type="text" class="form-control is-valid" id="ds_valid" value="Benar">
                            <div class="valid-feedback">Terlihat bagus!</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ds_invalid">Invalid</label>
                            <input type="text" class="form-control is-invalid" id="ds_invalid" value="Salah">
                            <div class="invalid-feedback">Harap isi dengan benar.</div>
                        </div>
                    </div>
                </div>
            </x-card>
        </section>

        {{-- FEEDBACK --}}
        <section id="ds-feedback" class="ds-section ds-reveal">
            <x-card title="Feedback">
                <div class="ds-subsection">
                    <div class="ds-section-title"><span class="ds-code">&lt;x-alert&gt;</span></div>
                    <x-alert type="success" class="mb-2">Operasi berhasil disimpan.</x-alert>
                    <x-alert type="warning" class="mb-2">Perhatian — data belum lengkap.</x-alert>
                    <x-alert type="danger" class="mb-2">Terjadi kesalahan saat memproses.</x-alert>
                    <x-alert type="info">Informasi tambahan untuk pengguna.</x-alert>
                </div>

                <div class="ds-subsection">
                    <div class="ds-section-title"><span class="ds-code">&lt;x-progress&gt;</span> &amp; <span class="ds-code">&lt;x-spinner&gt;</span></div>
                    <x-progress :value="60" color="primary" class="mb-3" />
                    <div class="ds-demo-row">
                        <x-spinner color="primary" />
                        <x-spinner color="success" />
                        <x-spinner type="grow" color="primary" />
                    </div>
                </div>
            </x-card>
        </section>

        {{-- DATA --}}
        <section id="ds-data" class="ds-section ds-reveal">
            <x-card title="Data Display">
                <div class="ds-subsection">
                    <div class="ds-section-title"><span class="ds-code">&lt;x-table&gt;</span></div>
                    <x-table :striped="true">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>John Doe</td>
                                <td><x-badge color="success">Aktif</x-badge></td>
                                <td>
                                    <x-button color="primary" size="sm" icon="ti-edit">Edit</x-button>
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Jane Smith</td>
                                <td><x-badge color="warning">Pending</x-badge></td>
                                <td>
                                    <x-button color="primary" size="sm" icon="ti-edit">Edit</x-button>
                                </td>
                            </tr>
                        </tbody>
                    </x-table>
                </div>

                <div class="ds-subsection">
                    <div class="ds-section-title"><span class="ds-code">&lt;x-card&gt;</span> dengan Footer</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-card title="Card Header">
                                <p class="mb-0 small">Konten card dengan header dari prop <code>title</code>.</p>
                            </x-card>
                        </div>
                        <div class="col-md-6">
                            <x-card>
                                <p class="mb-0 small">Card dengan slot footer.</p>
                                <x-slot name="footer">
                                    <small class="text-muted">Diperbarui 3 menit lalu</small>
                                </x-slot>
                            </x-card>
                        </div>
                    </div>
                </div>
            </x-card>
        </section>

        {{-- OVERLAYS --}}
        <section id="ds-overlays" class="ds-section ds-reveal">
            <x-card title="Overlays & Navigation">
                <div class="ds-subsection">
                    <div class="ds-section-title"><span class="ds-code">&lt;x-modal&gt;</span></div>
                    <p class="small text-muted mb-3">Modal ditempatkan di luar container agar overlay ter-center di viewport.</p>
                    <x-button color="primary" data-bs-toggle="modal" data-bs-target="#dsExampleModal">
                        Buka Modal
                    </x-button>
                </div>

                <div class="ds-subsection">
                    <div class="ds-section-title"><span class="ds-code">&lt;x-confirm-modal&gt;</span></div>
                    <x-button color="danger" variant="outline" data-bs-toggle="modal" data-bs-target="#dsConfirmModal">
                        Konfirmasi Hapus
                    </x-button>
                </div>

                <div class="ds-subsection">
                    <div class="ds-section-title"><span class="ds-code">&lt;x-tabs&gt;</span> / <span class="ds-code">&lt;x-tab-pane&gt;</span></div>
                    <x-tabs :tabs="[
                        ['id' => 'ds-tab-1', 'label' => 'Umum', 'active' => true],
                        ['id' => 'ds-tab-2', 'label' => 'Detail'],
                        ['id' => 'ds-tab-3', 'label' => 'Riwayat'],
                    ]">
                        <x-tab-pane id="ds-tab-1" :active="true">
                            <p class="mt-3 mb-0 small">Konten tab umum.</p>
                        </x-tab-pane>
                        <x-tab-pane id="ds-tab-2">
                            <p class="mt-3 mb-0 small">Konten tab detail.</p>
                        </x-tab-pane>
                        <x-tab-pane id="ds-tab-3">
                            <p class="mt-3 mb-0 small">Konten tab riwayat.</p>
                        </x-tab-pane>
                    </x-tabs>
                </div>

                <div class="ds-subsection">
                    <div class="ds-section-title">Breadcrumb & Pagination</div>
                    <nav aria-label="breadcrumb" class="mb-3">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item"><a href="#">Partner</a></li>
                            <li class="breadcrumb-item active">Design System</li>
                        </ol>
                    </nav>
                    <nav aria-label="Pagination">
                        <ul class="pagination mb-0">
                            <li class="page-item"><a class="page-link" href="#">Prev</a></li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">Next</a></li>
                        </ul>
                    </nav>
                </div>
            </x-card>
        </section>

    </div>

    {{-- Modals di luar container — hindari offset dari transform/overflow ancestor --}}
    <x-modal id="dsExampleModal" title="Contoh Modal">
        <p class="mb-3">Komponen <code>x-modal</code> dengan slot body dan footer.</p>
        <x-form-group label="Nama" name="modal_demo_name">
            <input type="text" class="form-control" id="modal_demo_name" placeholder="Masukkan nama...">
        </x-form-group>
        <x-slot name="footer">
            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Tutup</button>
            <x-button color="primary" type="button" data-bs-dismiss="modal">Simpan</x-button>
        </x-slot>
    </x-modal>

    <x-confirm-modal
        id="dsConfirmModal"
        title="Hapus data?"
        action="#"
        method="DELETE"
        confirmText="Ya, Hapus"
        confirmClass="btn-danger"
    >
        <p class="mb-0">Tindakan ini tidak dapat dibatalkan. Data akan dihapus permanen.</p>
    </x-confirm-modal>

    @push('page-js')
        <script>
            document.querySelectorAll('.ds-showcase .ds-nav .nav-link').forEach(function (link) {
                link.addEventListener('click', function () {
                    document.querySelectorAll('.ds-showcase .ds-nav .nav-link').forEach(function (l) {
                        l.classList.remove('active');
                    });
                    link.classList.add('active');
                });
            });
        </script>
    @endpush
</x-app-layout>
