<script>
    $(document).ready(function () {
        const $setupToggle = $('#warehouse_setup');
        const $setupFields = $('#warehouseSetupFields');
        const $parentSelect = $('select[name="parent_id"]');
        const $branchName = $('input[name="name"]');
        const $warehouseName = $('#warehouse_name');
        const $assignedSelect = $('#assigned_warehouse_ids');
        const $defaultSelect = $('#default_warehouse_id');

        function toggleWarehouseFields() {
            const enabled = $setupToggle.is(':checked');
            $setupFields.toggle(enabled);
            $setupFields.find('input, select, textarea').not('[readonly]').prop('disabled', !enabled);
        }

        function suggestWarehouseName() {
            if ($warehouseName.data('touched')) {
                return;
            }

            const name = ($branchName.val() || '').trim();
            if (name) {
                $warehouseName.val('Gudang ' + name);
            }
        }

        function filterWarehouseOptionsByCompany() {
            const companyId = $parentSelect.val();

            [$assignedSelect, $defaultSelect].forEach(function ($select) {
                if (!$select.length) {
                    return;
                }

                $select.find('option').each(function () {
                    const $option = $(this);
                    const optionCompany = $option.data('company');
                    const isOwned = $option.data('owned');

                    if (!$option.val() || isOwned) {
                        $option.prop('disabled', false).show();
                        return;
                    }

                    const visible = !companyId || optionCompany === companyId;
                    $option.prop('disabled', !visible);
                    if (!visible && $option.is(':selected')) {
                        $option.prop('selected', false);
                    }
                });

                $select.trigger('change.select2');
            });
        }

        $setupToggle.on('change', toggleWarehouseFields);
        $branchName.on('input', suggestWarehouseName);
        $parentSelect.on('change', filterWarehouseOptionsByCompany);

        $warehouseName.on('input', function () {
            $(this).data('touched', true);
        });

        toggleWarehouseFields();
        suggestWarehouseName();
        filterWarehouseOptionsByCompany();
    });
</script>
