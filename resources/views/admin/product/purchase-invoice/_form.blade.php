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
            'attachment_name' => $item->attachment_name,
            'attachment_url' => $item->attachment_url,
            'has_attachment' => (bool) $item->has_attachment,
        ])->all()
        : []));
@endphp

<form method="POST" action="{{ $formAction }}" id="kontrabonForm" enctype="multipart/form-data">
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
                <div class="col-md-3">
                    <label class="form-label" for="kontrabon_date">Kontrabon Date <span class="text-danger">*</span></label>
                    <input type="text" id="kontrabon_date" name="kontrabon_date" class="form-control"
                        placeholder="DD/MM/YYYY"
                        value="{{ old('kontrabon_date', $isEdit ? $kontrabon->kontrabon_date->format('d/m/Y') : date('d/m/Y')) }}" required>
                </div>
                <div class="col-md-5">
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
                    <small class="text-muted">Pilih PO sudah diterima atau belum diterima, lalu isi nominal (DP / pelunasan).</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Catatan opsional...">{{ old('notes', $isEdit ? $kontrabon->notes : '') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-0">Purchase Order & Faktur Supplier</h5>
                <small class="text-muted">Pilih jenis PO, centang, isi nominal & data faktur.</small>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="btn-group btn-group-sm" role="group" aria-label="Filter penerimaan PO">
                    <input type="radio" class="btn-check" name="po_receive_scope" id="poScopeReceived" value="received" autocomplete="off" checked>
                    <label class="btn btn-outline-primary" for="poScopeReceived">Sudah diterima</label>
                    <input type="radio" class="btn-check" name="po_receive_scope" id="poScopeUnreceived" value="unreceived" autocomplete="off">
                    <label class="btn btn-outline-primary" for="poScopeUnreceived">Belum diterima</label>
                </div>
                <div class="form-check mb-0">
                    <input type="checkbox" class="form-check-input" id="checkAllPo" disabled>
                    <label class="form-check-label small" for="checkAllPo">Pilih semua</label>
                </div>
                <span class="badge bg-label-primary" id="selectedCount">0 PO dipilih</span>
            </div>
        </div>
        <div class="card-body p-3">
            <div id="poList" class="po-invoice-list">
                <div id="poEmptyState" class="po-empty-state text-center text-muted py-5">
                    <i class="ti ti-file-invoice d-block mb-2" style="font-size:2rem;opacity:.45;"></i>
                    Pilih supplier untuk memuat daftar PO.
                </div>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 bg-light">
            <small class="text-muted mb-0">File: PDF / JPG / PNG / WEBP · maks. 5 MB per PO</small>
            <div class="d-flex align-items-baseline gap-2">
                <span class="text-muted small">Total Kontrabon</span>
                <strong class="fs-5 text-primary" id="totalDisplay">0</strong>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pb-4 mb-2">
        <a href="{{ $isEdit ? route('product.purchase-invoice.detail.view', $kontrabon->id) : route('product.purchase-invoice.index.view') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>Back
        </a>
        <div class="d-flex flex-wrap gap-2">
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
        .po-invoice-list {
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }
        .po-invoice-card {
            border: 1px solid #e7e7e8;
            border-radius: .5rem;
            background: #fff;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .po-invoice-card.is-selected {
            border-color: rgba(var(--bs-primary-rgb), .45);
            box-shadow: 0 0 0 1px rgba(var(--bs-primary-rgb), .12);
            background: rgba(var(--bs-primary-rgb), .015);
        }
        .po-invoice-card-main {
            display: grid;
            grid-template-columns: auto minmax(0, 1.4fr) minmax(110px, .7fr) minmax(110px, .7fr) minmax(150px, .9fr);
            gap: .75rem 1rem;
            align-items: center;
            padding: .9rem 1rem;
        }
        @media (max-width: 991.98px) {
            .po-invoice-card-main {
                grid-template-columns: auto 1fr;
            }
            .po-invoice-card-main .po-meta-block,
            .po-invoice-card-main .po-amount-block,
            .po-invoice-card-main .po-nominal-block {
                grid-column: 2;
            }
        }
        .po-invoice-card-main .po-check {
            margin-top: .15rem;
        }
        .po-number-link {
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
        }
        .po-number-link:hover {
            text-decoration: underline;
        }
        .po-meta-chips {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            margin-top: .35rem;
        }
        .po-meta-chip {
            display: inline-flex;
            align-items: center;
            padding: .15rem .45rem;
            border-radius: .25rem;
            background: #f2f3f5;
            color: #697a8d;
            font-size: .75rem;
            line-height: 1.2;
        }
        .po-amount-block .label,
        .po-nominal-block .label,
        .po-faktur-panel .label {
            display: block;
            font-size: .72rem;
            color: #697a8d;
            margin-bottom: .2rem;
        }
        .po-amount-block .value {
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }
        .po-amount-block .value.is-remaining {
            color: #ff9f43;
        }
        .po-faktur-panel {
            display: none;
            border-top: 1px dashed #e7e7e8;
            padding: .85rem 1rem 1rem;
            background: rgba(0,0,0,.015);
        }
        .po-invoice-card.is-selected .po-faktur-panel {
            display: block;
        }
        .po-faktur-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem 1rem;
        }
        @media (max-width: 767.98px) {
            .po-faktur-grid {
                grid-template-columns: 1fr;
            }
        }
        .po-empty-state {
            border: 1px dashed #d9dee3;
            border-radius: .5rem;
            background: #fafbfc;
        }
        .po-items-preview {
            border-top: 1px dashed #e7e7e8;
            padding: .65rem 1rem .85rem;
        }
        .po-items-preview .table {
            margin-bottom: 0;
            font-size: .8rem;
        }
        .po-items-preview .table th {
            font-size: .72rem;
            color: #697a8d;
            font-weight: 600;
            white-space: nowrap;
        }
        .po-existing-file {
            margin-top: .4rem;
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
    <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
@endpush
@push('page-js')
    <script>
        $(function () {
            var eligibleUrl = "{{ route('product.purchase-invoice.eligible-pos') }}";
            var excludeKontrabonId = @json($isEdit ? $kontrabon->id : null);
            var oldItems = @json($selectedItems->keyBy('purchase_order_id'));
            var rowIndex = 0;
            var poCache = {};
            var autoSwitchedScope = false;

            function getReceiveScope() {
                return $('input[name="po_receive_scope"]:checked').val() || 'received';
            }

            function emptyPoMessage(kind) {
                if (kind === 'no-supplier') {
                    return 'Pilih supplier untuk memuat daftar PO.';
                }
                if (getReceiveScope() === 'unreceived') {
                    return 'Tidak ada PO Process yang belum diterima dengan sisa tagihan.';
                }
                return 'Tidak ada PO yang sudah diterima dengan sisa tagihan.';
            }

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

            function escapeHtml(str) {
                return String(str || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function initNumberInputs(scope) {
                $(scope || document).find('.number-format').each(function () {
                    if ($(this).data('cleave')) {
                        return;
                    }
                    var raw = parseNum($(this).val());
                    var cleave = new Cleave(this, {
                        numeral: true,
                        numeralThousandsGroupStyle: 'thousand',
                        numeralDecimalMark: ',',
                        delimiter: '.',
                        numeralDecimalScale: 2,
                        onValueChanged: function () {
                            updateTotals();
                        }
                    });
                    cleave.setRawValue(String(raw));
                    $(this).data('cleave', cleave);
                });
            }

            function getInvoiceAmount($card) {
                var $el = $card.find('.po-invoice-amount');
                var cleave = $el.data('cleave');
                if (cleave) {
                    return parseFloat(cleave.getRawValue()) || 0;
                }
                return parseNum($el.val());
            }

            function updateTotals() {
                var total = 0;
                var count = 0;
                $('#poList .po-invoice-card').each(function () {
                    var $card = $(this);
                    if (!$card.find('.po-row').is(':checked')) {
                        return;
                    }
                    total += getInvoiceAmount($card);
                    count++;
                });
                $('#totalDisplay').text(formatNumber(total));
                $('#selectedCount').text(count + ' PO dipilih');
            }

            function setCardSelected($card, checked) {
                $card.toggleClass('is-selected', checked);
                $card.find('.po-faktur-panel').find('input, select, textarea').prop('disabled', !checked);
                if (!checked) {
                    $card.find('.po-invoice-file').removeAttr('name');
                    $card.find('.hidden-inputs').remove();
                }
            }

            function renderPoItemsPreview(po) {
                var items = po.items || [];
                if (!items.length) {
                    return '<div class="po-items-preview"><small class="text-muted">Tidak ada item.</small></div>';
                }
                var rows = '';
                items.forEach(function (item) {
                    rows += '<tr>'
                        + '<td>' + escapeHtml(item.product_name || item.product_label || '-') + '</td>'
                        + '<td>' + escapeHtml(item.unit_label || '-') + '</td>'
                        + '<td class="text-end">' + formatNumber(item.quantity) + '</td>'
                        + '<td class="text-end">' + formatNumber(item.quantity_received) + '</td>'
                        + '<td class="text-end">' + formatNumber(item.subtotal) + '</td>'
                        + '</tr>';
                });
                return '<div class="po-items-preview">'
                    + '<div class="table-responsive">'
                    + '<table class="table table-sm table-bordered mb-0">'
                    + '<thead class="table-light"><tr>'
                    + '<th>Produk</th><th>Satuan</th>'
                    + '<th class="text-end">Qty PO</th><th class="text-end">Diterima</th>'
                    + '<th class="text-end">Subtotal</th>'
                    + '</tr></thead><tbody>' + rows + '</tbody></table></div></div>';
            }

            function renderRows(data) {
                rowIndex = 0;
                poCache = {};
                var $list = $('#poList').empty();

                if (!data.length) {
                    $list.append(
                        '<div id="poEmptyState" class="po-empty-state text-center text-muted py-5">'
                        + '<i class="ti ti-file-off d-block mb-2" style="font-size:2rem;opacity:.45;"></i>'
                        + emptyPoMessage() + '</div>'
                    );
                    $('#checkAllPo').prop('disabled', true).prop('checked', false);
                    updateTotals();
                    return;
                }

                data.forEach(function (po) {
                    poCache[po.id] = po;
                    var old = oldItems[po.id] || {};
                    var checked = !!old.purchase_order_id;
                    var invoiceNo = old.supplier_invoice_number || '';
                    var invoiceDate = old.supplier_invoice_date || '';
                    var remaining = po.remaining_invoice_amount ?? po.total;
                    var invoiceAmount = old.total !== undefined ? old.total : remaining;

                    var existingFileHtml = '';
                    if (old.has_attachment && old.attachment_url) {
                        existingFileHtml = '<div class="po-existing-file">'
                            + '<a href="'+escapeHtml(old.attachment_url)+'" target="_blank" rel="noopener" class="small">'
                            + '<i class="ti ti-paperclip me-1"></i>'+escapeHtml(old.attachment_name || 'Lihat file')
                            + '</a>'
                            + '<div class="form-check mt-1 mb-0">'
                            + '<input type="checkbox" class="form-check-input po-remove-file" value="1" id="rm-'+po.id+'">'
                            + '<label class="form-check-label small" for="rm-'+po.id+'">Hapus file lama</label>'
                            + '</div></div>';
                    }

                    var html = '<div class="po-invoice-card'+(checked ? ' is-selected' : '')+'">'
                        + '<div class="po-invoice-card-main">'
                        +   '<div class="po-check"><input type="checkbox" class="form-check-input po-row" '+(checked ? 'checked' : '')+' /></div>'
                        +   '<div class="po-meta-block min-w-0">'
                        +     '<a href="javascript:void(0)" class="po-number-link" data-po-id="'+po.id+'" title="Lihat detail item">'
                        +       escapeHtml(po.purchase_number)+' <i class="ti ti-eye text-muted"></i>'
                        +     '</a>'
                        +     '<div class="po-meta-chips">'
                        +       '<span class="po-meta-chip"><i class="ti ti-calendar me-1"></i>'+escapeHtml(po.purchase_date || '-')+'</span>'
                        +       (po.expected_delivery_date ? '<span class="po-meta-chip"><i class="ti ti-truck-delivery me-1"></i>Kirim '+escapeHtml(po.expected_delivery_date)+'</span>' : '')
                        +       '<span class="po-meta-chip">'+escapeHtml(po.po_kind_label || '-')+'</span>'
                        +       '<span class="po-meta-chip">'+escapeHtml(po.status || '-')+'</span>'
                        +       '<span class="po-meta-chip">'+(po.has_receive ? 'Diterima '+(po.received_qty_fmt || '0')+' / '+(po.ordered_qty_fmt || '0') : 'Belum diterima')+'</span>'
                        +     '</div>'
                        +   '</div>'
                        +   '<div class="po-amount-block">'
                        +     '<span class="label">Total PO</span>'
                        +     '<div class="value">'+(po.total_fmt || formatNumber(po.total))+'</div>'
                        +   '</div>'
                        +   '<div class="po-amount-block">'
                        +     '<span class="label">Sisa Tagihan</span>'
                        +     '<div class="value is-remaining">'+(po.remaining_fmt || formatNumber(remaining))+'</div>'
                        +   '</div>'
                        +   '<div class="po-nominal-block">'
                        +     '<span class="label">Nominal Invoice</span>'
                        +     '<input type="text" class="form-control form-control-sm po-invoice-amount number-format" inputmode="decimal" value="'+formatNumber(invoiceAmount)+'" data-max="'+remaining+'" '+(checked ? '' : 'disabled')+' />'
                        +   '</div>'
                        + '</div>'
                        + renderPoItemsPreview(po)
                        + '<div class="po-faktur-panel">'
                        +   '<div class="po-faktur-grid">'
                        +     '<div>'
                        +       '<span class="label">No. Faktur Supplier</span>'
                        +       '<input type="text" class="form-control form-control-sm po-invoice-no" value="'+escapeHtml(invoiceNo)+'" placeholder="No. faktur" '+(checked ? '' : 'disabled')+' />'
                        +     '</div>'
                        +     '<div>'
                        +       '<span class="label">Tgl Faktur</span>'
                        +       '<input type="text" class="form-control form-control-sm po-invoice-date" value="'+escapeHtml(invoiceDate)+'" placeholder="DD/MM/YYYY" '+(checked ? '' : 'disabled')+' />'
                        +     '</div>'
                        +     '<div>'
                        +       '<span class="label">Upload File <span class="text-muted">(opsional)</span></span>'
                        +       '<input type="file" class="form-control form-control-sm po-invoice-file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" '+(checked ? '' : 'disabled')+' />'
                        +       existingFileHtml
                        +     '</div>'
                        +   '</div>'
                        + '</div>'
                        + '</div>';

                    $list.append(html);
                    var $card = $list.children('.po-invoice-card').last();
                    $card.data('po-id', po.id);
                    $card.data('remaining', remaining);
                    initNumberInputs($card);
                    if (checked) {
                        appendHiddenInputs($card, po.id, getInvoiceAmount($card), invoiceNo, invoiceDate, old.notes || '');
                    }
                });

                $('#checkAllPo').prop('disabled', false);
                initPoInvoiceDatePicker($list[0]);
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
                            + '<td>' + escapeHtml(item.product_label || item.product_name || '-') + '</td>'
                            + '<td>' + escapeHtml(item.unit_label || '-') + '</td>'
                            + '<td>' + escapeHtml(item.batch_number || '-') + '</td>'
                            + '<td>' + escapeHtml(item.expiry_date || '-') + '</td>'
                            + '<td class="text-end">' + formatNumber(item.quantity) + '</td>'
                            + '<td class="text-end">' + formatNumber(item.quantity_received) + '</td>'
                            + '<td class="text-end">' + formatNumber(item.unit_price) + '</td>'
                            + '<td class="text-end">' + formatNumber(item.subtotal) + '</td>'
                            + '</tr>';
                        if (item.carton_display && item.carton_display !== '-') {
                            rows += '<tr class="table-light"><td colspan="8" class="py-1 ps-4"><small class="text-muted">MC: ' + escapeHtml(item.carton_display) + '</small></td></tr>';
                        }
                    });
                }

                $('#poItemsModalBody').html(rows);
                $('#poItemsModalTotal').text(po.total_fmt || formatNumber(po.total));
                bootstrap.Modal.getOrCreateInstance(document.getElementById('poItemsModal')).show();
            }

            function appendHiddenInputs($card, poId, invoiceAmount, invoiceNo, invoiceDate, notes) {
                $card.find('.hidden-inputs').remove();
                var idx = rowIndex++;
                var removeChecked = $card.find('.po-remove-file').is(':checked') ? '1' : '';
                var hidden = '<div class="hidden-inputs d-none">'
                    + '<input type="hidden" name="items['+idx+'][purchase_order_id]" value="'+poId+'">'
                    + '<input type="hidden" name="items['+idx+'][total]" class="hidden-invoice-amount" value="'+(invoiceAmount || '')+'">'
                    + '<input type="hidden" name="items['+idx+'][supplier_invoice_number]" class="hidden-invoice-no" value="'+(invoiceNo || '')+'">'
                    + '<input type="hidden" name="items['+idx+'][supplier_invoice_date]" class="hidden-invoice-date" value="'+(invoiceDate || '')+'">'
                    + '<input type="hidden" name="items['+idx+'][notes]" class="hidden-notes" value="'+(notes || '')+'">'
                    + '<input type="hidden" name="items['+idx+'][remove_attachment]" class="hidden-remove-file" value="'+removeChecked+'">'
                    + '</div>';
                $card.append(hidden);
                $card.find('.po-invoice-file').attr('name', 'items['+idx+'][attachment]');
                $card.data('item-index', idx);
            }

            function syncCardInputs($card) {
                var checked = $card.find('.po-row').is(':checked');
                setCardSelected($card, checked);
                $card.find('.po-invoice-amount').prop('disabled', !checked);
                if (checked) {
                    appendHiddenInputs(
                        $card,
                        $card.data('po-id'),
                        getInvoiceAmount($card),
                        $card.find('.po-invoice-no').val(),
                        $card.find('.po-invoice-date').val(),
                        ''
                    );
                }
                updateTotals();
            }

            function loadEligiblePos() {
                var supplierId = $('#supplier_id').val();
                var $list = $('#poList').empty();
                if (!supplierId) {
                    $list.append(
                        '<div id="poEmptyState" class="po-empty-state text-center text-muted py-5">'
                        + '<i class="ti ti-file-invoice d-block mb-2" style="font-size:2rem;opacity:.45;"></i>'
                        + 'Pilih supplier untuk memuat daftar PO.</div>'
                    );
                    $('#checkAllPo').prop('disabled', true).prop('checked', false);
                    updateTotals();
                    return;
                }

                $list.append(
                    '<div class="po-empty-state text-center text-muted py-4">'
                    + '<div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>'
                    + 'Memuat daftar PO...</div>'
                );

                $.get(eligibleUrl, {
                    supplier_id: supplierId,
                    exclude_kontrabon_id: excludeKontrabonId,
                    receive_scope: getReceiveScope()
                }, function (res) {
                    var data = res.data || [];
                    var oldIds = Object.keys(oldItems || {});
                    if (!autoSwitchedScope && oldIds.length && !oldIds.some(function (id) {
                        return data.some(function (po) { return po.id === id; });
                    })) {
                        autoSwitchedScope = true;
                        var other = getReceiveScope() === 'received' ? 'unreceived' : 'received';
                        $('input[name="po_receive_scope"][value="'+other+'"]').prop('checked', true);
                        loadEligiblePos();
                        return;
                    }
                    renderRows(data);
                }).fail(function () {
                    $list.html(
                        '<div class="po-empty-state text-center text-danger py-4">Gagal memuat daftar PO.</div>'
                    );
                    alert('Gagal memuat daftar PO.');
                });
            }

            $('#supplier_id').on('change', function () {
                autoSwitchedScope = false;
                loadEligiblePos();
            });
            $('input[name="po_receive_scope"]').on('change', function () {
                autoSwitchedScope = true;
                loadEligiblePos();
            });
            $(document).on('click', '.po-number-link', function (e) {
                e.preventDefault();
                showPoItemsModal($(this).data('po-id'));
            });
            $(document).on('change', '.po-row', function () {
                syncCardInputs($(this).closest('.po-invoice-card'));
            });
            $(document).on('change', '.po-remove-file', function () {
                var $card = $(this).closest('.po-invoice-card');
                if ($card.find('.po-row').is(':checked')) {
                    $card.find('.hidden-remove-file').val($(this).is(':checked') ? '1' : '');
                }
            });
            $(document).on('input change', '.po-invoice-no, .po-invoice-date, .po-invoice-amount', function () {
                var $card = $(this).closest('.po-invoice-card');
                if ($card.find('.po-row').is(':checked')) {
                    $card.find('.hidden-invoice-amount').val(getInvoiceAmount($card));
                    $card.find('.hidden-invoice-no').val($card.find('.po-invoice-no').val());
                    $card.find('.hidden-invoice-date').val($card.find('.po-invoice-date').val());
                }
                if ($(this).hasClass('po-invoice-amount')) {
                    updateTotals();
                }
            });
            $('#checkAllPo').on('change', function () {
                var checked = $(this).is(':checked');
                $('#poList .po-row').prop('checked', checked).each(function () {
                    syncCardInputs($(this).closest('.po-invoice-card'));
                });
            });

            $('#kontrabonForm').on('submit', function (e) {
                syncAllFlatpickrInputs();
                rowIndex = 0;
                var hasError = false;
                $('#poList .po-invoice-card').each(function () {
                    var $card = $(this);
                    if (!$card.find('.po-row').is(':checked')) {
                        $card.find('.po-invoice-file').removeAttr('name');
                        return;
                    }
                    var max = parseFloat($card.data('remaining') || 0);
                    var amount = getInvoiceAmount($card);
                    if (amount <= 0) {
                        hasError = 'Nominal invoice harus lebih dari 0.';
                        return false;
                    }
                    if (amount > max + 0.000001) {
                        hasError = 'Nominal invoice melebihi sisa tagihan PO (maks: ' + formatNumber(max) + ').';
                        return false;
                    }
                    appendHiddenInputs(
                        $card,
                        $card.data('po-id'),
                        amount,
                        $card.find('.po-invoice-no').val(),
                        $card.find('.po-invoice-date').val(),
                        ''
                    );
                });

                if (hasError) {
                    e.preventDefault();
                    alert(hasError);
                    return;
                }

                var selected = $('#poList .po-row:checked').length;
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
