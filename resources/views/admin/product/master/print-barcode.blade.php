<x-app-layout>

    @section('title', 'Print Barcode | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => route('product.index.view')],
                ['label' => 'Print Barcode', 'active' => true],
            ]"
        />

        @if (session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Print Barcode QR — {{ $product->name }}</h5>
                        <small class="text-muted">SKU: {{ $product->sku ?: '-' }} | Code: {{ $product->code }}</small>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-4">
                            <div class="d-flex gap-2">
                                <i class="ti ti-info-circle mt-1"></i>
                                <div>
                                    <strong>Nomor barcode per level satuan</strong> (tahun produksi + level + urutan):
                                    <ul class="mb-0 mt-1 small">
                                        <li>Level 1 (satuan terbesar / Dus): <code>{{ date('y') }}1000000001</code>, <code>{{ date('y') }}1000000002</code>, ...</li>
                                        <li>Level 2 (Box): <code>{{ date('y') }}2000000001</code>, <code>{{ date('y') }}2000000002</code>, ...</li>
                                        <li>Level 3 (Pcs): <code>{{ date('y') }}3000000001</code>, <code>{{ date('y') }}3000000002</code>, ...</li>
                                    </ul>
                                    <span class="small">Setiap level punya penomoran sendiri mulai dari 1. Preview dan PDF diurutkan hierarki: Karton → Pack → Box sesuai nomor seri per level.</span>
                                </div>
                            </div>
                        </div>

                        <form id="printForm">
                            @csrf

                            @if ($hasUnitHierarchy)
                                <div class="mb-3">
                                    <label class="form-label">Mode Cetak</label>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                name="print_mode"
                                                id="print_mode_hierarchy"
                                                value="hierarchy"
                                                checked
                                            >
                                            <label class="form-check-label" for="print_mode_hierarchy">
                                                <strong>Berdasarkan satuan terbesar</strong>
                                                <span class="text-muted d-block small">Qty menengah &amp; kecil otomatis mengikuti konversi (contoh: 1 Karton → 30 Pack → 300 Box)</span>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                name="print_mode"
                                                id="print_mode_single"
                                                value="single"
                                            >
                                            <label class="form-check-label" for="print_mode_single">
                                                <strong>Satuan tunggal</strong>
                                                <span class="text-muted d-block small">Cetak label untuk satu level satuan saja</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <input type="hidden" name="print_mode" value="single">
                            @endif

                            @if ($units->count() > 0)
                                <div class="mb-3">
                                    <label class="form-label" for="unit_id">Satuan <span class="text-danger">*</span></label>
                                    <select name="unit_id" id="unit_id" class="form-select select2" required>
                                        @foreach ($units as $unit)
                                            <option
                                                value="{{ $unit['id'] }}"
                                                data-level="{{ $unit['level'] }}"
                                                data-hint="{{ $unit['conversion_hint'] }}"
                                                data-content="{{ $unit['content_summary'] }}"
                                                data-child-hint="{{ $unit['child_labels_hint'] }}"
                                                data-format="{{ $unit['format_example'] }}"
                                                @selected($defaultUnitId === $unit['id'])
                                            >
                                                Level {{ $unit['level'] }} — {{ $unit['label'] }}@if($unit['name'] !== $unit['label']) ({{ $unit['name'] }})@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <div id="unitHint" class="form-text mt-2"></div>
                                </div>

                                <div class="mb-3 d-none" id="qtyBreakdownCard">
                                    <label class="form-label">Rincian Qty Label (otomatis)</label>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Level</th>
                                                    <th>Satuan</th>
                                                    <th class="text-end">Qty Label</th>
                                                    <th>Isi Label</th>
                                                </tr>
                                            </thead>
                                            <tbody id="qtyBreakdownBody"></tbody>
                                            <tfoot class="table-light">
                                                <tr>
                                                    <th colspan="2">Total semua level</th>
                                                    <th class="text-end" id="qtyBreakdownTotal">0</th>
                                                    <th></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <small class="text-muted d-block mt-1">Qty di bawah dihitung dari konversi satuan produk.</small>
                                </div>
                            @else
                                <x-alert type="warning" class="mb-3">
                                    Produk ini belum memiliki satuan. Atur satuan default di edit produk terlebih dahulu.
                                </x-alert>
                            @endif

                            @if ($variants->count() > 1)
                                <div class="mb-3">
                                    <label class="form-label" for="variant_id">Varian</label>
                                    <select name="variant_id" id="variant_id" class="form-select select2">
                                        <option value="">— Semua / Default —</option>
                                        @foreach ($variants as $variant)
                                            <option value="{{ $variant['id'] }}" @selected(($prefillVariantId ?? null) === $variant['id'])>{{ $variant['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="mb-4">
                                <label class="form-label" for="quantity" id="quantityLabel">Jumlah Satuan Terbesar (Qty)</label>
                                <input
                                    type="number"
                                    name="quantity"
                                    id="quantity"
                                    class="form-control"
                                    min="1"
                                    max="{{ $maxHierarchyParentQty ?? 500 }}"
                                    value="{{ old('quantity', $prefillQuantity ?? 1) }}"
                                    required
                                >
                                <small class="text-muted" id="quantityHelp">Masukkan qty satuan terbesar. Qty level menengah &amp; kecil akan dihitung otomatis.</small>
                            </div>

                            <input type="hidden" name="batch_id" id="batch_id" value="">

                            @if (! empty($serialStatus))
                                <div class="mb-4">
                                    <label class="form-label">Status Nomor Barcode</label>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-2" id="serialStatusTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Level</th>
                                                    <th>Satuan</th>
                                                    <th>Nomor Berikutnya</th>
                                                    <th class="text-end">Tercatat</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($serialStatus as $row)
                                                    <tr>
                                                        <td>{{ $row['level'] }}</td>
                                                        <td>{{ $row['unit_label'] }}</td>
                                                        <td><code>{{ $row['next_serial'] }}</code></td>
                                                        <td class="text-end">{{ number_format($row['allocated_count']) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @php
                                        $canResetBarcodeSerials = session('is_super_admin') || session('permissions.Product.is_update', false) == 1;
                                    @endphp
                                    @if ($canResetBarcodeSerials)
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnResetSerials">
                                            <i class="ti ti-refresh me-1"></i> Reset Nomor Barcode
                                        </button>
                                    @endif
                                    <small class="text-muted d-block mt-1">
                                        Reset menghapus riwayat penomoran di sistem. Label yang sudah tercetak fisik tidak berubah.
                                    </small>
                                </div>
                            @endif

                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-info" id="btnPreview">
                                    <i class="ti ti-eye me-1"></i> Preview
                                </button>
                                <button type="submit" class="btn btn-primary" id="btnPdf" disabled>
                                    <i class="ti ti-file-type-pdf me-1"></i> Save to PDF (A3)
                                </button>
                                <a href="{{ route('product.index.view') }}" class="btn btn-label-secondary">Kembali</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card d-none" id="previewCard">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="card-title mb-0">Preview Label</h5>
                            <small class="text-muted" id="previewMeta"></small>
                        </div>
                        <span class="badge bg-label-primary" id="previewRange"></span>
                    </div>
                    <div class="card-body">
                        <div class="preview-grid" id="previewGrid"></div>
                        <p class="text-muted small mb-0 mt-3 d-none" id="previewMore"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('page-css')
        <style>
            @include('admin.product.master._barcode-label-styles')
        </style>
    @endpush

    @push('page-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script>
            $(function () {
                var previewUrl = @json(route('product.print-barcode.preview', $product->id));
                var pdfUrl = @json(route('product.print-barcode.pdf', $product->id));
                var resetSerialsUrl = @json(route('product.print-barcode.reset-serials', $product->id));
                var distributorName = @json($distributorName);
                var productName = @json($product->name);
                var harnicaLogoUrl = @json(asset('assets/img/harnica/logo.png'));
                var unitChain = @json($unitChain);
                var units = @json($units);
                var defaultUnitId = @json($defaultUnitId);
                var hasUnitHierarchy = @json($hasUnitHierarchy);
                var singlePrintMaxLabels = @json($singlePrintMaxLabels ?? 500);
                var hierarchyPrintMaxLabels = @json($hierarchyPrintMaxLabels ?? 5000);
                var labelsPerParent = @json($labelsPerParent ?? 1);
                var maxHierarchyParentQty = @json($maxHierarchyParentQty ?? 500);

                $('.select2').select2({ width: '100%', allowClear: true });

                function isHierarchyMode() {
                    return hasUnitHierarchy && $('input[name="print_mode"]:checked').val() === 'hierarchy';
                }

                function getSelectedUnitId() {
                    if (isHierarchyMode()) {
                        return defaultUnitId;
                    }
                    return $('#unit_id').val();
                }

                function computeBreakdown(parentQty, parentUnitId) {
                    var startIndex = unitChain.findIndex(function (item) {
                        return item.unit_id === parentUnitId;
                    });
                    if (startIndex < 0) {
                        startIndex = 0;
                    }

                    var breakdown = [];
                    var qty = parentQty;

                    for (var i = startIndex; i < unitChain.length; i++) {
                        var chainItem = unitChain[i];
                        var unitMeta = units.find(function (u) { return u.id === chainItem.unit_id; }) || {};

                        breakdown.push({
                            level: chainItem.level,
                            label: chainItem.label,
                            qty: qty,
                            content_summary: unitMeta.content_summary || ''
                        });

                        if (i + 1 < unitChain.length && chainItem.factor_to_next) {
                            qty = Math.round(qty * chainItem.factor_to_next);
                        }
                    }

                    return breakdown;
                }

                function renderQtyBreakdown() {
                    if (!isHierarchyMode()) {
                        $('#qtyBreakdownCard').addClass('d-none');
                        return;
                    }

                    var parentQty = parseInt($('#quantity').val(), 10) || 1;
                    var breakdown = computeBreakdown(parentQty, defaultUnitId);
                    var total = breakdown.reduce(function (sum, row) { return sum + row.qty; }, 0);
                    var html = '';

                    breakdown.forEach(function (row) {
                        html += '<tr>' +
                            '<td>Level ' + row.level + '</td>' +
                            '<td>' + $('<div>').text(row.label).html() + '</td>' +
                            '<td class="text-end"><strong>' + row.qty + '</strong></td>' +
                            '<td class="small text-muted">' + $('<div>').text(row.content_summary || '-').html() + '</td>' +
                            '</tr>';
                    });

                    $('#qtyBreakdownBody').html(html);
                    $('#qtyBreakdownTotal').text(total);

                    var maxTotal = hierarchyPrintMaxLabels;
                    if (total > maxTotal) {
                        $('#qtyBreakdownTotal').addClass('text-danger');
                    } else {
                        $('#qtyBreakdownTotal').removeClass('text-danger');
                    }

                    $('#qtyBreakdownCard').removeClass('d-none');
                }

                function syncPrintModeUi() {
                    var hierarchy = isHierarchyMode();

                    if (hierarchy) {
                        $('#unit_id').val(defaultUnitId).trigger('change.select2');
                        $('#unit_id').prop('disabled', true);
                        $('#quantity').attr('max', maxHierarchyParentQty);
                        $('#quantityLabel').text('Jumlah Satuan Terbesar (Qty)');
                        $('#quantityHelp').text(
                            '1 satuan terbesar = ' + labelsPerParent + ' label total. ' +
                            'Maks. qty induk: ' + maxHierarchyParentQty + ' (total ' + hierarchyPrintMaxLabels + ' label).'
                        );
                    } else {
                        $('#unit_id').prop('disabled', false);
                        $('#quantity').attr('max', singlePrintMaxLabels);
                        $('#quantityLabel').text('Jumlah Label (Qty)');
                        $('#quantityHelp').text('Qty = jumlah label untuk satuan yang dipilih. Maksimal ' + singlePrintMaxLabels + ' label per cetak.');
                        $('#qtyBreakdownCard').addClass('d-none');
                    }

                    updateUnitHint();
                    renderQtyBreakdown();
                }

                function updateUnitHint() {
                    var $opt = $('#unit_id option:selected');
                    var parts = [];
                    var level = $opt.data('level');
                    var format = $opt.data('format');
                    var hint = $opt.data('hint');
                    var content = $opt.data('content');
                    var childHint = $opt.data('child-hint');

                    if (level) {
                        parts.push('Level ' + level);
                    }
                    if (format) {
                        parts.push('Contoh nomor: <code>' + format + '</code>');
                    }
                    if (hint) {
                        parts.push(hint);
                    }
                    if (content) {
                        parts.push('Isi label: <em>' + content + '</em>');
                    }
                    if (!isHierarchyMode() && childHint) {
                        parts.push('1 satuan ini ≈ ' + childHint + ' label satuan terkecil');
                    }

                    $('#unitHint').html(parts.join(' · '));
                }

                $('input[name="print_mode"]').on('change', function () {
                    syncPrintModeUi();
                    resetPreviewState();
                });

                syncPrintModeUi();
                $('#unit_id').on('change', function () {
                    if (!isHierarchyMode()) {
                        updateUnitHint();
                    }
                    resetPreviewState();
                });

                $('#quantity').on('change input', function () {
                    renderQtyBreakdown();
                    resetPreviewState();
                });

                $('#variant_id').on('change', resetPreviewState);

                function resetPreviewState() {
                    $('#batch_id').val('');
                    $('#btnPdf').prop('disabled', true);
                    $('#previewCard').addClass('d-none');
                    $('#previewGrid').empty();
                    $('#previewMeta, #previewRange, #previewMore').text('');
                }

                @include('admin.product.master._barcode-tree-renderer')

                function updateSerialStatusTable(rows) {
                    if (!rows || !rows.length) {
                        return;
                    }

                    var html = '';
                    rows.forEach(function (row) {
                        html += '<tr>' +
                            '<td>' + row.level + '</td>' +
                            '<td>' + $('<div>').text(row.unit_label).html() + '</td>' +
                            '<td><code>' + $('<div>').text(row.next_serial).html() + '</code></td>' +
                            '<td class="text-end">' + Number(row.allocated_count || 0).toLocaleString('id-ID') + '</td>' +
                            '</tr>';
                    });
                    $('#serialStatusTable tbody').html(html);
                }

                $('#btnResetSerials').on('click', function () {
                    if (!confirm('Reset nomor barcode produk ini? Riwayat penomoran di sistem akan dihapus dan cetak berikutnya mulai dari urutan 1 per level.')) {
                        return;
                    }

                    var $btn = $(this);
                    $btn.prop('disabled', true);

                    $.ajax({
                        url: resetSerialsUrl,
                        method: 'POST',
                        data: {
                            _token: $('input[name="_token"]').val(),
                            variant_id: $('#variant_id').val() || ''
                        },
                        success: function (res) {
                            alert(res.message || 'Reset berhasil.');
                            updateSerialStatusTable(res.serial_status || []);
                            resetPreviewState();
                        },
                        error: function (xhr) {
                            var message = (xhr.responseJSON && xhr.responseJSON.message)
                                ? xhr.responseJSON.message
                                : 'Gagal reset nomor barcode.';
                            alert(message);
                        },
                        complete: function () {
                            $btn.prop('disabled', false);
                        }
                    });
                });

                $('#btnPreview').on('click', function () {
                    var $btn = $(this);
                    var qty = parseInt($('#quantity').val(), 10);
                    var unitId = getSelectedUnitId();
                    var printMode = isHierarchyMode() ? 'hierarchy' : 'single';
                    var maxQty = printMode === 'hierarchy' ? maxHierarchyParentQty : singlePrintMaxLabels;

                    if (!unitId) {
                        alert('Silakan pilih satuan terlebih dahulu.');
                        return;
                    }

                    if (!qty || qty < 1 || qty > maxQty) {
                        alert('Qty harus antara 1 dan ' + maxQty + '.');
                        return;
                    }

                    if (printMode === 'hierarchy') {
                        var breakdown = computeBreakdown(qty, defaultUnitId);
                        var total = breakdown.reduce(function (sum, row) { return sum + row.qty; }, 0);
                        if (total > hierarchyPrintMaxLabels) {
                            alert('Total label (' + total + ') melebihi batas ' + hierarchyPrintMaxLabels + '. Maks. qty induk: ' + maxHierarchyParentQty + '.');
                            return;
                        }
                    }

                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Loading...');
                    resetPreviewState();

                    $.ajax({
                        url: previewUrl,
                        method: 'POST',
                        data: {
                            _token: $('input[name="_token"]').val(),
                            quantity: qty,
                            unit_id: unitId,
                            variant_id: $('#variant_id').val() || '',
                            print_mode: printMode
                        },
                        success: function (res) {
                            $('#batch_id').val(res.batch_id);
                            $('#btnPdf').prop('disabled', false);

                            if (res.print_mode === 'hierarchy') {
                                var breakdownArr = Object.values(res.breakdown || {});
                                var breakdownText = breakdownArr.map(function (row) {
                                    return row.label + ': ' + row.qty;
                                }).join(' · ');

                                $('#previewMeta').text(
                                    'Mode hierarki · Qty induk: ' + res.parent_quantity +
                                    ' | ' + breakdownText +
                                    ' | Menampilkan ' + res.displayed + ' dari ' + res.total + ' label'
                                );
                                $('#previewRange').text('Total ' + res.total + ' label');

                                renderHierarchyTreePreview(res.tree || []);

                                if (res.hidden && res.hidden > 0) {
                                    $('#previewMore').removeClass('d-none').text(
                                        '... dan ' + res.hidden + ' label lainnya akan disertakan di PDF (urutan Karton → Pack → Box sesuai nomor seri).'
                                    );
                                } else {
                                    $('#previewMore').addClass('d-none').text('');
                                }
                            } else {
                                $('#previewMeta').text(
                                    'Level ' + (res.unit_level || '-') + ' · ' + (res.unit_label || '-') +
                                    ' | Menampilkan ' + res.displayed + ' dari ' + res.total + ' label'
                                );
                                $('#previewRange').text((res.serial_from || '-') + ' — ' + (res.serial_to || '-'));

                                var html = '';
                                var distName = res.distributor_name || distributorName;
                                res.labels.forEach(function (label) {
                                    html += renderPreviewLabel(label, distName);
                                });
                                $('#previewGrid').html(html);
                            }

                            $('#previewCard').removeClass('d-none')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                        },
                        error: function (xhr) {
                            var msg = xhr.responseJSON?.message || 'Gagal memuat preview.';
                            alert(msg);
                        },
                        complete: function () {
                            $btn.prop('disabled', false).html('<i class="ti ti-eye me-1"></i> Preview');
                        }
                    });
                });

                $('#printForm').on('submit', function (e) {
                    e.preventDefault();

                    if (!$('#batch_id').val()) {
                        alert('Silakan preview terlebih dahulu.');
                        return;
                    }

                    var $btn = $('#btnPdf');
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Generating...');

                    var form = $('<form>', {
                        method: 'POST',
                        action: pdfUrl,
                        target: '_blank'
                    });

                    $('#printForm').find('input, select').each(function () {
                        if (this.name && !this.disabled) {
                            form.append($('<input>', { type: 'hidden', name: this.name, value: $(this).val() }));
                        }
                    });

                    if (isHierarchyMode()) {
                        form.append($('<input>', { type: 'hidden', name: 'print_mode', value: 'hierarchy' }));
                        form.append($('<input>', { type: 'hidden', name: 'unit_id', value: defaultUnitId }));
                    }

                    $('body').append(form);
                    form.trigger('submit');
                    form.remove();

                    setTimeout(function () {
                        $btn.prop('disabled', false).html('<i class="ti ti-file-type-pdf me-1"></i> Save to PDF (A3)');
                    }, 3000);
                });
            });
        </script>
    @endpush

</x-app-layout>
