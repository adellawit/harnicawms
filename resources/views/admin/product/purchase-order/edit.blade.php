<x-app-layout>
    @section('title', 'Edit Purchase Order | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    @push('page-css')
        <style>
            .detail-row { border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; background-color: #f8f9fa; }
            .detail-row-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #dee2e6; }
            .detail-row-number { font-weight: bold; color: #696cff; }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => 'javascript:void(0);'],
                ['label' => 'Purchase Order', 'url' => route('product.purchase-order.index.view')],
                ['label' => 'Edit', 'active' => true]
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <h5 class="card-header fw-bold">Edit Purchase Order - {{ $purchase->purchase_number }}</h5>
            <form method="POST" action="{{ route('product.purchase-order.edit.data') }}" id="postForm">
                @csrf
                <input type="hidden" name="id" value="{{ $purchase->id }}" />
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Purchase Number</label>
                            <input type="text" class="form-control bg-light" value="{{ $purchase->purchase_number }}" readonly />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="purchase_date">Purchase Date <span class="text-danger">*</span></label>
                            <input type="text" id="purchase_date" name="purchase_date" class="form-control flatpickr-date" value="{{ old('purchase_date', $purchase->purchase_date?->format('d/m/Y')) }}" required />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="expected_delivery_date">Expected Delivery</label>
                            <input type="text" id="expected_delivery_date" name="expected_delivery_date" class="form-control flatpickr-date" value="{{ old('expected_delivery_date', $purchase->expected_delivery_date?->format('d/m/Y')) }}" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="status_id">Status <span class="text-danger">*</span></label>
                            <select id="status_id" name="status_id" class="form-select" required>
                                @php $currentStatusId = ($poStatuses ?? collect())->firstWhere('key', $purchase->status)?->id; @endphp
                                @foreach(($poStatuses ?? collect()) as $s)
                                    <option value="{{ $s->id }}" {{ old('status_id', $currentStatusId) == $s->id ? 'selected' : '' }}>{{ $s->value ?? $s->key }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="supplier_id">Supplier <span class="text-danger">*</span></label>
                            <select id="supplier_id" name="supplier_id" class="form-select select2-supplier" required>
                                <option value="">-- Pilih Supplier --</option>
                                @foreach(($suppliers ?? collect()) as $s)
                                    <option value="{{ $s->id }}" {{ old('supplier_id', $purchase->supplier_id) == $s->id ? 'selected' : '' }}>{{ $s->name }}{{ $s->code ? ' ('.$s->code.')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Supplier Info</label>
                            <div id="supplier_info" class="form-control bg-light" style="min-height:38px; padding: 0.4rem 0.8rem;" readonly>
                                {{ $purchase->supplier_name ?: '-' }}{{ $purchase->supplier_contact ? ' | ' . $purchase->supplier_contact : '' }}{{ $purchase->supplier_address ? ' | ' . $purchase->supplier_address : '' }}
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Notes</label>
                            <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $purchase->notes) }}</textarea>
                        </div>
                    </div>

                    <hr class="my-4" />
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Items</h5>
                        <button type="button" class="btn btn-sm btn-primary" id="btnAddItem"><i class="ti ti-plus me-1"></i>Add Item</button>
                    </div>
                    <div id="itemsContainer">
                        @foreach($purchase->items as $idx => $item)
                        @php $itemHasVariants = $products->firstWhere('id', $item->product_id)?->variants->count() > 0; @endphp
                        <div class="detail-row" data-index="{{ $idx }}" data-variant-id="{{ $item->variant_id }}">
                            <div class="detail-row-header">
                                <span class="detail-row-number">Item #{{ $idx + 1 }}</span>
                                <button type="button" class="btn btn-sm btn-danger btnRemoveItem"><i class="ti ti-trash me-1"></i>Remove</button>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Product <span class="text-danger">*</span></label>
                                    <select name="items[{{ $idx }}][product_id]" class="form-select select2-product" data-index="{{ $idx }}" required>
                                        <option value="">-- Select --</option>
                                        @foreach($products as $product)
                                            <option value="{{ $product->id }}" {{ old('items.'.$idx.'.product_id', $item->product_id) == $product->id ? 'selected' : '' }}>{{ $product->name }}{{ $product->code ? ' ('.$product->code.')' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 variant-col" style="{{ $itemHasVariants ? '' : 'display:none;' }}">
                                    <label class="form-label">Variant</label>
                                    <select name="items[{{ $idx }}][variant_id]" class="form-select select2-variant">
                                        <option value="">-- Tanpa Varian --</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Unit <span class="text-danger">*</span></label>
                                    <select name="items[{{ $idx }}][unit_id]" class="form-select select2-unit">
                                        @foreach($units as $u)
                                            <option value="{{ $u->id }}" {{ old('items.'.$idx.'.unit_id', $item->unit_id) == $u->id ? 'selected' : '' }}>{{ $u->name }}{{ $u->symbol ? ' ('.$u->symbol.')' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Qty <span class="text-danger">*</span></label>
                                    <input type="text" name="items[{{ $idx }}][quantity]" class="form-control item-qty number-format" value="{{ format_number($item->quantity, 6, true) }}" required />
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Unit Price <span class="text-danger">*</span></label>
                                    <input type="text" name="items[{{ $idx }}][unit_price]" class="form-control item-price number-format" value="{{ format_number($item->unit_price, 4, true) }}" required />
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Discount</label>
                                    <input type="text" name="items[{{ $idx }}][discount_amount]" class="form-control item-discount number-format" value="{{ format_number($item->discount_amount, 4, true) }}" />
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Subtotal</label>
                                    <input type="text" class="form-control item-subtotal bg-light" value="{{ format_number($item->subtotal, 4, true) }}" readonly />
                                </div>
                                <div class="col-md-10">
                                    <label class="form-label">Notes</label>
                                    <input type="text" name="items[{{ $idx }}][notes]" class="form-control" value="{{ old('items.'.$idx.'.notes', $item->notes) }}" />
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <hr class="my-4" />
                    <div class="row g-3 justify-content-end">
                        <div class="col-md-3">
                            <label class="form-label">Subtotal</label>
                            <input type="text" id="subtotal_display" class="form-control bg-light" readonly />
                            <input type="hidden" name="subtotal" id="subtotal" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="tax_amount">Tax Amount</label>
                            <input type="text" id="tax_amount" name="tax_amount" class="form-control number-format" value="{{ format_number($purchase->tax_amount, 4, true) }}" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="discount_amount">Discount Amount</label>
                            <input type="text" id="discount_amount" name="discount_amount" class="form-control number-format" value="{{ format_number($purchase->discount_amount, 4, true) }}" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Total</label>
                            <input type="text" id="total_display" class="form-control bg-primary text-white fw-bold" readonly />
                            <input type="hidden" name="total" id="total" />
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('product.purchase-order.detail.view', $purchase->id) }}" class="btn btn-outline-dark me-2">Cancel</a>
        <x-button color="primary" id="btn-submit">Save</x-button>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/block-ui/block-ui.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-pickers.js') }}"></script>
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            var products = @json($products);
            var units = @json($units);
            var currentSuppliers = @json($suppliers ?? []);
            var itemCounter = {{ $purchase->items->count() }};

            function updateSupplierInfo(supplier) {
                if (!supplier) { $('#supplier_info').text('-'); return; }
                var parts = [supplier.name];
                if (supplier.contact) parts.push('Contact: ' + supplier.contact);
                if (supplier.phone) parts.push('Phone: ' + supplier.phone);
                if (supplier.is_ppn) parts.push('PPN: ' + (supplier.ppn_rate || 11) + '%');
                if (supplier.address) parts.push(supplier.address);
                $('#supplier_info').html(parts.join(' | '));
            }

            function calculateTaxFromSupplier() {
                var supplierId = $('#supplier_id').val();
                var supplier = supplierId ? currentSuppliers.find(function(s){ return s.id === supplierId; }) : null;
                if (supplier && supplier.is_ppn) {
                    var subtotal = 0;
                    $('.item-subtotal').each(function() { subtotal += parseNum($(this).val()); });
                    var rate = parseFloat(supplier.ppn_rate) || 11;
                    var tax = Math.round(subtotal * rate / 100);
                    var cleave = $('#tax_amount').data('cleave');
                    if (cleave) { cleave.setRawValue(tax.toString()); } else { $('#tax_amount').val(tax); }
                } else {
                    var cleave = $('#tax_amount').data('cleave');
                    if (cleave) { cleave.setRawValue('0'); } else { $('#tax_amount').val(0); }
                }
                updateTotals();
            }

            function getVariantLabel(variant) {
                var attrs = (variant.variant_attributes || []).map(function(va) {
                    var defName = va.attribute_definition ? va.attribute_definition.name : '';
                    var valName = va.attribute_value ? va.attribute_value.value : '';
                    return defName + ': ' + valName;
                });
                var label = variant.sku || '';
                if (attrs.length > 0) label += ' (' + attrs.join(', ') + ')';
                return label;
            }

            function getVariantOptions(productId, selectedVariantId) {
                var p = products.find(function(r) { return r.id === productId; });
                if (!p || !p.variants || p.variants.length === 0) return '';
                var opts = '<option value="">-- Tanpa Varian --</option>';
                p.variants.forEach(function(v) {
                    var sel = (selectedVariantId && v.id === selectedVariantId) ? ' selected' : '';
                    opts += '<option value="'+v.id+'"'+sel+'>'+getVariantLabel(v)+'</option>';
                });
                return opts;
            }

            function hasVariants(productId) {
                var p = products.find(function(r) { return r.id === productId; });
                return p && p.variants && p.variants.length > 0;
            }

            function updateVariantDropdown($row, productId, selectedVariantId) {
                var $variantCol = $row.find('.variant-col');
                var $variantSelect = $row.find('.select2-variant');
                if (hasVariants(productId)) {
                    $variantSelect.empty().append(getVariantOptions(productId, selectedVariantId));
                    $variantCol.show();
                    $variantSelect.trigger('change.select2');
                } else {
                    $variantSelect.empty().append('<option value="">-- Tanpa Varian --</option>');
                    $variantCol.hide();
                    $variantSelect.val('').trigger('change.select2');
                }
            }

            function getUnitOptions(productId, selectedUnitId) {
                var p = products.find(function(r) { return r.id === productId; });
                if (!p) return '<option value="">-- Select Product first --</option>';

                var defaultUnitId = p.default_unit_id;
                var convUnits = (p.unit_conversions || []);
                var availableUnitIds = [];

                if (defaultUnitId) availableUnitIds.push(defaultUnitId);
                convUnits.forEach(function(c) {
                    if (c.from_unit_id && availableUnitIds.indexOf(c.from_unit_id) === -1) availableUnitIds.push(c.from_unit_id);
                    if (c.to_unit_id && availableUnitIds.indexOf(c.to_unit_id) === -1) availableUnitIds.push(c.to_unit_id);
                });

                var filteredUnits = availableUnitIds.length > 0
                    ? units.filter(function(u) { return availableUnitIds.indexOf(u.id) !== -1; })
                    : [units.find(function(u) { return u.id === defaultUnitId; })].filter(Boolean);

                if (filteredUnits.length === 0) {
                    filteredUnits = units;
                }

                var preselect = selectedUnitId || defaultUnitId;
                return filteredUnits.map(function(u) {
                    var sel = u.id === preselect ? ' selected' : '';
                    return '<option value="'+u.id+'"'+sel+'>'+u.name+(u.symbol ? ' ('+u.symbol+')' : '')+'</option>';
                }).join('');
            }

            function addItemRow() {
                itemCounter++;
                var unitOpts = '<option value="">-- Select Product first --</option>';
                var productOpts = '<option value="">-- Select Product --</option>' + products.map(function(r) {
                    return '<option value="'+r.id+'">'+r.name+(r.code ? ' ('+r.code+')' : '')+'</option>';
                }).join('');
                var row = '<div class="detail-row" data-index="'+itemCounter+'">' +
                    '<div class="detail-row-header"><span class="detail-row-number">Item #'+itemCounter+'</span>' +
                    '<button type="button" class="btn btn-sm btn-danger btnRemoveItem"><i class="ti ti-trash me-1"></i>Remove</button></div>' +
                    '<div class="row g-3">' +
                    '<div class="col-md-4"><label class="form-label">Product <span class="text-danger">*</span></label>' +
                    '<select name="items['+itemCounter+'][product_id]" class="form-select select2-product" required>'+productOpts+'</select></div>' +
                    '<div class="col-md-4 variant-col" style="display:none;"><label class="form-label">Variant</label>' +
                    '<select name="items['+itemCounter+'][variant_id]" class="form-select select2-variant"></select></div>' +
                    '<div class="col-md-2"><label class="form-label">Unit <span class="text-danger">*</span></label>' +
                    '<select name="items['+itemCounter+'][unit_id]" class="form-select select2-unit">'+unitOpts+'</select></div>' +
                    '<div class="col-md-2"><label class="form-label">Qty <span class="text-danger">*</span></label>' +
                    '<input type="text" name="items['+itemCounter+'][quantity]" class="form-control item-qty number-format" required /></div>' +
                    '<div class="col-md-2"><label class="form-label">Unit Price <span class="text-danger">*</span></label>' +
                    '<input type="text" name="items['+itemCounter+'][unit_price]" class="form-control item-price number-format" required /></div>' +
                    '<div class="col-md-2"><label class="form-label">Discount</label>' +
                    '<input type="text" name="items['+itemCounter+'][discount_amount]" class="form-control item-discount number-format" value="0" /></div>' +
                    '<div class="col-md-2"><label class="form-label">Subtotal</label>' +
                    '<input type="text" class="form-control item-subtotal bg-light" readonly /></div>' +
                    '<div class="col-md-10"><label class="form-label">Notes</label>' +
                    '<input type="text" name="items['+itemCounter+'][notes]" class="form-control" /></div></div></div>';
                $('#itemsContainer').append(row);
                var $lastRow = $('#itemsContainer .detail-row:last');
                $lastRow.find('.select2-product, .select2-unit, .select2-variant').select2({ placeholder: '-- Select --', allowClear: true });
                $lastRow.find('.select2-product').on('change', function() {
                    var productId = $(this).val();
                    var $row = $(this).closest('.detail-row');
                    $row.find('.select2-unit').empty().append(getUnitOptions(productId)).trigger('change');
                    updateVariantDropdown($row, productId);
                });
                initNumberInputs($lastRow);
                bindItemCalculations($lastRow);
            }

            function initNumberInputs(scope) {
                $(scope || document).find('.number-format').each(function() {
                    if ($(this).data('cleave')) return;
                    var c = new Cleave(this, {
                        numeral: true, numeralThousandsGroupStyle: 'thousand', numeralDecimalMark: ',',
                        delimiter: '.', numeralDecimalScale: 6, onValueChanged: updateTotals
                    });
                    $(this).data('cleave', c);
                });
            }

            function parseNum(val) { return parseFloat(String(val||0).replace(/\./g,'').replace(',','.')) || 0; }

            function bindItemCalculations(scope) {
                $(scope || document).find('.item-qty, .item-price, .item-discount').off('input change keyup').on('input change keyup', function() {
                    var $row = $(this).closest('.detail-row');
                    var qty = parseNum($row.find('.item-qty').val());
                    var price = parseNum($row.find('.item-price').val());
                    var disc = parseNum($row.find('.item-discount').val());
                    var st = (qty * price) - disc;
                    $row.find('.item-subtotal').val(st.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 4}));
                    calculateTaxFromSupplier();
                });
            }

            function updateTotals() {
                var subtotal = 0;
                $('.item-subtotal').each(function() { subtotal += parseNum($(this).val()); });
                var tax = parseNum($('#tax_amount').val());
                var disc = parseNum($('#discount_amount').val());
                var total = subtotal + tax - disc;
                $('#subtotal_display').val(subtotal.toLocaleString('id-ID', {minimumFractionDigits: 2}));
                $('#subtotal').val(subtotal);
                $('#total_display').val(total.toLocaleString('id-ID', {minimumFractionDigits: 2}));
                $('#total').val(total);
            }

            $(document).ready(function() {
                $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y' });
                $('.select2-product, .select2-unit, .select2-variant').select2({ placeholder: '-- Select --', allowClear: true });
                initNumberInputs();
                bindItemCalculations();
                $('#tax_amount, #discount_amount').on('input change', updateTotals);
                updateTotals();

                $('.detail-row').each(function() {
                    var $row = $(this);
                    var productId = $row.find('.select2-product').val();
                    var savedVariantId = $row.data('variant-id') || '';
                    if (productId && hasVariants(productId)) {
                        updateVariantDropdown($row, productId, savedVariantId);
                    }
                });

                $('#btnAddItem').click(addItemRow);
                $(document).on('click', '.btnRemoveItem', function() {
                    if ($('.detail-row').length <= 1) { alert('Minimal 1 item diperlukan'); return; }
                    $(this).closest('.detail-row').remove();
                    updateTotals();
                });

                $(document).on('change', '.select2-product', function() {
                    var productId = $(this).val();
                    var $row = $(this).closest('.detail-row');
                    var currentUnitId = $row.find('.select2-unit').val();
                    $row.find('.select2-unit').empty().append(getUnitOptions(productId, currentUnitId)).trigger('change');
                    updateVariantDropdown($row, productId);
                });

                $('#supplier_id').select2({ placeholder: '-- Pilih Supplier --', allowClear: true });
                $('#supplier_id').on('change', function() {
                    var id = $(this).val();
                    updateSupplierInfo(id ? currentSuppliers.find(function(x){ return x.id === id; }) : null);
                    calculateTaxFromSupplier();
                });

                $('#btn-submit').click(function() {
                    if ($('.detail-row').length === 0) { alert('Minimal 1 item diperlukan'); return; }
                    $('.number-format').each(function() {
                        var $el = $(this);
                        var c = $el.data('cleave');
                        if (c && typeof c.getRawValue === 'function' && $el.attr('name')) {
                            var raw = c.getRawValue() || '0';
                            $el.prop('disabled', true);
                            $('<input type="hidden" name="'+$el.attr('name')+'" value="'+raw+'" />').insertAfter($el);
                        }
                    });
                    updateTotals();
                    $('#postForm').submit();
                });

                $('#postForm').submit(function() {
                    $(this).block({ message: '<div class="spinner-border text-primary"></div>', timeout: 1000, css: { backgroundColor: "transparent", border: 0 }, overlayCSS: { backgroundColor: "#fff", opacity: .8 } });
                });
            });
        </script>
    @endpush
</x-app-layout>
