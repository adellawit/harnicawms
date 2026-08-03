<x-app-layout>
    @section('title', 'Tambah Ongkir | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}">
        <style>
            .tooltip.shipping-rate-tooltip .tooltip-inner {
                max-width: 280px;
                text-align: left;
            }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Master Data'],
            ['label' => 'Master Ongkir', 'url' => route('shipping-rate.index.view')],
            ['label' => 'Tambah', 'active' => true],
        ]" />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <h5 class="card-header fw-bold">Tambah Tarif Ongkir</h5>
            <form method="POST" action="{{ route('shipping-rate.insert.data') }}" id="postForm">
                @csrf
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    <div class="row g-3">
                        @include('admin.master-data.shipping-rate._city-select', [
                            'fieldId' => 'origin_city_id',
                            'fieldName' => 'origin_city_id',
                            'label' => 'Kota Asal',
                            'selectedId' => old('origin_city_id'),
                            'tooltip' => 'Kota pengiriman (gudang/cabang). Cari minimal 2 karakter.',
                        ])
                        @include('admin.master-data.shipping-rate._city-select', [
                            'fieldId' => 'destination_city_id',
                            'fieldName' => 'destination_city_id',
                            'label' => 'Kota Tujuan',
                            'selectedId' => old('destination_city_id'),
                            'tooltip' => 'Kota penerima. Harus berbeda dari kota asal.',
                        ])

                        <div class="col-md-4">
                            <label class="form-label" for="courier_code">
                                Kurir <span class="text-danger">*</span>
                                <i class="ti ti-help-circle text-primary ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="shipping-rate-tooltip" data-bs-title="Ekspedisi untuk rute ini (JNE, J&T, SiCepat, dll)." title="Ekspedisi untuk rute ini (JNE, J&T, SiCepat, dll)."></i>
                            </label>
                            <select id="courier_code" name="courier_code" class="form-select select2" required>
                                @foreach($couriers as $code => $label)
                                    <option value="{{ $code }}" @selected(old('courier_code') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Ekspedisi untuk rute ini (JNE, J&T, SiCepat, dll).</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="service_code">
                                Kode Layanan <span class="text-danger">*</span>
                                <i class="ti ti-help-circle text-primary ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="shipping-rate-tooltip" data-bs-title="Kode layanan kurir, contoh REG, YES, ECO." title="Kode layanan kurir, contoh REG, YES, ECO."></i>
                            </label>
                            <input type="text" id="service_code" name="service_code" class="form-control" value="{{ old('service_code', 'REG') }}" required maxlength="30">
                            <div class="form-text">Kode layanan kurir, contoh REG, YES, ECO.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="service_name">
                                Nama Layanan
                                <i class="ti ti-help-circle text-primary ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="shipping-rate-tooltip" data-bs-title="Label tampilan. Jika kosong, mengikuti kode layanan." title="Label tampilan. Jika kosong, mengikuti kode layanan."></i>
                            </label>
                            <input type="text" id="service_name" name="service_name" class="form-control" value="{{ old('service_name', 'Reguler') }}" maxlength="100">
                            <div class="form-text">Label tampilan. Jika kosong, mengikuti kode layanan.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="base_amount">
                                Base Amount (Rp) <span class="text-danger">*</span>
                                <i class="ti ti-help-circle text-primary ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="shipping-rate-tooltip" data-bs-title="Biaya dasar. Total = base + (ceil(kg) x per kg)." title="Biaya dasar. Total = base + (ceil(kg) x per kg)."></i>
                            </label>
                            <input type="text" id="base_amount" name="base_amount" class="form-control number-format" inputmode="decimal" value="{{ format_number(old('base_amount', 0), 2, true) }}" required>
                            <div class="form-text">Biaya dasar. Total = base + (ceil(kg) x per kg).</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="per_kg_amount">
                                Per Kg (Rp) <span class="text-danger">*</span>
                                <i class="ti ti-help-circle text-primary ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="shipping-rate-tooltip" data-bs-title="Tambahan per kilogram (dibulatkan ke atas, min 1 kg)." title="Tambahan per kilogram (dibulatkan ke atas, min 1 kg)."></i>
                            </label>
                            <input type="text" id="per_kg_amount" name="per_kg_amount" class="form-control number-format" inputmode="decimal" value="{{ format_number(old('per_kg_amount', 0), 2, true) }}" required>
                            <div class="form-text">Tambahan per kilogram (dibulatkan ke atas, min 1 kg).</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="etd_min_days">
                                ETD Min
                                <i class="ti ti-help-circle text-primary ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="shipping-rate-tooltip" data-bs-title="Estimasi hari kirim paling cepat." title="Estimasi hari kirim paling cepat."></i>
                            </label>
                            <input type="number" id="etd_min_days" name="etd_min_days" class="form-control" min="0" value="{{ old('etd_min_days') }}">
                            <div class="form-text">Hari tercepat.</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="etd_max_days">
                                ETD Max
                                <i class="ti ti-help-circle text-primary ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="shipping-rate-tooltip" data-bs-title="Estimasi hari kirim paling lama. Harus >= ETD Min." title="Estimasi hari kirim paling lama. Harus >= ETD Min."></i>
                            </label>
                            <input type="number" id="etd_max_days" name="etd_max_days" class="form-control" min="0" value="{{ old('etd_max_days') }}">
                            <div class="form-text">Hari terlama.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block">
                                Aktif
                                <i class="ti ti-help-circle text-primary ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="shipping-rate-tooltip" data-bs-title="Hanya tarif aktif yang dipakai untuk estimasi." title="Hanya tarif aktif yang dipakai untuk estimasi."></i>
                            </label>
                            <input type="hidden" name="is_active" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', '1') == '1')>
                                <label class="form-check-label" for="is_active">Ya</label>
                            </div>
                            <div class="form-text">Hanya tarif aktif yang dipakai untuk estimasi.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">
                                Catatan
                                <i class="ti ti-help-circle text-primary ms-1" style="cursor:help;" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="shipping-rate-tooltip" data-bs-title="Keterangan internal (sumber harga, zona, dll)." title="Keterangan internal (sumber harga, zona, dll)."></i>
                            </label>
                            <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                            <div class="form-text">Keterangan internal (sumber harga, zona, dll).</div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('shipping-rate.index.view') }}" class="btn btn-outline-dark me-2">Cancel</a>
        <button type="button" class="btn btn-primary" id="btn-submit">Save</button>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/block-ui/block-ui.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        @include('admin.master-data.shipping-rate._city-select-js')
        <script>
            $(function() {
                var Tooltip = window.bootstrap && window.bootstrap.Tooltip;
                if (Tooltip) {
                    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                        var existing = Tooltip.getInstance(el);
                        if (existing) existing.dispose();
                        new Tooltip(el, { container: 'body', trigger: 'hover focus', boundary: 'window' });
                    });
                }
                $('#btn-submit').click(function() { $('#postForm').submit(); });
            });
        </script>
    @endpush
</x-app-layout>
