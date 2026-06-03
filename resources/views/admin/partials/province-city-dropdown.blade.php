{{-- Province-first approach: search province (AJAX), then load cities --}}
@php
    $selectedProvinceId = $selectedProvinceId ?? null;
    $selectedCityId = $selectedCityId ?? null;
    $currentProvinceName = $currentProvinceName ?? old('province', '');
    $currentCityName = $currentCityName ?? old('city', '');
    $colClass = $colClass ?? 'col-md-4';
    $labelProvince = $labelProvince ?? 'Provinsi';
    $labelCity = $labelCity ?? 'Kota';
    $uniqueId = uniqid();
@endphp

<div class="{{ $colClass }}">
    <label class="form-label">{{ $labelProvince }}</label>
    <input type="hidden" name="province" id="province_name_hidden_{{ $uniqueId }}" value="{{ $currentProvinceName }}">
    <select id="province_select_{{ $uniqueId }}" class="form-select" style="width: 100%;">
        <option value="">Pilih {{ $labelProvince }}</option>
    </select>
    @error('province')
        <div class="text-danger small">{{ $message }}</div>
    @enderror
</div>

<div class="{{ $colClass }}">
    <label class="form-label">{{ $labelCity }}</label>
    <input type="hidden" name="city" id="city_name_hidden_{{ $uniqueId }}" value="{{ $currentCityName }}">
    <select id="city_select_{{ $uniqueId }}" class="form-select" style="width: 100%;" disabled>
        <option value="">Pilih {{ $labelProvince }} Terlebih Dahulu</option>
    </select>
    @error('city')
        <div class="text-danger small">{{ $message }}</div>
    @enderror
</div>

@push('page-js')
<script>
(function() {
    const uniqueId = '{{ $uniqueId }}';
    const $province = $('#province_select_' + uniqueId);
    const $city = $('#city_select_' + uniqueId);
    const $provinceHidden = $('#province_name_hidden_' + uniqueId);
    const $cityHidden = $('#city_name_hidden_' + uniqueId);

    let initialProvinceId = @json($selectedProvinceId);
    let initialProvinceName = @json($currentProvinceName);
    let initialCityName = @json($currentCityName);

    // Initialize Province Select2 with AJAX search
    $province.select2({
        placeholder: 'Cari {{ $labelProvince }} (minimal 3 karakter)',
        allowClear: true,
        width: '100%',
        minimumInputLength: 3,
        ajax: {
            url: '/helper/provinces',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    search: params.term,
                    page: params.page || 1,
                    per_page: 50
                };
            },
            processResults: function(data) {
                return {
                    results: data.results || [],
                    pagination: data.pagination || { more: false }
                };
            },
            cache: true
        }
    });

    // Initialize City Select2
    $city.select2({
        placeholder: 'Pilih {{ $labelProvince }} Terlebih Dahulu',
        allowClear: true,
        width: '100%',
        disabled: true
    });

    // Province change handler - load cities
    $province.on('select2:select', function(e) {
        const provinceId = e.params.data.id;
        const provinceName = e.params.data.text;

        // Set province name to hidden input
        $provinceHidden.val(provinceName);
        $cityHidden.val('');

        // Show loading
        $city.empty().append('<option value="">Loading...</option>').prop('disabled', true).trigger('change');

        // Load cities via AJAX
        $.get('/helper/cities', { province_id: provinceId, per_page: 9999 }, function(data) {
            const cities = data.results || [];

            // Clear and populate city dropdown
            $city.empty().append('<option value="">Pilih {{ $labelCity }}</option>');

            cities.forEach(function(city) {
                $city.append('<option value="' + city.text + '">' + city.text + '</option>');
            });

            // Enable city dropdown
            $city.prop('disabled', false).trigger('change');

            // Restore old city value if exists
            if (initialCityName && $city.find('option[value="' + initialCityName + '"]').length) {
                $city.val(initialCityName).trigger('change');
                initialCityName = ''; // Only auto-select once
            }
        });
    });

    // City change handler - set name to hidden input
    $city.on('change', function() {
        $cityHidden.val($(this).val() || '');
    });

    // Province clear handler
    $province.on('select2:clear', function() {
        $provinceHidden.val('');
        $cityHidden.val('');
        $city.empty().append('<option value="">Pilih {{ $labelProvince }} Terlebih Dahulu</option>').prop('disabled', true).trigger('change');
    });

    // Edit mode: if province is pre-selected, load cities
    if (initialProvinceId && initialProvinceName) {
        // Pre-select the province in Select2
        var provinceOption = new Option(initialProvinceName, initialProvinceId, true, true);
        $province.append(provinceOption).trigger('change');
        $provinceHidden.val(initialProvinceName);

        // Load cities for this province
        $.get('/helper/cities', { province_id: initialProvinceId, per_page: 9999 }, function(data) {
            const cities = data.results || [];

            $city.empty().append('<option value="">Pilih {{ $labelCity }}</option>');

            cities.forEach(function(city) {
                $city.append('<option value="' + city.text + '">' + city.text + '</option>');
            });

            $city.prop('disabled', false).trigger('change');

            // Pre-select city if exists
            if (initialCityName && $city.find('option[value="' + initialCityName + '"]').length) {
                $city.val(initialCityName).trigger('change');
                $cityHidden.val(initialCityName);
            }
        });
    }
})();
</script>
@endpush
