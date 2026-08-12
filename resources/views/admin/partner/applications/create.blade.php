<x-app-layout>
    @section('title', 'Tambah Pendaftaran Partner | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    @endpush

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/css/partner-register.css') }}" />
    @endpush

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Customer'],
            ['label' => 'Network', 'url' => route('partner.reports.index')],
            ['label' => 'Applications', 'url' => route('partner.applications.index')],
            ['label' => 'Tambah', 'active' => true],
        ]" />

        <form method="POST" action="{{ route('partner.applications.store') }}" enctype="multipart/form-data" id="partnerApplicationForm">
            @csrf

            <div class="pr-page pr-page--admin">
                <div class="pr-aurora" aria-hidden="true">
                    <span></span><span></span><span></span>
                </div>

                <div class="pr-shell">
                    <div class="pr-card">
                        <div class="pr-hero pr-reveal pr-reveal--instant">
                            <div class="pr-brandmark">
                                <img src="{{ $appTheme['logo_url'] ?? asset('assets/img/harnica/logo.png') }}"
                                     alt="Harnica"
                                     data-brand-logo="{{ $appTheme['logo_url'] ?? asset('assets/img/harnica/logo.png') }}"
                                     width="180"
                                     height="48"
                                     loading="eager"
                                     decoding="async"
                                     onerror="this.style.display='none'; this.parentElement.insertAdjacentHTML('beforeend','<strong style=\'font-size:1.25rem;letter-spacing:.02em;color:#2f3a44\'>HARNICA</strong>');">
                            </div>
                            <p class="pr-hero__subtitle">CV. Suhara Botanica<br class="d-sm-none"> Komplek Setrasari Mall C2 No.27, Bandung</p>
                            <h1 class="pr-hero__title" id="form-title">Formulir Registrasi Agen</h1>
                            <p class="pr-hero__lead">Lengkapi data sesuai formulir resmi Harnica. Pendaftaran akan masuk ke daftar application untuk ditindaklanjuti.</p>
                        </div>

                        @if ($errors->any())
                            <x-alert type="danger" class="mb-3 pr-reveal">
                                <ul class="m-0">
                                    @foreach ($errors->all() as $e)
                                        <li>{{ $e }}</li>
                                    @endforeach
                                </ul>
                            </x-alert>
                        @endif

                        @include('admin.partner.applications._public-form', [
                            'lockPartnerTypeReseller' => $lockPartnerTypeReseller ?? false,
                        ])
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="submit" class="btn btn-primary me-2" id="partnerSubmitBtn" disabled>
                    Submit
                </button>
                <a href="{{ route('partner.applications.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>

    @push('page-js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const title = document.getElementById('form-title');

            function syncFormTitle(isReseller) {
                if (!title) return;
                title.textContent = isReseller
                    ? 'Formulir Registrasi Reseller'
                    : 'Formulir Registrasi Agen';
            }

            document.querySelectorAll('.partner-type-radio').forEach((radio) => {
                radio.addEventListener('change', function () {
                    syncFormTitle(this.value === 'RESELLER');
                });
            });

            const checked = document.querySelector('.partner-type-radio:checked');
            if (checked) {
                syncFormTitle(checked.value === 'RESELLER');
            } else {
                const hiddenType = document.querySelector('input[name="partner_type"][type="hidden"]');
                if (hiddenType?.value === 'RESELLER') {
                    syncFormTitle(true);
                }
            }

            const revealEls = Array.from(document.querySelectorAll('.pr-reveal:not(.pr-reveal--instant)'));
            revealEls.forEach((el, index) => { el.dataset.revealIndex = index; });

            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            const el = entry.target;
                            const delay = (parseInt(el.dataset.revealIndex, 10) || 0) * 40;
                            setTimeout(() => el.classList.add('is-in'), delay);
                            observer.unobserve(el);
                        }
                    });
                }, { threshold: 0.05, rootMargin: '0px 0px -20px 0px' });
                revealEls.forEach((el) => observer.observe(el));
            } else {
                revealEls.forEach((el) => el.classList.add('is-in'));
            }
        });
    </script>
    @endpush
</x-app-layout>
