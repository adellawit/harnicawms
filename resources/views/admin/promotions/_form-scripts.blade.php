<script>
    (function () {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (el) {
            if (window.bootstrap?.Tooltip) {
                new bootstrap.Tooltip(el);
            }
        });

        if (window.jQuery && $.fn.select2) {
            $('.select2').select2({
                width: '100%',
                allowClear: true,
                placeholder: function () {
                    return $(this).find('option:first').text() || 'Select';
                },
            });
        }

        if (window.flatpickr) {
            $('.flatpickr-date').flatpickr({
                dateFormat: 'd/m/Y',
                allowInput: true,
                disableMobile: true,
            });
        }

        function toggleGetSpecific() {
            const mode = $('#get_product_mode').val() || document.getElementById('get_product_mode')?.value;
            document.querySelectorAll('.get-specific').forEach(function (el) {
                el.style.display = mode === 'specific' ? '' : 'none';
            });
        }

        if (window.jQuery) {
            $('#get_product_mode').on('change', toggleGetSpecific);
        } else {
            document.getElementById('get_product_mode')?.addEventListener('change', toggleGetSpecific);
        }
        toggleGetSpecific();
    })();
</script>
