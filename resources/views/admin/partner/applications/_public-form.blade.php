@php
    $application = $application ?? null;
    $lockPartnerTypeReseller = $lockPartnerTypeReseller ?? false;
    $partnerType = old('partner_type', $lockPartnerTypeReseller ? 'RESELLER' : ($application?->partner_type ?? 'AGENT'));
    $acceptedTerms = old('terms_accepted', $application?->terms_accepted ?? []);
    $field = fn (string $key, mixed $default = '') => old($key, $application?->{$key} ?? $default);
    $hasSignature = $application?->documents?->where('document_type', 'signature')->isNotEmpty() ?? false;
    $hasSignedForm = $application?->documents?->where('document_type', 'signed_registration_form')->isNotEmpty() ?? false;
    $requireSignature = ! $hasSignature;
    $requireSignedForm = ! $hasSignedForm;
    $existingSignature = $application?->documents?->firstWhere('document_type', 'signature');
    $existingSignedForm = $application?->documents?->firstWhere('document_type', 'signed_registration_form');
@endphp

<div id="partner-register-form">
    {{-- Jenis Pendaftaran --------------------------------------------- --}}
    <div class="pr-section pr-section--type pr-reveal">
        <div class="pr-section__head">
            <span class="pr-section__badge"><i class="ti ti-switch-horizontal"></i></span>
            <div>
                <h5 class="pr-section__title">
                    Jenis Pendaftaran
                    <span class="info-tip" tabindex="0" data-tip="Agen: mitra dengan pembelian & wilayah lebih besar. Reseller: pembelian per paket yang lebih ringan.">?</span>
                </h5>
                <p class="pr-section__subtitle">
                    @if ($lockPartnerTypeReseller)
                        Pendaftaran reseller atas nama Agent Anda.
                    @else
                        Pilih peran kemitraan yang sesuai dengan bisnis Anda.
                    @endif
                </p>
            </div>
        </div>
        @if ($lockPartnerTypeReseller)
            <input type="hidden" name="partner_type" value="RESELLER">
            <div class="pr-segment">
                <div class="pr-segment__item">
                    <input class="partner-type-radio" type="radio" name="partner_type_display" id="partnerTypeResellerLocked" value="RESELLER" checked disabled>
                    <label class="pr-segment__label" for="partnerTypeResellerLocked"><i class="ti ti-users"></i> Reseller</label>
                </div>
            </div>
        @else
            <label class="form-label visually-hidden">Jenis Pendaftaran <span class="text-danger">*</span></label>
            <div class="pr-segment">
                <div class="pr-segment__item">
                    <input class="partner-type-radio" type="radio" name="partner_type" id="partnerTypeAgent" value="AGENT" @checked($partnerType === 'AGENT') required>
                    <label class="pr-segment__label" for="partnerTypeAgent"><i class="ti ti-briefcase"></i> Agen</label>
                </div>
                <div class="pr-segment__item">
                    <input class="partner-type-radio" type="radio" name="partner_type" id="partnerTypeReseller" value="RESELLER" @checked($partnerType === 'RESELLER') required>
                    <label class="pr-segment__label" for="partnerTypeReseller"><i class="ti ti-users"></i> Reseller</label>
                </div>
            </div>
        @endif
    </div>

    {{-- I. Data Pribadi --------------------------------------------- --}}
    <div class="pr-section pr-reveal">
        <div class="pr-section__head">
            <span class="pr-section__badge"><i class="ti ti-user"></i></span>
            <div>
                <h5 class="pr-section__title">Data Pribadi</h5>
                <p class="pr-section__subtitle">Data diri sesuai kartu identitas resmi.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">
                    Nama Lengkap (Sesuai KTP) <span class="text-danger">*</span>
                    <span class="info-tip" tabindex="0" data-tip="Tulis nama persis seperti tercantum di KTP.">?</span>
                </label>
                <input type="text" name="name" class="form-control" value="{{ $field('name') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tempat Lahir <span class="text-danger">*</span></label>
                <input type="text" name="birth_place" class="form-control" value="{{ $field('birth_place') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                <input type="date" name="birth_date" class="form-control" value="{{ $field('birth_date', $application?->birth_date?->format('Y-m-d')) }}" max="{{ now()->toDateString() }}" required>
                @error('birth_date')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    Nomor Telp / WA <span class="text-danger">*</span>
                    <span class="info-tip" tabindex="0" data-tip="Gunakan nomor aktif WhatsApp untuk memudahkan follow-up.">?</span>
                </label>
                <input type="text" name="phone" class="form-control" value="{{ $field('phone') }}" inputmode="numeric" pattern="[0-9]+" maxlength="50" placeholder="08123456789" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                @error('phone')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    Email
                    <span class="info-tip" tabindex="0" data-tip="Opsional. Digunakan untuk mengirim notifikasi status pendaftaran.">?</span>
                </label>
                <input type="email" name="email" class="form-control" value="{{ $field('email') }}" placeholder="nama@contoh.com">
                @error('email')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label">
                    Alamat (Sesuai KTP) <span class="text-danger">*</span>
                    <span class="info-tip" tabindex="0" data-tip="Alamat sesuai KTP untuk verifikasi identitas.">?</span>
                </label>
                <textarea name="address_ktp" rows="2" class="form-control" required>{{ $field('address_ktp') }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label">
                    Alamat Domisili (Pengiriman Barang) <span class="text-danger">*</span>
                    <span class="info-tip" tabindex="0" data-tip="Alamat tempat barang dikirim. Boleh berbeda dari alamat KTP.">?</span>
                </label>
                <textarea name="address" rows="2" class="form-control" required>{{ $field('address') }}</textarea>
            </div>
            @include('admin.partials.province-city-dropdown', [
                'currentProvinceName' => old('province', $application?->province),
                'currentCityName' => old('city', $application?->city),
                'colClass' => 'col-md-4',
            ])
            <div class="col-md-4">
                <label class="form-label">Kode Pos</label>
                <input type="text" name="postal_code" class="form-control" value="{{ $field('postal_code') }}">
            </div>
            <div class="col-12">
                @include('admin.partials.location-map-picker', [
                    'latitude' => $field('latitude'),
                    'longitude' => $field('longitude'),
                ])
            </div>
        </div>
    </div>

    {{-- Marketplace ------------------------------------------------- --}}
    <div class="pr-section pr-reveal">
        <div class="pr-section__head">
            <span class="pr-section__badge"><i class="ti ti-shopping-cart"></i></span>
            <div>
                <h5 class="pr-section__title">Marketplace</h5>
                <p class="pr-section__subtitle">Kanal penjualan online yang Anda gunakan.</p>
            </div>
        </div>

        @error('marketplace')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <div class="pr-chips mb-2">
            <label class="pr-chip" for="mpTokopedia">
                <input type="checkbox" name="marketplace_tokopedia" id="mpTokopedia" value="1" @checked(old('marketplace_tokopedia', $application?->marketplace_tokopedia))>
                <span class="pr-chip__label">Tokopedia</span>
            </label>
            <label class="pr-chip" for="mpShopee">
                <input type="checkbox" name="marketplace_shopee" id="mpShopee" value="1" @checked(old('marketplace_shopee', $application?->marketplace_shopee))>
                <span class="pr-chip__label">Shopee</span>
            </label>
            <label class="pr-chip" for="mpOthers">
                <input type="checkbox" name="marketplace_others" id="mpOthers" value="1" @checked(old('marketplace_others') || old('marketplace_other', $application?->marketplace_other))>
                <span class="pr-chip__label">Lainnya</span>
            </label>
        </div>
        <div id="marketplace-other-wrap" class="{{ old('marketplace_others') || old('marketplace_other', $application?->marketplace_other) ? '' : 'd-none' }}">
            <label class="form-label">Nama Marketplace Lainnya</label>
            <input type="text" name="marketplace_other" id="marketplaceOtherText" class="form-control" value="{{ old('marketplace_other', $application?->marketplace_other) }}" placeholder="Contoh: TikTok Shop, Lazada, dll.">
            @error('marketplace_other')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
    </div>

    {{-- II. Syarat & Ketentuan — Agen ------------------------------- --}}
    <div class="pr-section pr-reveal partner-section-agent" @style(['display: none' => $partnerType !== 'AGENT'])>
        <div class="pr-section__head">
            <span class="pr-section__badge"><i class="ti ti-file-check"></i></span>
            <div>
                <h5 class="pr-section__title">Syarat dan Ketentuan Kemitraan Agen</h5>
                <p class="pr-section__subtitle">Centang seluruh poin untuk dapat mengirim formulir.</p>
            </div>
        </div>
        @php
            $agentTerms = [
                'cooperation_agreement' => 'Menandatangani Surat Perjanjian Kerjasama.',
                'initial_purchase_2mc' => 'Melakukan pembelian pertama sebanyak minimal 2 MC (Master Carton) @300 box/MC Foredi dengan harga Rp160.000/Box.',
                'monthly_purchase_1mc' => 'Membeli minimal 1 MC/Bulan, dan akan dievaluasi per 3 bulan.',
                'storage_standard' => 'Memiliki tempat penyimpanan dengan standar minimum agar kualitas produk terjaga.',
                'no_consortium' => 'Tidak melakukan konsorsium (beberapa reseller bergabung dalam 1 keagenan).',
                'no_undercut_price' => 'Tidak menjual di bawah harga sesuai yang sudah ditetapkan oleh perusahaan.',
                'serve_reseller_area' => 'Melayani reseller di area yang telah ditentukan.',
                'guide_resellers' => 'Memberikan informasi dan pengarahan kepada Reseller sesuai ketentuan yang diberikan Perusahaan.',
            ];
        @endphp
        @foreach ($agentTerms as $key => $label)
            <label class="pr-term" for="termAgent{{ $key }}">
                <input class="form-check-input term-checkbox term-agent term-agent-required" type="checkbox" name="terms_accepted[]" id="termAgent{{ $key }}" value="{{ $key }}" @checked(in_array($key, $acceptedTerms, true))>
                <span class="form-check-label">{{ $label }}</span>
            </label>
        @endforeach
        <label class="pr-term" for="termAgentNib">
            <input class="form-check-input term-checkbox term-agent" type="checkbox" name="terms_accepted[]" id="termAgentNib" value="nib_recommended" @checked(in_array('nib_recommended', $acceptedTerms, true))>
            <span class="form-check-label">Dianjurkan memiliki Nomor Induk Berusaha (NIB).</span>
        </label>
    </div>

    {{-- II. Syarat & Ketentuan — Reseller --------------------------- --}}
    <div class="pr-section pr-reveal partner-section-reseller" @style(['display: none' => $partnerType !== 'RESELLER'])>
        <div class="pr-section__head">
            <span class="pr-section__badge"><i class="ti ti-file-check"></i></span>
            <div>
                <h5 class="pr-section__title">Syarat dan Ketentuan Kemitraan Reseller</h5>
                <p class="pr-section__subtitle">Pilih paket pembelian dan centang seluruh ketentuan.</p>
            </div>
        </div>

        <label class="form-label">
            Pilih Paket Pembelian <span class="text-danger">*</span>
            <span class="info-tip" tabindex="0" data-tip="Paket menentukan jumlah box dan harga per box saat pembelian pertama.">?</span>
        </label>
        @error('reseller_package')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        @php
            $packages = [
                'A' => 'Paket A: 120 Box — Harga Rp170.000/box',
                'B' => 'Paket B: 60 Box — Harga Rp175.000/box',
                'C' => 'Paket C: 30 Box — Harga Rp180.000/box',
            ];
        @endphp
        @foreach ($packages as $code => $label)
            <label class="pr-package {{ old('reseller_package', $application?->reseller_package) === $code ? 'is-selected' : '' }}" for="package{{ $code }}">
                <input class="form-check-input" type="radio" name="reseller_package" id="package{{ $code }}" value="{{ $code }}" @checked(old('reseller_package', $application?->reseller_package) === $code)>
                <span class="form-check-label">{{ $label }}</span>
            </label>
        @endforeach

        @php
            $resellerTerms = [
                'buy_from_official_agent' => 'Membeli produk di Agen Resmi yang terdaftar di Harnica.',
                'no_undercut_price' => 'Tidak menjual di bawah harga sesuai yang sudah ditetapkan oleh perusahaan.',
                'follow_company_rules' => 'Mengikuti seluruh ketentuan yang telah ditentukan oleh perusahaan.',
            ];
        @endphp
        <div class="mt-3">
            @foreach ($resellerTerms as $key => $label)
                <label class="pr-term" for="termReseller{{ $key }}">
                    <input class="form-check-input term-checkbox term-reseller term-reseller-required" type="checkbox" name="terms_accepted[]" id="termReseller{{ $key }}" value="{{ $key }}" @checked(in_array($key, $acceptedTerms, true))>
                    <span class="form-check-label">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div id="terms-validation-alert" class="alert alert-warning py-2 d-none pr-reveal" role="alert">
        <i class="ti ti-alert-triangle me-1"></i>
        Semua syarat dan ketentuan kemitraan wajib dicentang sebelum formulir dapat dikirim.
    </div>

    @error('terms_accepted')<div class="text-danger small pr-reveal">{{ $message }}</div>@enderror

    {{-- Pernyataan & Tanda Tangan ----------------------------------- --}}
    <div class="pr-section pr-reveal">
        <div class="pr-section__head">
            <span class="pr-section__badge"><i class="ti ti-writing-sign"></i></span>
            <div>
                <h5 class="pr-section__title">Pernyataan &amp; Tanda Tangan</h5>
                <p class="pr-section__subtitle">Konfirmasi kebenaran data dan bubuhkan tanda tangan.</p>
            </div>
        </div>

        <label class="pr-term mb-3" for="declarationAccepted">
            <input class="form-check-input" type="checkbox" name="declaration_accepted" id="declarationAccepted" value="1" @checked(old('declaration_accepted', $application?->declaration_accepted)) required>
            <span class="form-check-label">
                Saya yang bertanda tangan di bawah ini menyatakan bahwa seluruh data yang saya berikan adalah benar dan saya telah membaca serta menyetujui syarat dan ketentuan yang berlaku. <span class="text-danger">*</span>
            </span>
        </label>
        @error('declaration_accepted')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

        @include('admin.partials.signature-pad', [
            'requireSignature' => $requireSignature,
            'requireSignedForm' => $requireSignedForm,
            'existingSignature' => $existingSignature,
            'existingSignedForm' => $existingSignedForm,
        ])
    </div>

    {{-- Lampiran ---------------------------------------------------- --}}
    <div class="pr-section pr-reveal">
        <div class="pr-section__head">
            <span class="pr-section__badge"><i class="ti ti-paperclip"></i></span>
            <div>
                <h5 class="pr-section__title">Lampiran Tambahan</h5>
                <p class="pr-section__subtitle">Opsional — dokumen pendukung pendaftaran.</p>
            </div>
        </div>
        <label class="form-label">
            Unggah Dokumen
            <span class="info-tip" tabindex="0" data-tip="Contoh: KTP, NIB, atau dokumen pendukung lain. Format JPG/PNG/PDF, maks. 5MB per file.">?</span>
        </label>
        <input type="file" name="documents[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.pdf">
        <small class="text-muted">Contoh: KTP, NIB, atau dokumen pendukung lain. Maks. 5MB per file.</small>
    </div>
</div>

@push('page-js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const agentSection = document.querySelector('.partner-section-agent');
        const resellerSection = document.querySelector('.partner-section-reseller');
        const radios = document.querySelectorAll('.partner-type-radio');
        const form = document.getElementById('partner-register-form')?.closest('form');
        const termsAlert = document.getElementById('terms-validation-alert');
        const submitBtn = form?.querySelector('button[type="submit"], .pr-submit');

        function setRequired(elements, enabled) {
            elements.forEach((el) => {
                if (enabled) {
                    el.setAttribute('required', 'required');
                } else {
                    el.removeAttribute('required');
                }
            });
        }

        function allTermsAccepted(isAgent) {
            const selector = isAgent ? '.term-agent-required' : '.term-reseller-required';
            const boxes = Array.from(document.querySelectorAll(selector));
            return boxes.length > 0 && boxes.every((el) => el.checked);
        }

        function resellerPackageSelected() {
            return !!document.querySelector('input[name="reseller_package"]:checked');
        }

        function updateSubmitState() {
            const type = document.querySelector('.partner-type-radio:checked')?.value || 'AGENT';
            const isAgent = type === 'AGENT';
            const termsOk = allTermsAccepted(isAgent);
            const packageOk = isAgent || resellerPackageSelected();

            if (submitBtn) {
                submitBtn.disabled = !termsOk || !packageOk;
            }

            if (termsAlert) {
                termsAlert.classList.toggle('d-none', termsOk && packageOk);
            }
        }

        function syncPackageCards() {
            document.querySelectorAll('.pr-package').forEach((label) => {
                const input = label.querySelector('input[type="radio"]');
                label.classList.toggle('is-selected', !!input && input.checked);
            });
        }

        function syncPartnerSections() {
            const type = document.querySelector('.partner-type-radio:checked')?.value || 'AGENT';
            const isAgent = type === 'AGENT';

            agentSection.style.display = isAgent ? '' : 'none';
            resellerSection.style.display = isAgent ? 'none' : '';

            document.querySelectorAll('.term-agent').forEach((el) => {
                el.disabled = !isAgent;
                if (!isAgent) el.checked = false;
            });
            document.querySelectorAll('.term-reseller').forEach((el) => {
                el.disabled = isAgent;
                if (isAgent) el.checked = false;
            });
            document.querySelectorAll('input[name="reseller_package"]').forEach((el) => {
                el.disabled = isAgent;
                if (isAgent) el.checked = false;
            });

            setRequired(document.querySelectorAll('.term-agent-required'), isAgent);
            setRequired(document.querySelectorAll('.term-reseller-required'), !isAgent);
            setRequired(document.querySelectorAll('input[name="reseller_package"]'), !isAgent);

            syncPackageCards();
            updateSubmitState();
        }

        radios.forEach((radio) => radio.addEventListener('change', syncPartnerSections));
        document.querySelectorAll('.term-checkbox, input[name="reseller_package"]').forEach((el) => {
            el.addEventListener('change', function () {
                syncPackageCards();
                updateSubmitState();
            });
        });

        form?.addEventListener('submit', function (event) {
            const type = document.querySelector('.partner-type-radio:checked')?.value || 'AGENT';
            const isAgent = type === 'AGENT';

            if (!allTermsAccepted(isAgent) || (!isAgent && !resellerPackageSelected())) {
                event.preventDefault();
                termsAlert?.classList.remove('d-none');
                (isAgent ? agentSection : resellerSection)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        const mpOthers = document.getElementById('mpOthers');
        const mpOtherWrap = document.getElementById('marketplace-other-wrap');
        const mpOtherText = document.getElementById('marketplaceOtherText');

        function syncMarketplaceOther() {
            const show = mpOthers?.checked;
            mpOtherWrap?.classList.toggle('d-none', !show);
            if (mpOtherText) {
                if (show) {
                    mpOtherText.setAttribute('required', 'required');
                } else {
                    mpOtherText.removeAttribute('required');
                    mpOtherText.value = '';
                }
            }
        }

        mpOthers?.addEventListener('change', syncMarketplaceOther);
        syncMarketplaceOther();

        syncPartnerSections();
    });
</script>
@endpush
