<x-app-layout>
    @section('title', 'Add Purchase Order | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    @push('page-css')
        <style>
            .detail-row { border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; background-color: #f8f9fa; }
            .detail-row-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #dee2e6; }
            .detail-row-number { font-weight: bold; color: #696cff; }
            .item-mc-display { min-height: 38px; font-size: 0.8125rem; line-height: 1.35; word-break: break-word; }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => 'javascript:void(0);'],
                ['label' => 'Purchase Order', 'url' => route('product.purchase-order.index.view')],
                ['label' => 'Add', 'active' => true]
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <h5 class="card-header fw-bold">Add Purchase Order</h5>
            <form method="POST" action="{{ route('product.purchase-order.insert.data') }}" id="postForm">
                @csrf
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    @php
                        $selectedPoKind = old('po_kind', $defaultPoKind ?? 'standalone');
                        $isLockedSubFromCpo = ! empty($lockDocumentTypeToSub) || (old('po_kind') === 'sub' && old('parent_id'));
                    @endphp
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Document Type <span class="text-danger">*</span></label>
                            @if($isLockedSubFromCpo)
                                <input type="hidden" name="po_kind" value="sub">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-label-info fs-6">RO — Release Order (dari CPO)</span>
                                    <small class="text-muted">PO standalone &amp; CPO baru tidak tersedia saat release dari CPO induk.</small>
                                </div>
                            @else
                            <div class="d-flex flex-wrap gap-3" id="po-kind-options">
                                <div class="form-check po-kind-option" data-kind="standalone">
                                    <input class="form-check-input" type="radio" name="po_kind" id="po_kind_standalone" value="standalone" @checked($selectedPoKind === 'standalone')>
                                    <label class="form-check-label" for="po_kind_standalone">PO — tanpa kontrak</label>
                                </div>
                                <div class="form-check po-kind-option" data-kind="master">
                                    <input class="form-check-input" type="radio" name="po_kind" id="po_kind_master" value="master" @checked($selectedPoKind === 'master')>
                                    <label class="form-check-label" for="po_kind_master">CPO — Contract PO (batch)</label>
                                </div>
                                <div class="form-check po-kind-option" data-kind="sub">
                                    <input class="form-check-input" type="radio" name="po_kind" id="po_kind_sub" value="sub" @checked($selectedPoKind === 'sub')>
                                    <label class="form-check-label" for="po_kind_sub">RO — Release Order (dari CPO)</label>
                                </div>
                            </div>
                            @endif
                            <small class="text-muted d-block mt-1" id="po-kind-hint">PO standalone dapat diedit selama status draft.</small>
                        </div>
                        <div class="col-md-6" id="parent-po-wrap" style="{{ $isLockedSubFromCpo || $selectedPoKind === 'sub' ? '' : 'display:none;' }}">
                            <label class="form-label" for="parent_id">CPO Induk <span class="text-danger">*</span></label>
                            @if($isLockedSubFromCpo && ($defaultParentId || old('parent_id')))
                                <input type="hidden" name="parent_id" id="parent_id" value="{{ old('parent_id', $defaultParentId) }}">
                                <div class="form-control bg-light" id="parent-po-locked-label" readonly>-</div>
                            @else
                            <select id="parent_id" name="parent_id" class="form-select">
                                <option value="">-- Pilih PO Utama --</option>
                            </select>
                            @endif
                        </div>
                    </div>
                    <div id="cpo-release-summary-wrap" class="row g-3" style="display:none;">
                        <div class="col-12">
                            <div class="alert alert-info mb-0 py-2">
                                <div class="fw-semibold mb-1">Informasi Qty CPO</div>
                                <small id="cpo-release-summary-meta" class="text-muted d-block mb-2"></small>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0 bg-white">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product</th>
                                                <th>Unit</th>
                                                <th class="text-end">Qty CPO</th>
                                                <th class="text-end">Sudah Release</th>
                                                <th class="text-end">Sisa PO</th>
                                            </tr>
                                        </thead>
                                        <tbody id="cpo-release-summary-body"></tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <th colspan="2" class="text-end">Total</th>
                                                <th class="text-end" id="cpo-total-committed">0</th>
                                                <th class="text-end" id="cpo-total-released">0</th>
                                                <th class="text-end text-warning fw-semibold" id="cpo-total-remaining">0</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="purchase_date">Purchase Date <span class="text-danger">*</span></label>
                            <input type="text" id="purchase_date" name="purchase_date" class="form-control flatpickr-date" placeholder="DD/MM/YYYY" value="{{ old('purchase_date', date('d/m/Y')) }}" required />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="expected_delivery_date">Expected Delivery</label>
                            <input type="text" id="expected_delivery_date" name="expected_delivery_date" class="form-control flatpickr-date" placeholder="DD/MM/YYYY" value="{{ old('expected_delivery_date') }}" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="status_id">Status <span class="text-danger">*</span></label>
                            <select id="status_id" name="status_id" class="form-select" required>
                                @foreach($poStatuses ?? [] as $s)
                                    <option value="{{ $s->id }}" {{ old('status_id', collect($poStatuses ?? [])->firstWhere('key','draft')?->id) == $s->id ? 'selected' : '' }}>{{ $s->value ?? $s->key }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="supplier_id">Supplier <span class="text-danger">*</span></label>
                            <select id="supplier_id" name="supplier_id" class="form-select select2-supplier" required>
                                <option value="">-- Pilih Supplier --</option>
                            </select>
                            <small class="text-muted" id="supplier-type-hint">Pilih supplier untuk memfilter item (Bahan Baku / Product).</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Supplier Info</label>
                            <div id="supplier_info" class="form-control bg-light" style="min-height:38px; padding: 0.4rem 0.8rem;" readonly>-</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="attention_to">Attention (Up.)</label>
                            <input type="text" id="attention_to" name="attention_to" class="form-control" placeholder="Contact person at supplier" value="{{ old('attention_to') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ship_to_address">Ship To</label>
                            <input type="text" id="ship_to_address" name="ship_to_address" class="form-control" placeholder="Delivery / ship-to address" value="{{ old('ship_to_address') }}" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Notes / Keterangan</label>
                            <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <hr class="my-4" />
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Items</h5>
                        <button type="button" class="btn btn-sm btn-primary" id="btnAddItem"><i class="ti ti-plus me-1"></i>Add Item</button>
                    </div>
                    <div id="itemsContainer"></div>

                    <hr class="my-4" />
                    <div class="row g-3 justify-content-end">
                        <div class="col-md-3">
                            <label class="form-label">Subtotal</label>
                            <input type="text" id="subtotal_display" class="form-control bg-light" readonly />
                            <input type="hidden" name="subtotal" id="subtotal" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="tax_amount">Tax Amount</label>
                            <input type="text" id="tax_amount" name="tax_amount" class="form-control number-format" value="0" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="discount_amount">Discount Amount</label>
                            <input type="text" id="discount_amount" name="discount_amount" class="form-control number-format" value="0" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="other_cost_amount">Other Costs</label>
                            <input type="text" id="other_cost_amount" name="other_cost_amount" class="form-control number-format" value="0" />
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
        <a href="{{ route('product.purchase-order.index.view') }}" class="btn btn-outline-dark me-2">Cancel</a>
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
        <script src="{{ asset('assets/js/purchase-order-carton.js') }}"></script>
        <script>
            var products = @json($products);
            var units = @json($units);
            var supplierItemTypeMap = @json($supplierItemTypeMap ?? []);
            var suppliersByTypeUrl = "{{ route('product.purchase-order.suppliers-by-type') }}";
            var mastersForSubUrl = "{{ route('product.purchase-order.masters-for-sub') }}";
            var masterItemsUrlBase = "{{ url('product/purchase-order/master-items') }}";
            var currentPoKind = @json(old('po_kind', $defaultPoKind ?? 'standalone'));
            var oldParentId = @json(old('parent_id', $defaultParentId ?? null));
            var lockDocumentTypeToSub = @json(! empty($lockDocumentTypeToSub) || (old('po_kind') === 'sub' && old('parent_id')));
            var isSubMode = false;
            var isMasterMode = false;
            var masterItemsCache = [];
            var currentSuppliers = [];
            var itemCounter = 0;
            var oldItems = @json(old('items', []));
            var oldSupplierId = @json(old('supplier_id'));

            function getSupplierTypeKey() {
                var supplierId = $('#supplier_id').val();
                if (!supplierId) return null;
                var sup = currentSuppliers.find(function(s) { return s.id === supplierId; });
                return sup ? (sup.supplier_type_key || null) : null;
            }

            function getFilteredProducts() {
                var typeKey = getSupplierTypeKey();
                var allowed = typeKey ? (supplierItemTypeMap[typeKey] || null) : null;
                return products.filter(function(p) {
                    if (!p.is_purchase_item) return false;
                    if (!allowed) return true;
                    return allowed.indexOf(p.item_type_key) !== -1;
                });
            }

            function getProductOptionsHtml(selectedId) {
                var opts = '<option value="">-- Select Product --</option>';
                getFilteredProducts().forEach(function(r) {
                    var sel = selectedId === r.id ? ' selected' : '';
                    opts += '<option value="'+r.id+'"'+sel+'>'+r.name+(r.code ? ' ('+r.code+')' : '')+'</option>';
                });
                return opts;
            }

            function updateSupplierTypeHint() {
                var typeKey = getSupplierTypeKey();
                var sup = currentSuppliers.find(function(s) { return s.id === $('#supplier_id').val(); });
                if (!sup) {
                    $('#supplier-type-hint').text('Pilih supplier untuk memfilter item (Bahan Baku / Product).');
                    return;
                }
                if (typeKey === 'raw_material') {
                    $('#supplier-type-hint').text('Supplier Bahan Baku — hanya item bahan baku yang ditampilkan.');
                } else if (typeKey === 'product') {
                    $('#supplier-type-hint').text('Supplier Product — hanya item finished good yang ditampilkan.');
                } else {
                    $('#supplier-type-hint').text('Semua item pembelian ditampilkan.');
                }
            }

            function refreshProductSelects() {
                $('.select2-product').each(function() {
                    var $sel = $(this);
                    var current = $sel.val();
                    $sel.empty().append(getProductOptionsHtml(current));
                    if (current && getFilteredProducts().some(function(p) { return p.id === current; })) {
                        $sel.val(current);
                    } else {
                        $sel.val('');
                        var $row = $sel.closest('.detail-row');
                        $row.find('.select2-unit').empty().append('<option value="">-- Select Product first --</option>').trigger('change');
                        $row.find('.variant-col').hide();
                    }
                    $sel.trigger('change.select2');
                });
            }

            function loadSuppliers() {
                var $sel = $('#supplier_id');
                $.get(suppliersByTypeUrl, {}, function(suppliers) {
                    currentSuppliers = suppliers;
                    $sel.empty().append('<option value="">-- Pilih Supplier --</option>');
                    suppliers.forEach(function(s) {
                        var typeLabel = s.supplier_type_label ? ' [' + s.supplier_type_label + ']' : '';
                        $sel.append('<option value="'+s.id+'">'+s.name+(s.code ? ' ('+s.code+')' : '')+typeLabel+'</option>');
                    });
                    if (oldSupplierId) {
                        $sel.val(oldSupplierId).trigger('change.select2');
                        var sup = currentSuppliers.find(function(s){ return s.id === oldSupplierId; });
                        updateSupplierInfo(sup);
                        updateSupplierTypeHint();
                    } else {
                        $sel.trigger('change.select2');
                    }
                });
            }

            function updateSupplierInfo(supplier) {
                if (!supplier) { $('#supplier_info').text('-'); return; }
                var parts = [supplier.name];
                if (supplier.contact) parts.push('Contact: ' + supplier.contact);
                if (supplier.phone) parts.push('Phone: ' + supplier.phone);
                if (supplier.is_ppn) parts.push('PPN: ' + (supplier.ppn_rate || 11) + '%');
                if (supplier.supplier_type_label) parts.push('Type: ' + supplier.supplier_type_label);
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

            function addItemRow(itemData = null, options = {}) {
                var locked = !!options.locked;
                itemCounter++;
                var unitOpts = '<option value="">-- Select Product first --</option>';
                var productOpts = getProductOptionsHtml();
                var row = '<div class="detail-row" data-index="'+itemCounter+'">' +
                    (itemData && itemData.parent_item_id ? '<input type="hidden" name="items['+itemCounter+'][parent_item_id]" value="'+itemData.parent_item_id+'" />' : '') +
                    '<div class="detail-row-header">' +
                    '<span class="detail-row-number">Item #'+itemCounter+'</span>' +
                    (locked ? '' : '<button type="button" class="btn btn-sm btn-danger btnRemoveItem"><i class="ti ti-trash me-1"></i>Remove</button>') +
                    '</div>' +
                    '<div class="row g-3">' +
                    '<div class="col-md-4"><label class="form-label">Product <span class="text-danger">*</span></label>' +
                    '<select name="items['+itemCounter+'][product_id]" class="form-select select2-product" data-index="'+itemCounter+'" required '+(locked ? 'disabled' : '')+'>'+productOpts+'</select>' +
                    (locked && itemData && itemData.product_id ? '<input type="hidden" name="items['+itemCounter+'][product_id]" value="'+itemData.product_id+'" />' : '') +
                    '</div>' +
                    '<div class="col-md-4 variant-col" style="display:none;"><label class="form-label">Variant</label>' +
                    '<select name="items['+itemCounter+'][variant_id]" class="form-select select2-variant" '+(locked ? 'disabled' : '')+'></select>' +
                    (locked && itemData && itemData.variant_id ? '<input type="hidden" name="items['+itemCounter+'][variant_id]" value="'+itemData.variant_id+'" />' : '') +
                    '</div>' +
                    '<div class="col-md-2"><label class="form-label">Unit <span class="text-danger">*</span></label>' +
                    '<select name="items['+itemCounter+'][unit_id]" class="form-select select2-unit" '+(locked ? 'disabled' : '')+'>'+unitOpts+'</select>' +
                    (locked && itemData && itemData.unit_id ? '<input type="hidden" name="items['+itemCounter+'][unit_id]" value="'+itemData.unit_id+'" />' : '') +
                    '</div>' +
                    '<div class="col-md-2"><label class="form-label">Qty <span class="text-danger">*</span></label>' +
                    '<input type="text" name="items['+itemCounter+'][quantity]" class="form-control item-qty number-format" data-index="'+itemCounter+'" data-max="'+(itemData && itemData.remaining_qty ? itemData.remaining_qty : '')+'" required />' +
                    buildCpoQtyHint(itemData) +
                    '</div>' +
                    '<div class="col-md-2"><label class="form-label">Kode Batch <span class="text-danger">*</span></label>' +
                    '<input type="text" name="items['+itemCounter+'][batch_number]" class="form-control item-batch" maxlength="100" '+(locked ? 'readonly' : '')+' required value="'+(itemData && itemData.batch_number ? itemData.batch_number : '')+'" /></div>' +
                    '<div class="col-md-2"><label class="form-label">Expired <span class="text-danger">*</span></label>' +
                    '<input type="text" name="items['+itemCounter+'][expiry_date]" class="form-control flatpickr-date item-expiry" '+(locked ? 'readonly' : '')+' required value="'+(itemData && itemData.expiry_date ? itemData.expiry_date : '')+'" /></div>' +
                    '<div class="col-md-2"><label class="form-label">Carton (MC)</label>' +
                    '<div class="form-control item-mc-display bg-light text-muted">-</div></div>' +
                    '<div class="col-md-2"><label class="form-label">Unit Price <span class="text-danger">*</span></label>' +
                    '<input type="text" name="items['+itemCounter+'][unit_price]" class="form-control item-price number-format" data-index="'+itemCounter+'" required /></div>' +
                    '<div class="col-md-2"><label class="form-label">Discount</label>' +
                    '<input type="text" name="items['+itemCounter+'][discount_amount]" class="form-control item-discount number-format" data-index="'+itemCounter+'" value="0" /></div>' +
                    '<div class="col-md-2"><label class="form-label">Subtotal</label>' +
                    '<input type="text" class="form-control item-subtotal bg-light" readonly /></div>' +
                    '<div class="col-md-6"><label class="form-label">Notes</label>' +
                    '<input type="text" name="items['+itemCounter+'][notes]" class="form-control" /></div>' +
                    '</div></div>';
                $('#itemsContainer').append(row);
                var $lastRow = $('#itemsContainer .detail-row:last');
                $lastRow.find('.select2-product, .select2-unit, .select2-variant').each(function() {
                    $(this).select2({ placeholder: '-- Select --', allowClear: true });
                });
                $lastRow.find('.select2-product').on('change', function() {
                    var $row = $(this).closest('.detail-row');
                    var productId = $(this).val();
                    $row.find('.select2-unit').empty().append(getUnitOptions(productId)).trigger('change');
                    updateVariantDropdown($row, productId);
                    setTimeout(function() { updateMcDisplayForRow($row); }, 200);
                });
                initNumberInputs($lastRow);
                $lastRow.find('.item-expiry').each(function() {
                    if (!$(this).data('flatpickr')) {
                        $(this).flatpickr({ dateFormat: 'd/m/Y' });
                    }
                });
                bindItemCalculations($lastRow);

                // Prefill dari old input jika ada
                if (itemData) {
                    if (itemData.product_id) {
                        $lastRow.find('.select2-product').val(itemData.product_id).trigger('change');
                    }
                    setTimeout(function () {
                        if (itemData.unit_id) {
                            $lastRow.find('.select2-unit').val(itemData.unit_id).trigger('change');
                        }
                        if (itemData.variant_id) {
                            updateVariantDropdown($lastRow, itemData.product_id, itemData.variant_id);
                        }
                    }, 150);
                    if (typeof itemData.quantity !== 'undefined') {
                        $lastRow.find('.item-qty').val(itemData.quantity);
                    }
                    if (typeof itemData.unit_price !== 'undefined') {
                        $lastRow.find('.item-price').val(itemData.unit_price);
                    }
                    if (typeof itemData.discount_amount !== 'undefined') {
                        $lastRow.find('.item-discount').val(itemData.discount_amount);
                    }
                    if (itemData.notes) {
                        $lastRow.find('input[name$=\"[notes]\"]').val(itemData.notes);
                    }
                    bindItemCalculations($lastRow);
                }

                updateItemNumbers();
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

            function formatQty(val) {
                var num = parseFloat(val);
                if (isNaN(num)) return '0';
                return num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 6 });
            }

            function renderCpoReleaseSummary(master, items) {
                if (!items || !items.length) {
                    $('#cpo-release-summary-wrap').hide();
                    return;
                }

                var totalCommitted = 0;
                var totalReleased = 0;
                var totalRemaining = 0;
                var rows = '';

                items.forEach(function(item) {
                    var committed = parseFloat(item.committed_qty) || 0;
                    var released = parseFloat(item.allocated_qty ?? item.released_qty) || 0;
                    var remaining = parseFloat(item.remaining_qty) || 0;
                    totalCommitted += committed;
                    totalReleased += released;
                    totalRemaining += remaining;

                    var productLabel = item.product_name || '-';
                    if (item.product_code) productLabel += ' (' + item.product_code + ')';
                    var remainingClass = remaining > 0 ? 'text-warning fw-semibold' : 'text-success';

                    rows += '<tr>'
                        + '<td>' + productLabel + '</td>'
                        + '<td>' + (item.unit_label || '-') + '</td>'
                        + '<td class="text-end">' + formatQty(committed) + '</td>'
                        + '<td class="text-end">' + formatQty(released) + '</td>'
                        + '<td class="text-end ' + remainingClass + '">' + formatQty(remaining) + '</td>'
                        + '</tr>';
                });

                $('#cpo-release-summary-body').html(rows);
                $('#cpo-total-committed').text(formatQty(totalCommitted));
                $('#cpo-total-released').text(formatQty(totalReleased));
                $('#cpo-total-remaining').text(formatQty(totalRemaining));
                $('#cpo-release-summary-meta').text(
                    (master && master.purchase_number ? master.purchase_number + ' — ' : '')
                    + 'Total CPO: ' + formatQty(totalCommitted)
                    + (totalReleased > 0 ? ' | Sudah release: ' + formatQty(totalReleased) : '')
                    + ' | Sisa PO: ' + formatQty(totalRemaining)
                );
                $('#cpo-release-summary-wrap').show();
            }

            function clearCpoReleaseSummary() {
                $('#cpo-release-summary-wrap').hide();
                $('#cpo-release-summary-body').empty();
                $('#cpo-release-summary-meta').text('');
            }

            function buildCpoQtyHint(itemData) {
                if (!itemData || itemData.committed_qty === undefined) {
                    return itemData && itemData.remaining_qty
                        ? '<small class="text-muted d-block">Sisa release: ' + formatQty(itemData.remaining_qty) + '</small>'
                        : '';
                }

                var released = parseFloat(itemData.allocated_qty ?? itemData.released_qty) || 0;
                var hint = '<small class="text-muted d-block">Qty CPO: ' + formatQty(itemData.committed_qty);
                if (released > 0) {
                    hint += ' | Sudah release: ' + formatQty(released);
                }
                hint += ' | Sisa: <span class="text-warning fw-semibold">' + formatQty(itemData.remaining_qty) + '</span></small>';
                return hint;
            }

            function updateMcDisplayForRow($row) {
                if (window.PurchaseOrderCarton) {
                    PurchaseOrderCarton.updateRowMcDisplay($row, products, units, parseNum);
                }
            }

            function bindItemCalculations(scope) {
                $(scope || document).find('.item-qty, .item-price, .item-discount').off('input change keyup').on('input change keyup', function() {
                    var $row = $(this).closest('.detail-row');
                    var qty = parseNum($row.find('.item-qty').val());
                    var price = parseNum($row.find('.item-price').val());
                    var disc = parseNum($row.find('.item-discount').val());
                    var st = (qty * price) - disc;
                    $row.find('.item-subtotal').val(st.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 4}));
                    updateMcDisplayForRow($row);
                    calculateTaxFromSupplier();
                });
                $(scope || document).find('.select2-unit').off('change.mc').on('change.mc', function() {
                    updateMcDisplayForRow($(this).closest('.detail-row'));
                });
                $(scope || document).find('.detail-row').each(function() {
                    updateMcDisplayForRow($(this));
                });
            }

            function updateTotals() {
                var subtotal = 0;
                $('.item-subtotal').each(function() {
                    subtotal += parseNum($(this).val());
                });
                var tax = parseNum($('#tax_amount').val());
                var disc = parseNum($('#discount_amount').val());
                var other = parseNum($('#other_cost_amount').val());
                var total = subtotal + tax - disc + other;
                $('#subtotal_display').val(subtotal.toLocaleString('id-ID', {minimumFractionDigits: 2}));
                $('#subtotal').val(subtotal);
                $('#total_display').val(total.toLocaleString('id-ID', {minimumFractionDigits: 2}));
                $('#total').val(total);
            }

            function resetItemsContainer() {
                $('#itemsContainer').empty();
                itemCounter = 0;
            }

            function updateItemNumbers() {
                $('.detail-row').each(function(i) {
                    $(this).find('.detail-row-number').text('Item #' + (i + 1));
                });
            }

            function setPoKind(kind, options) {
                options = options || {};
                currentPoKind = kind;
                isSubMode = kind === 'sub';
                isMasterMode = kind === 'master';

                $('input[name="po_kind"][value="' + kind + '"]').prop('checked', true);

                if (!lockDocumentTypeToSub) {
                    $('.po-kind-option[data-kind="standalone"], .po-kind-option[data-kind="master"]').toggle(!isSubMode);
                }

                $('#parent-po-wrap').toggle(isSubMode);
                $('#btnAddItem').toggle(!isSubMode);
                $('#supplier_id').prop('disabled', isSubMode);

                if (isSubMode) {
                    $('#po-kind-hint').text('RO mengambil produk dari CPO induk. Qty tidak boleh melebihi sisa release.');
                    resetItemsContainer();
                    clearCpoReleaseSummary();
                    loadMastersForSub();
                } else if (options.restoreOldItems && oldItems && oldItems.length) {
                    $('#po-kind-hint').text('PO standalone dapat diedit selama status draft.');
                    $('#supplier_id').prop('disabled', false);
                    resetItemsContainer();
                    clearCpoReleaseSummary();
                    oldItems.forEach(function (it) { addItemRow(it); });
                    updateTotals();
                } else if (isMasterMode) {
                    $('#po-kind-hint').text('CPO tidak dapat diedit setelah disimpan. Buat RO untuk release parsial.');
                    resetItemsContainer();
                    clearCpoReleaseSummary();
                    addItemRow();
                } else {
                    $('#po-kind-hint').text('PO standalone dapat diedit selama status draft.');
                    $('#supplier_id').prop('disabled', false);
                    resetItemsContainer();
                    clearCpoReleaseSummary();
                    addItemRow();
                }
            }

            function loadMastersForSub() {
                $.get(mastersForSubUrl, function(masters) {
                    var $sel = $('#parent_id');
                    if (!$sel.is('select')) {
                        var parentId = oldParentId || $sel.val();
                        var master = masters.find(function(m) { return m.id === parentId; });
                        if (master) {
                            $('#parent-po-locked-label').text(master.purchase_number + ' - ' + master.supplier_name);
                        }
                        if (parentId) {
                            loadMasterItems(parentId);
                        }
                        return;
                    }
                    $sel.empty().append('<option value="">-- Pilih PO Utama --</option>');
                    masters.forEach(function(m) {
                        $sel.append('<option value="'+m.id+'">'+m.purchase_number+' - '+m.supplier_name+' (sisa RO)</option>');
                    });
                    if (oldParentId) {
                        $sel.val(oldParentId).trigger('change');
                    }
                });
            }

            function loadMasterItems(parentId) {
                if (!parentId) {
                    clearCpoReleaseSummary();
                    return;
                }
                $.get(masterItemsUrlBase + '/' + parentId, function(res) {
                    masterItemsCache = res.items || [];
                    renderCpoReleaseSummary(res.master, masterItemsCache);
                    $('#supplier_id').empty().append('<option value="'+res.master.supplier_id+'" selected>'+res.master.supplier_name+'</option>').trigger('change.select2');
                    updateSupplierInfo({
                        name: res.master.supplier_name,
                        contact: res.master.supplier_contact,
                        address: res.master.supplier_address,
                    });

                    $('#itemsContainer').empty();
                    itemCounter = 0;
                    masterItemsCache.forEach(function(item) {
                        if (item.remaining_qty <= 0) return;
                        addItemRow({
                            parent_item_id: item.parent_item_id,
                            product_id: item.product_id,
                            variant_id: item.variant_id,
                            unit_id: item.unit_id,
                            batch_number: item.batch_number,
                            expiry_date: item.expiry_date,
                            unit_price: item.unit_price,
                            discount_amount: item.discount_amount,
                            committed_qty: item.committed_qty,
                            allocated_qty: item.allocated_qty ?? item.released_qty,
                            remaining_qty: item.remaining_qty,
                        }, { locked: true });
                    });
                    updateTotals();
                });
            }

            $(document).ready(function() {
                $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y' });
                initNumberInputs();
                bindItemCalculations();
                $('#tax_amount, #discount_amount, #other_cost_amount').on('input change', updateTotals);

                $('input[name="po_kind"]').on('change', function() {
                    if (lockDocumentTypeToSub) return;
                    setPoKind($(this).val());
                });
                $('#parent_id').on('change', function() { loadMasterItems($(this).val()); });
                setPoKind(lockDocumentTypeToSub ? 'sub' : currentPoKind, { restoreOldItems: true });
                $('#supplier_id').select2({ placeholder: '-- Pilih Supplier --', allowClear: true });
                loadSuppliers();

                $('#btnAddItem').click(function () {
                    if (isSubMode) return;
                    if (!$('#supplier_id').val()) {
                        alert('Pilih supplier terlebih dahulu.');
                        return;
                    }
                    addItemRow();
                });
                $(document).on('click', '.btnRemoveItem', function() {
                    if (isSubMode) return;
                    if ($('.detail-row').length <= 1) { alert('Minimal 1 item diperlukan'); return; }
                    $(this).closest('.detail-row').remove();
                    updateItemNumbers();
                    updateTotals();
                });

                $(document).on('change', '.select2-product', function() {
                    var productId = $(this).val();
                    var $row = $(this).closest('.detail-row');
                    $row.find('.select2-unit').empty().append(getUnitOptions(productId)).trigger('change');
                    updateVariantDropdown($row, productId);
                });

                $('#supplier_id').on('change', function() {
                    var id = $(this).val();
                    updateSupplierInfo(id ? currentSuppliers.find(function(x){ return x.id === id; }) : null);
                    updateSupplierTypeHint();
                    if (!isSubMode) {
                        refreshProductSelects();
                    }
                    calculateTaxFromSupplier();
                });

                $('#btn-submit').click(function() {
                    if ($('.detail-row').length === 0) { alert('Minimal 1 item diperlukan'); return; }
                    if (isSubMode && !$('#parent_id').val()) { alert('Pilih PO Utama untuk Sub-PO'); return; }
                    var qtyError = null;
                    $('.detail-row').each(function() {
                        var $row = $(this);
                        var max = parseFloat($row.find('.item-qty').data('max'));
                        var qty = parseNum($row.find('.item-qty').val());
                        if (!isNaN(max) && max >= 0 && qty > max + 1e-6) {
                            qtyError = 'Qty melebihi sisa release PO utama (maks: ' + max + ').';
                            return false;
                        }
                    });
                    if (qtyError) { alert(qtyError); return; }
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
                    var st = 0;
                    $('.item-subtotal').each(function() { st += parseNum($(this).val()); });
                    var tax = parseNum($('#tax_amount').val());
                    var disc = parseNum($('#discount_amount').val());
                    var other = parseNum($('#other_cost_amount').val());
                    $('#subtotal').val(st);
                    $('#total').val(st + tax - disc + other);
                    $('#postForm').submit();
                });

                $('#postForm').submit(function() {
                    $(this).block({ message: '<div class="spinner-border text-primary"></div>', timeout: 1000, css: { backgroundColor: "transparent", border: 0 }, overlayCSS: { backgroundColor: "#fff", opacity: .8 } });
                });
            });
        </script>
    @endpush
</x-app-layout>
