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
                                            <option value="{{ $variant['id'] }}">{{ $variant['label'] }}</option>
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
                                    value="{{ old('quantity', 1) }}"
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
            .preview-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 1.5mm 2mm;
            }

            .preview-group {
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 16px;
                margin-bottom: 16px;
                background: #f8f9fa;
            }

            .preview-group__header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 8px;
                margin-bottom: 12px;
                padding-bottom: 12px;
                border-bottom: 2px solid #dee2e6;
            }

            .preview-group__title {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 16px;
            }

            .preview-group__badge {
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
            }

            .preview-group__info {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 4px;
                font-size: 13px;
            }

            .preview-group__qty {
                font-weight: 600;
                color: #0d6efd;
            }

            .preview-group__serial {
                font-family: ui-monospace, monospace;
                font-size: 12px;
                color: #6c757d;
            }

            .preview-group__labels {
                background: #fff;
                padding: 12px;
                border-radius: 6px;
            }

            .preview-tree {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .preview-tree__node {
                position: relative;
            }

            .preview-tree__node--level-1,
            .preview-tree__node--level-2-group,
            .preview-tree__node--level-3 {
                border-radius: 8px;
                padding: 10px;
            }

            .preview-tree__node--level-1 {
                border: 2px dashed #0d6efd;
                background: #f0f6ff;
                width: fit-content;
                max-width: 100%;
            }

            .preview-tree__node--level-2-group {
                border: 1.5px dashed #6ea8fe;
                background: #f0f6ff;
            }

            .preview-tree__node--level-3 {
                border: 1.5px dashed #28a745;
                background: #f8fff9;
            }

            .preview-tree__cut-line {
                font-size: 11px;
                color: #6c757d;
                text-align: center;
                border-bottom: 1px dashed #ccc;
                margin: -10px -10px 8px;
                padding: 5px 8px;
                background: #e9ecef;
                border-radius: 8px 8px 0 0;
                line-height: 1.2;
            }

            .preview-tree__box-grid {
                display: grid;
                grid-template-columns: repeat(5, 50mm);
                gap: 1.5mm 2mm;
                justify-content: start;
            }

            .preview-tree__box-groups-page .preview-tree__node--level-3 {
                margin-bottom: 10px;
            }

            .preview-tree__box-groups-page .preview-tree__node--level-3:last-child {
                margin-bottom: 0;
            }

            .preview-tree__page {
                margin-bottom: 20px;
            }

            .preview-tree__page + .preview-tree__page {
                border-top: 2px dashed #adb5bd;
                padding-top: 16px;
                margin-top: 8px;
            }

            .preview-tree__page-label {
                font-size: 10px;
                font-weight: 700;
                color: #6c757d;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                margin-bottom: 10px;
            }

            .preview-tree__page--level-1-2 {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .preview-tree__pack-grid {
                display: grid;
                grid-template-columns: repeat(6, 40mm);
                gap: 1.5mm;
                justify-content: start;
            }

            .preview-tree__pack-grid-item-title {
                font-size: 10px;
                font-weight: 700;
                text-align: center;
                color: #444;
                margin-bottom: 3px;
                line-height: 1;
            }

            .preview-tree__labels {
                display: block;
            }

            .preview-tree__more {
                margin: 6px 0 0;
                font-size: 12px;
                color: #6c757d;
                font-style: italic;
            }

            .label-item {
                border: 1px solid #999;
                overflow: hidden;
                background: #fff;
            }

            .label-item--box {
                width: 50mm;
                height: 12mm;
                display: flex;
            }

            .label-item--box .label-item__qr {
                width: 12mm;
                height: 12mm;
                flex-shrink: 0;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .label-item--box .label-item__qr img {
                width: 10mm;
                height: 10mm;
                display: block;
            }

            .label-item--box .label-item__content {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: flex-end;
                padding: 0 1.5mm 1mm 1mm;
                min-width: 0;
            }

            .label-item__distributor-name {
                font-size: 6.5px;
                font-weight: 700;
                color: #000;
                line-height: 1.2;
                text-transform: uppercase;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .label-item__serial {
                font-size: 6.5px;
                font-weight: 700;
                color: #000;
                font-family: ui-monospace, monospace;
                letter-spacing: 0.02em;
                line-height: 1.2;
                white-space: nowrap;
                margin-bottom: 0.4mm;
            }

            .label-item--karton {
                width: 100mm;
                height: 80mm;
                padding: 1mm;
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .label-item--karton .label-item__top {
                width: 100%;
                flex-shrink: 0;
            }

            .label-item--karton .label-item__bottom {
                width: 100%;
                margin-top: auto;
                flex-shrink: 0;
                text-align: center;
            }

            .label-item--pack {
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                align-items: center;
                text-align: center;
                width: 40mm;
                height: 30mm;
                padding: 9% 1mm 10%;
                box-sizing: border-box;
            }

            .label-item--pack .label-item__top {
                width: 100%;
                flex-shrink: 0;
                line-height: 1.15;
            }

            .label-item--pack .label-item__bottom {
                text-align: center;
                width: 100%;
                flex-shrink: 0;
                line-height: 1.15;
                padding-top: 0;
            }

            .label-item--karton .label-item__logo {
                line-height: 0;
                margin-bottom: 0.3mm;
            }

            .label-item--karton .label-item__qr {
                width: auto;
                height: auto;
                display: block;
                margin: 0 auto;
            }

            .label-item--karton .label-item__logo img {
                max-width: 96mm;
                max-height: 15mm;
                object-fit: contain;
            }

            .label-item--karton .label-item__product {
                font-size: 17px;
                font-weight: 700;
                text-transform: uppercase;
                line-height: 1.15;
                margin-top: 0.4mm;
            }

            .label-item--karton .label-item__unit-qty {
                font-size: 11px;
                font-weight: 700;
                line-height: 1.15;
                margin-top: 0.4mm;
            }

            .label-item--karton .label-item__summary {
                font-size: 9px;
                line-height: 1.15;
                margin-top: 0.4mm;
            }

            .label-item--karton .label-item__qr img {
                display: block;
                margin: 0 auto;
                width: 35mm;
                height: 35mm;
            }

            .label-item--karton .label-item__serial {
                font-size: 10px;
                font-weight: 700;
                font-family: ui-monospace, monospace;
                line-height: 1.15;
                margin-top: 0.4mm;
            }

            .label-item--pack .label-item__qr {
                width: auto;
                height: auto;
                display: block;
                margin: 0 auto;
                line-height: 0;
            }

            .label-item--pack .label-item__product {
                font-size: 9px;
                font-weight: 700;
                text-transform: uppercase;
                line-height: 1.15;
                margin-top: 0;
            }

            .label-item--pack .label-item__summary {
                font-size: 7px;
                line-height: 1.15;
                margin-top: 0.3mm;
                margin-bottom: 0;
            }

            .label-item--pack .label-item__qr img {
                width: 14mm;
                height: 14mm;
            }

            .label-item--pack .label-item__serial {
                font-size: 7px;
                font-family: ui-monospace, monospace;
                line-height: 1.1;
                margin-top: 0.4mm;
            }
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

                function formatBoxBrandLabel(distName) {
                    var name = (distName || '').trim();
                    if (name.toUpperCase().indexOf('HARNICA ') === 0) {
                        return name;
                    }
                    return 'HARNICA ' + name;
                }

                function renderPreviewLabel(label, distName) {
                    var type = label.label_type || 'box';
                    var distHtml = $('<div>').text(distName).html();
                    var productHtml = $('<div>').text(label.product_name || productName).html();
                    var summaryHtml = $('<div>').text(label.content_summary || '').html();

                    if (type === 'karton') {
                        return '<div class="label-item label-item--karton">' +
                            '<div class="label-item__top">' +
                            '<div class="label-item__logo"><img src="' + harnicaLogoUrl + '" alt="Harnica"></div>' +
                            '<div class="label-item__product">' + productHtml + '</div>' +
                            '<div class="label-item__unit-qty">1 ' + label.unit_label + '</div>' +
                            (label.content_summary ? '<div class="label-item__summary">' + summaryHtml + '</div>' : '') +
                            '</div>' +
                            '<div class="label-item__bottom">' +
                            '<div class="label-item__qr"><img src="' + label.qr_data_uri + '" alt="Barcode"></div>' +
                            '<div class="label-item__serial">' + label.serial + '</div>' +
                            '</div></div>';
                    }

                    if (type === 'pack') {
                        return '<div class="label-item label-item--pack">' +
                            '<div class="label-item__top">' +
                            '<div class="label-item__product">' + productHtml + '</div>' +
                            (label.content_summary ? '<div class="label-item__summary">' + summaryHtml + '</div>' : '') +
                            '</div>' +
                            '<div class="label-item__bottom">' +
                            '<div class="label-item__qr"><img src="' + label.qr_data_uri + '" alt="Barcode"></div>' +
                            '<div class="label-item__serial">' + label.serial + '</div>' +
                            '</div></div>';
                    }

                    var boxBrandHtml = $('<div>').text(formatBoxBrandLabel(distName)).html();

                    return '<div class="label-item label-item--box">' +
                        '<div class="label-item__qr"><img src="' + label.qr_data_uri + '" alt="QR"></div>' +
                        '<div class="label-item__content">' +
                        '<div class="label-item__serial">' + label.serial + '</div>' +
                        '<div class="label-item__distributor-name">' + boxBrandHtml + '</div>' +
                        '</div></div>';
                }

                function renderHierarchyTreePreview(tree) {
                    function boxSerialRange(packNode) {
                        var serials = (packNode.children || [])
                            .map(function (child) {
                                return child.serial || (child.label && child.label.serial) || null;
                            })
                            .filter(Boolean);
                        if (!serials.length) {
                            return '';
                        }
                        if (serials.length === 1) {
                            return serials[0];
                        }
                        return serials[0] + '-' + serials[serials.length - 1];
                    }

                    function renderPackGroupSection(kartonNode) {
                        var packs = kartonNode.children || [];
                        if (!packs.length) {
                            return '';
                        }

                        var unitLabel = $('<div>').text(packs[0].unit_label || 'Pack').html();
                        var html = '<div class="preview-tree__node preview-tree__node--level-2-group">';
                        html += '<div class="preview-tree__cut-line">&#9986; POTONG &mdash; ' + unitLabel.toUpperCase() + ' : 1-' + packs.length + '</div>';
                        html += '<div class="preview-tree__pack-grid">';

                        packs.forEach(function (packNode) {
                            if (packNode.label) {
                                html += '<div class="preview-tree__pack-grid-item">';
                                html += '<div class="preview-tree__pack-grid-item-title">' + packNode.ordinal + ' ' + unitLabel.toUpperCase() + '</div>';
                                html += renderPreviewLabel(packNode.label, distributorName);
                                html += '</div>';
                            }
                        });

                        html += '</div></div>';
                        return html;
                    }

                    function renderLevel1And2Page(kartonNode) {
                        var unitLabel = $('<div>').text(kartonNode.unit_label || 'Karton').html();
                        var html = '<div class="preview-tree__page preview-tree__page--level-1-2">';
                        html += '<div class="preview-tree__page-label">Halaman Level 1 &amp; 2 — Karton &amp; Pack</div>';

                        html += '<div class="preview-tree__node preview-tree__node--level-1">';
                        html += '<div class="preview-tree__cut-line">&#9986; POTONG &mdash; ' + kartonNode.ordinal + ' ' + unitLabel.toUpperCase() + '</div>';

                        if (kartonNode.label) {
                            html += '<div class="preview-tree__labels">';
                            html += renderPreviewLabel(kartonNode.label, distributorName);
                            html += '</div>';
                        }

                        if (kartonNode.hidden_children && kartonNode.hidden_children > 0) {
                            html += '<p class="preview-tree__more">... dan ' + kartonNode.hidden_children + ' label lainnya akan disertakan di PDF.</p>';
                        }

                        html += '</div>';

                        html += renderPackGroupSection(kartonNode);
                        html += '</div>';
                        return html;
                    }

                    function renderBoxGroupSection(packNode) {
                        var unitLabel = $('<div>').text(packNode.unit_label || 'Pack').html();
                        var boxLabels = (packNode.children || [])
                            .map(function (child) { return child.label; })
                            .filter(Boolean);

                        if (!boxLabels.length) {
                            return '';
                        }

                        var rangeLabel = boxSerialRange(packNode);
                        var html = '<div class="preview-tree__node preview-tree__node--level-3">';
                        html += '<div class="preview-tree__cut-line">&#9986; POTONG &mdash; ' + packNode.ordinal + ' ' + unitLabel.toUpperCase();
                        if (rangeLabel) {
                            html += ' : ' + rangeLabel;
                        }
                        html += '</div>';
                        html += '<div class="preview-tree__box-grid">';
                        boxLabels.forEach(function (label) {
                            html += renderPreviewLabel(label, distributorName);
                        });
                        html += '</div>';

                        if (packNode.hidden_children && packNode.hidden_children > 0) {
                            html += '<p class="preview-tree__more">... dan ' + packNode.hidden_children + ' box lainnya akan disertakan di PDF.</p>';
                        }

                        html += '</div>';
                        return html;
                    }

                    function renderLevel3Page(kartonNode) {
                        var packs = kartonNode.children || [];
                        var sections = packs.map(renderBoxGroupSection).filter(Boolean);

                        if (!sections.length) {
                            return '';
                        }

                        var html = '<div class="preview-tree__page preview-tree__page--level-3">';
                        html += '<div class="preview-tree__page-label">Halaman Level 3 — Box</div>';
                        html += '<div class="preview-tree__box-groups-page">';
                        html += sections.join('');
                        html += '</div></div>';
                        return html;
                    }

                    function renderKartonNode(kartonNode) {
                        return renderLevel1And2Page(kartonNode) + renderLevel3Page(kartonNode);
                    }

                    var html = '<div class="preview-tree">';
                    tree.forEach(function (rootNode) {
                        html += renderKartonNode(rootNode);
                    });
                    html += '</div>';

                    $('#previewGrid').html(html);
                }

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
