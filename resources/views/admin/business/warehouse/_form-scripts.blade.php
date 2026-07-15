@push('vendor-js')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endpush
@push('page-js')
<script>
$(document).ready(function() {
    $('.select2').not('#province_select, #city_select').select2({ width: '100%' });
    $('#branch_ids').select2({ width: '100%', placeholder: 'Select branches' });

    $('#province_select').select2({
        placeholder: 'Search province (min. 3 characters)',
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

    $('#city_select').select2({ placeholder: 'Select province first', allowClear: true, width: '100%' });

    $('#province_select').on('select2:select', function(e) {
        const provinceId = e.params.data.id;
        const provinceName = e.params.data.text;
        $('#province_name_hidden').val(provinceName);
        $('#city_name_hidden').val('');
        const $city = $('#city_select').empty().append('<option value="">Loading...</option>').prop('disabled', true);
        $.get('/helper/cities', { province_id: provinceId, per_page: 9999 }, function(data) {
            $city.empty().append('<option value="">Select city</option>');
            (data.results || []).forEach(c => $city.append(`<option value="${c.text}">${c.text}</option>`));
            $city.prop('disabled', false);
        });
    });

    $('#city_select').on('change', function() { $('#city_name_hidden').val($(this).val() || ''); });
    $('#province_select').on('select2:clear', function() {
        $('#province_name_hidden, #city_name_hidden').val('');
        $('#city_select').empty().append('<option value="">Select province first</option>').prop('disabled', true);
    });

    @if(!empty($selectedProvinceId))
    $.get('/helper/cities', { province_id: '{{ $selectedProvinceId }}', per_page: 9999 }, function(data) {
        const $city = $('#city_select');
        $city.empty().append('<option value="">Select city</option>');
        (data.results || []).forEach(c => $city.append(`<option value="${c.text}">${c.text}</option>`));
        $city.prop('disabled', false);
        const current = $('#city_name_hidden').val();
        if (current) $city.val(current).trigger('change');
    });
    @endif

    function refreshWarehouseCode() {
        const $code = $('#warehouse_code');
        const $btn = $('#btn_regenerate_warehouse_code');
        if (!$code.length || !$btn.length) return;

        const companyId = $('#warehouse_company_id').val() || '';
        $btn.prop('disabled', true);
        $.get(@json(route('warehouse.generate.code')), { company_id: companyId })
            .done(function (res) {
                if (res && res.code) $code.val(res.code);
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    }

    $('#btn_regenerate_warehouse_code').on('click', refreshWarehouseCode);
    $('#warehouse_company_id').on('change', function () {
        if ($('#btn_regenerate_warehouse_code').length) {
            refreshWarehouseCode();
        }
    });
});
</script>
@endpush
