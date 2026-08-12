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

        function currentPromotionType() {
            const checked = document.querySelector('input[name="promotion_type"]:checked');
            return checked ? checked.value : 'product';
        }

        function setBlockFieldsEnabled(block, enabled) {
            if (!block) {
                return;
            }
            block.querySelectorAll('input, select, textarea').forEach(function (el) {
                if (el.type === 'radio' && el.name === 'promotion_type') {
                    return;
                }
                el.disabled = !enabled;
            });
        }

        function togglePromotionTypeBlocks() {
            const type = currentPromotionType();
            const productBlock = document.getElementById('promoProductBlock');
            const marketingBlock = document.getElementById('promoMarketingBlock');
            if (productBlock) {
                productBlock.style.display = type === 'product' ? '' : 'none';
                setBlockFieldsEnabled(productBlock, type === 'product');
            }
            if (marketingBlock) {
                marketingBlock.style.display = type === 'marketing' ? '' : 'none';
                setBlockFieldsEnabled(marketingBlock, type === 'marketing');
            }
        }

        function toggleTargetPickers() {
            const targetType = $('#target_type').val() || document.getElementById('target_type')?.value || 'both';
            document.querySelectorAll('.target-agent-picker').forEach(function (el) {
                el.style.display = (targetType === 'agent' || targetType === 'both') ? '' : 'none';
            });
            document.querySelectorAll('.target-reseller-picker').forEach(function (el) {
                el.style.display = (targetType === 'reseller' || targetType === 'both') ? '' : 'none';
            });
        }

        if (window.jQuery) {
            $('#get_product_mode').on('change', toggleGetSpecific);
            $('input[name="promotion_type"]').on('change', togglePromotionTypeBlocks);
            $('#target_type').on('change', toggleTargetPickers);
        } else {
            document.getElementById('get_product_mode')?.addEventListener('change', toggleGetSpecific);
            document.querySelectorAll('input[name="promotion_type"]').forEach(function (el) {
                el.addEventListener('change', togglePromotionTypeBlocks);
            });
            document.getElementById('target_type')?.addEventListener('change', toggleTargetPickers);
        }

        toggleGetSpecific();
        togglePromotionTypeBlocks();
        toggleTargetPickers();
    })();
</script>
