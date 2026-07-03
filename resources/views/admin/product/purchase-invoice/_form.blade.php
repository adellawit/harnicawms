@php
    $isEdit = !empty($kontrabon);
    $formAction = $isEdit
        ? route('product.purchase-invoice.edit.data')
        : route('product.purchase-invoice.insert.data');
    $selectedItems = collect(old('items', $isEdit
        ? $kontrabon->items->map(fn ($item) => [
            'purchase_order_id' => $item->purchase_order_id,
            'total' => (float) $item->total,
            'supplier_invoice_number' => $item->supplier_invoice_number,
            'supplier_invoice_date' => optional($item->supplier_invoice_date)->format('d/m/Y'),
            'notes' => $item->notes,
        ])->all()
        : []));
@endphp

<form method="POST" action="{{ $formAction }}" id="kontrabonForm">
    @csrf
    @if($isEdit)
        <input type="hidden" name="id" value="{{ $kontrabon->id }}">
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">{{ $isEdit ? 'Edit Kontrabon' : 'Create Invoice' }}</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="kontrabon_date">Kontrabon Date <span class="text-danger">*</span></label>
                    <input type="text" id="kontrabon_date" name="kontrabon_date" class="form-control"
                        placeholder="DD/MM/YYYY"
                        value="{{ old('kontrabon_date', $isEdit ? $kontrabon->kontrabon_date->format('d/m/Y') : date('d/m/Y')) }}" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="supplier_id">Supplier <span class="text-danger">*</span></label>
                    <select id="supplier_id" name="supplier_id" class="form-select select2-supplier" required>
                        <option value="">-- Pilih Supplier --</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}"
                                {{ old('supplier_id', $isEdit ? $kontrabon->supplier_id : '') == $supplier->id ? 'selected' : '' }}>
                                {{ $supplier->code }} - {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">PO dengan sisa tagihan yang belum di-invoice. Nominal invoice dapat diisi partial.</small>
                </div>
                <div class="col-12">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $isEdit ? $kontrabon->notes : '') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Purchase Order & Faktur Supplier</h5>
            <span class="badge bg-label-primary" id="selectedCount">0 PO dipilih</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0" id="poTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="checkAllPo" disabled></th>
                            <th>PO Number</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="text-end">Total PO</th>
                            <th class="text-end">Sisa Tagihan</th>
                            <th class="text-end">Nominal Invoice</th>
                            <th>No. Faktur Supplier</th>
                            <th>Tgl Faktur</th>
                        </tr>
                    </thead>
                    <tbody id="poTableBody">
                        <tr id="poEmptyRow">
                            <td colspan="9" class="text-center text-muted py-4">Pilih supplier untuk memuat daftar PO.</td>
                        </tr>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="6" class="text-end">Total Kontrabon</th>
                            <th class="text-end" id="totalDisplay">0</th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ $isEdit ? route('product.purchase-invoice.detail.view', $kontrabon->id) : route('product.purchase-invoice.index.view') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>Back
        </a>
        <div class="d-flex gap-2">
            <button type="submit" name="submit" value="0" class="btn btn-label-primary">
                <i class="ti ti-device-floppy me-1"></i>Save Draft
            </button>
            <button type="submit" name="submit" value="1" class="btn btn-primary">
                <i class="ti ti-send me-1"></i>Save & Submit
            </button>
        </div>
    </div>
</form>

<div class="modal fade" id="poItemsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Detail Item PO</h5>
                    <small class="text-muted" id="poItemsModalSubtitle">-</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Unit</th>
                                <th>Batch</th>
                                <th>Expired</th>
                                <th class="text-end">Qty PO</th>
                                <th class="text-end">Diterima</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="poItemsModalBody">
                            <tr><td colspan="8" class="text-center text-muted py-3">-</td></tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="7" class="text-end">Total PO</th>
                                <th class="text-end" id="poItemsModalTotal">0</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="poItemsModalDetailLink" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                    <i class="ti ti-external-link me-1"></i>Buka Detail PO
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('page-css')
    <style>
        .po-number-link {
            cursor: pointer;
            text-decoration: none;
        }
        .po-number-link:hover {
            text-decoration: underline;
        }
    </style>
