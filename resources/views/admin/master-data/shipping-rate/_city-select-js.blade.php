<script>
(function() {
    function initCitySelect($el) {
        $el.select2({
            placeholder: $el.data('placeholder') || 'Cari kota',
            allowClear: true,
            width: '100%',
            minimumInputLength: 2,
            ajax: {
                url: '/helper/cities',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { search: params.term, page: params.page || 1, per_page: 30 };
                },
                processResults: function(data) {
                    var results = (data.results || []).map(function(item) {
                        var text = item.text || item.name || '';
                        if (item.province_name) text += ' (' + item.province_name + ')';
                        return { id: item.id, text: text };
                    });
                    return {
                        results: results,
                        pagination: data.pagination || { more: false }
                    };
                },
                cache: true
            }
        });
    }

    $(function() {
        $('.shipping-city-select').each(function() {
            initCitySelect($(this));
        });
    });
})();
</script>
