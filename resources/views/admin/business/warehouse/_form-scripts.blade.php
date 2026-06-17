@push('vendor-js')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endpush
@push('page-js')
<script>
$(document).ready(function() {
    $('.select2').not('#province_select, #city_select').select2({ width: '100%' });
    $('#branch_ids').select2({ width: '100%', placeholder: 'Pilih cabang' });

    $('#province_select').select2({
        placeholder: 'Cari provinsi (min. 3 karakter)',
        allowClear: true,
        width: '100%',
        minimumInputLength: 3,
        ajax: {
            url: '/helper/provinces',
            dataType: 'json',
            delay: 250,
            data: params => ({ search: params.term, page: params.page || 1, per_page: 50 }),
            processResults: data => ({ results: data.results || [], pagination: data.pagination || { more: false } }),
        }
    });

    $('#city_select').select2({ placeholder: 'Pilih provinsi dulu', allowClear: true, width: '100%' });

    $('#province_select').on('select2:select', function(e) {
        const provinceId = e.params.data.id;
        const provinceName = e.params.data.text;
        $('#province_name_hidden').val(provinceName);
        $('#city_name_hidden').val('');
        const $city = $('#city_select').empty().append('<option value="">Loading...</option>').prop('disabled', true);
        $.get('/helper/cities', { province_id: provinceId, per_page: 9999 }, function(data) {
            $city.empty().append('<option value="">Pilih kota</option>');
            (data.results || []).forEach(c => $city.append(`<option value="${c.text}">${c.text}</option>`));
            $city.prop('disabled', false);
        });
    });

    $('#city_select').on('change', function() { $('#city_name_hidden').val($(this).val() || ''); });
    $('#province_select').on('select2:clear', function() {
        $('#province_name_hidden, #city_name_hidden').val('');
        $('#city_select').empty().append('<option value="">Pilih provinsi dulu</option>').prop('disabled', true);
    });

    @if(!empty($selectedProvinceId))
    $.get('/helper/cities', { province_id: '{{ $selectedProvinceId }}', per_page: 9999 }, function(data) {
        const $city = $('#city_select');
        $city.empty().append('<option value="">Pilih kota</option>');
        (data.results || []).forEach(c => $city.append(`<option value="${c.text}">${c.text}</option>`));
        $city.prop('disabled', false);
        const current = $('#city_name_hidden').val();
        if (current) $city.val(current).trigger('change');
    });
    @endif
});
</script>
@endpush
