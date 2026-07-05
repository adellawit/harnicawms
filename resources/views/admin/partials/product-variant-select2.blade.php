{{-- Reusable Select2 AJAX search for product variants. Include once per page,
     then call window.initProductVariantSelect2($el, options) on any <select>
     (static or dynamically created) to wire it up. --}}
@push('vendor-css')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endpush

@push('vendor-js')
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endpush

@push('page-js')
<script>
    window.initProductVariantSelect2 = function ($el, options) {
        options = options || {};

        $el.select2({
            placeholder: options.placeholder || 'Cari produk (ketik nama/SKU, minimal 2 karakter)',
            allowClear: true,
            width: '100%',
            minimumInputLength: 2,
            ajax: {
                url: '{{ route('helper.product-variants') }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term,
                        page: params.page || 1,
                        per_page: 30,
                        nature: options.nature || null,
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results || [],
                        pagination: data.pagination || { more: false },
                    };
                },
                cache: true,
            },
        });

        if (typeof options.onSelect === 'function') {
            $el.on('select2:select', function (e) {
                options.onSelect(e.params.data);
            });
        }
    };
</script>
@endpush