@endpush
@push('vendor-css')
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endpush
@push('vendor-js')
    <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endpush
@push('page-js')
    <script>
        $(function () {
            var eligibleUrl = "{{ route('product.purchase-invoice.eligible-pos') }}";
            var excludeKontrabonId = @json($isEdit ? $kontrabon->id : null);
            var oldItems = @json($selectedItems->keyBy('purchase_order_id'));
            var rowIndex = 0;
            var poCache = {};

            flatpickr('#kontrabon_date', {
                dateFormat: 'd/m/Y',
                disableMobile: true,
                allowInput: true,
            });

            function initPoInvoiceDatePicker($scope) {
                ($scope || document).querySelectorAll('.po-invoice-date').forEach(function (el) {
                    if (el._flatpickr) {
                        return;
                    }
                    flatpickr(el, {
                        dateFormat: 'd/m/Y',
                        disableMobile: true,
                        allowInput: true,
                    });
                });
            }

            function syncFlatpickrInput(input) {
                if (!input || !input._flatpickr) {
                    return;
                }

                var fp = input._flatpickr;
                if (fp.selectedDates.length) {
                    input.value = fp.formatDate(fp.selectedDates[0], 'd/m/Y');
                }
            }

            function syncAllFlatpickrInputs() {
                syncFlatpickrInput(document.getElementById('kontrabon_date'));
                document.querySelectorAll('.po-invoice-date').forEach(syncFlatpickrInput);
            }
            $('.select2-supplier').select2({ placeholder: '-- Pilih Supplier --', allowClear: true, width: '100%' });

            function formatNumber(val) {
                return parseFloat(val || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
            }

            function parseNum(val) {
                return parseFloat(String(val || 0).replace(/\./g, '').replace(',', '.')) || 0;
            }

            function updateTotals() {
                var total = 0;
                var count = 0;
                $('#poTableBody tr').each(function () {
                    var $row = $(this);
                    if (!$row.find('.po-row').is(':checked')) {
                        return;
                    }
                    total += parseNum($row.find('.po-invoice-amount').val());
                    count++;
                });
                $('#totalDisplay').text(formatNumber(total));
                $('#selectedCount').text(count + ' PO dipilih');
            }

            function renderRows(data) {
                rowIndex = 0;
                poCache = {};
                var $body = $('#poTableBody').empty();
                if (!data.length) {
                    $body.append('<tr><td colspan="9" class="text-center text-muted py-4">Tidak ada PO eligible untuk supplier ini.</td></tr>');
                    $('#checkAllPo').prop('disabled', true);
                    updateTotals();
                    return;
                }

                data.forEach(function (po) {
                    poCache[po.id] = po;
                    var old = oldItems[po.id] || {};
                    var checked = old.purchase_order_id ? 'checked' : '';
                    var invoiceNo = old.supplier_invoice_number || '';
                    var invoiceDate = old.supplier_invoice_date || '';
                    var remaining = po.remaining_invoice_amount ?? po.total;
                    var invoiceAmount = old.total !== undefined ? old.total : remaining;
                    var html = '<tr>'
                        + '<td><input type="checkbox" class="form-check-input po-row" '+checked+' /></td>'
                        + '<td><a href="javascript:void(0)" class="po-number-link fw-semibold" data-po-id="'+po.id+'" title="Lihat detail item">'+po.purchase_number+' <i class="ti ti-eye text-muted ms-1"></i></a></td>'
                        + '<td>'+(po.purchase_date || '-')+'</td>'
                        + '<td>'+(po.po_kind_label || '-')+'</td>'
                        + '<td>'+(po.status || '-')+'</td>'
                        + '<td class="text-end">'+(po.total_fmt || formatNumber(po.total))+'</td>'
                        + '<td class="text-end text-warning fw-semibold">'+(po.remaining_fmt || formatNumber(remaining))+'</td>'
                        + '<td><input type="text" class="form-control form-control-sm po-invoice-amount number-format" value="'+invoiceAmount+'" data-max="'+remaining+'" /></td>'
                        + '<td><input type="text" class="form-control form-control-sm po-invoice-no" value="'+invoiceNo+'" placeholder="No. faktur" /></td>'
                        + '<td><input type="text" class="form-control form-control-sm po-invoice-date" value="'+invoiceDate+'" placeholder="DD/MM/YYYY" /></td>'
                        + '</tr>';
                    $body.append(html);
                    var $row = $body.find('tr:last');
                    $row.data('po-id', po.id);
                    $row.data('remaining', remaining);
                    if (checked) {
                        appendHiddenInputs($row, po.id, invoiceAmount, invoiceNo, invoiceDate, old.notes || '');
                    }
                });

                $('#checkAllPo').prop('disabled', false);
                initPoInvoiceDatePicker($body[0]);
                updateTotals();
            }

            function showPoItemsModal(poId) {
                var po = poCache[poId];
                if (!po) return;

                $('#poItemsModalSubtitle').text(
                    (po.purchase_number || '-') + ' · ' + (po.purchase_date || '-')
                    + ' · ' + (po.po_kind_label || '-') + ' · ' + (po.status || '-')
                );
                $('#poItemsModalDetailLink').attr('href', po.detail_url || '#');

                var rows = '';
                var items = po.items || [];
                if (!items.length) {
                    rows = '<tr><td colspan="8" class="text-center text-muted py-3">Tidak ada item.</td></tr>';
                } else {
                    items.forEach(function (item) {
                        rows += '<tr>'
                            + '<td>' + (item.product_label || item.product_name || '-') + '</td>'
                            + '<td>' + (item.unit_label || '-') + '</td>'
                            + '<td>' + (item.batch_number || '-') + '</td>'
                            + '<td>' + (item.expiry_date || '-') + '</td>'
                            + '<td class="text-end">' + formatNumber(item.quantity) + '</td>'
                            + '<td class="text-end">' + formatNumber(item.quantity_received) + '</td>'
                            + '<td class="text-end">' + formatNumber(item.unit_price) + '</td>'
                            + '<td class="text-end">' + formatNumber(item.subtotal) + '</td>'
                            + '</tr>';
                        if (item.carton_display && item.carton_display !== '-') {
                            rows += '<tr class="table-light"><td colspan="8" class="py-1 ps-4"><small class="text-muted">MC: ' + item.carton_display + '</small></td></tr>';
                        }
                    });
                }

                $('#poItemsModalBody').html(rows);
                $('#poItemsModalTotal').text(po.total_fmt || formatNumber(po.total));
                bootstrap.Modal.getOrCreateInstance(document.getElementById('poItemsModal')).show();
            }

            function appendHiddenInputs($row, poId, invoiceAmount, invoiceNo, invoiceDate, notes) {
                $row.find('.hidden-inputs').remove();
                var idx = rowIndex++;
                var hidden = '<div class="hidden-inputs">'
                    + '<input type="hidden" name="items['+idx+'][purchase_order_id]" value="'+poId+'">'
                    + '<input type="hidden" name="items['+idx+'][total]" class="hidden-invoice-amount" value="'+(invoiceAmount || '')+'">'
                    + '<input type="hidden" name="items['+idx+'][supplier_invoice_number]" class="hidden-invoice-no" value="'+(invoiceNo || '')+'">'
                    + '<input type="hidden" name="items['+idx+'][supplier_invoice_date]" class="hidden-invoice-date" value="'+(invoiceDate || '')+'">'
                    + '<input type="hidden" name="items['+idx+'][notes]" class="hidden-notes" value="'+(notes || '')+'">'
                    + '</div>';
                $row.append(hidden);
            }

            function syncRowInputs($row) {
                var checked = $row.find('.po-row').is(':checked');
                var poId = $row.data('po-id');
                var invoiceAmount = $row.find('.po-invoice-amount').val();
                var invoiceNo = $row.find('.po-invoice-no').val();
                var invoiceDate = $row.find('.po-invoice-date').val();
                if (checked) {
                    appendHiddenInputs($row, poId, invoiceAmount, invoiceNo, invoiceDate, '');
                } else {
                    $row.find('.hidden-inputs').remove();
                }
                updateTotals();
            }

            function loadEligiblePos() {
                var supplierId = $('#supplier_id').val();
                if (!supplierId) {
                    $body.append('<tr><td colspan="9" class="text-center text-muted py-4">Pilih supplier untuk memuat daftar PO.</td></tr>');
                    $('#checkAllPo').prop('disabled', true);
                    updateTotals();
                    return;
                }

                $.get(eligibleUrl, {
                    supplier_id: supplierId,
                    exclude_kontrabon_id: excludeKontrabonId
                }, function (res) {
                    renderRows(res.data || []);
                }).fail(function () {
                    alert('Gagal memuat daftar PO.');
                });
            }

            $('#supplier_id').on('change', loadEligiblePos);
            $(document).on('click', '.po-number-link', function (e) {
                e.preventDefault();
                showPoItemsModal($(this).data('po-id'));
            });
            $(document).on('change', '.po-row', function () {
                syncRowInputs($(this).closest('tr'));
            });
            $(document).on('input change', '.po-invoice-no, .po-invoice-date, .po-invoice-amount', function () {
                var $row = $(this).closest('tr');
                if ($row.find('.po-row').is(':checked')) {
                    $row.find('.hidden-invoice-amount').val($row.find('.po-invoice-amount').val());
                    $row.find('.hidden-invoice-no').val($row.find('.po-invoice-no').val());
                    $row.find('.hidden-invoice-date').val($row.find('.po-invoice-date').val());
                }
                if ($(this).hasClass('po-invoice-amount')) {
                    updateTotals();
                }
            });
            $('#checkAllPo').on('change', function () {
                var checked = $(this).is(':checked');
                $('#poTableBody .po-row').prop('checked', checked).each(function () {
                    syncRowInputs($(this).closest('tr'));
                });
            });

            $('#kontrabonForm').on('submit', function (e) {
                syncAllFlatpickrInputs();
                var hasError = false;
                $('#poTableBody tr').each(function () {
                    var $row = $(this);
                    if (!$row.find('.po-row').is(':checked')) {
                        return;
                    }
                    var max = parseFloat($row.data('remaining') || 0);
                    var amount = parseNum($row.find('.po-invoice-amount').val());
                    if (amount <= 0) {
                        hasError = 'Nominal invoice harus lebih dari 0.';
                        return false;
                    }
                    if (amount > max + 0.000001) {
                        hasError = 'Nominal invoice melebihi sisa tagihan PO (maks: ' + formatNumber(max) + ').';
                        return false;
                    }
                    $row.find('.hidden-invoice-amount').val(amount);
                    $row.find('.hidden-invoice-no').val($row.find('.po-invoice-no').val());
                    $row.find('.hidden-invoice-date').val($row.find('.po-invoice-date').val());
                });

                if (hasError) {
                    e.preventDefault();
                    alert(hasError);
                    return;
                }

                var selected = $('#poTableBody .po-row:checked').length;
                if (!selected) {
                    e.preventDefault();
                    alert('Pilih minimal 1 Purchase Order.');
                }
            });

            if ($('#supplier_id').val()) {
                loadEligiblePos();
            }
        });
    </script>
@endpush
