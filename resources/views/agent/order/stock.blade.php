@extends('layouts.agent-order')

@section('title', 'Stock | ')

@section('shop_body_class')
    agent-stock-page
@endsection

@push('body-top')
    <div class="bg-shapes" aria-hidden="true">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
    </div>
@endpush

@push('styles')
    <style>
        .agent-stock-table td { vertical-align: middle; }
        .agent-stock-table .stock-col-qty { min-width: 160px; max-width: 280px; }
        .agent-stock-unit-detail .stock-unit-line { word-break: break-word; }
        .agent-stock-header-actions .btn-group .btn { font-size: 0.8125rem; }
        #stockBarcodeModal .btn-copy-barcode {
            width: 1.75rem;
            height: 1.75rem;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
@endpush

@section('content')
    @php
        $displayUnitMode = $displayUnitMode ?? request('display_unit', 'large');
        $unitToggleQuery = collect(request()->query())
            ->except('display_unit')
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();
        $largeUnitUrl = route('agent-order.stock', array_merge($unitToggleQuery, ['display_unit' => 'large']));
        $smallUnitUrl = route('agent-order.stock', array_merge($unitToggleQuery, ['display_unit' => 'small']));
    @endphp

    <header class="shop-page-header d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <a href="{{ route('agent-order.dashboard') }}" class="shop-back-link d-inline-flex align-items-center gap-1 small text-muted text-decoration-none mb-2">
                <i class="ti ti-arrow-left"></i> Beranda
            </a>
            <h1 class="shop-page-title mb-1">Stock</h1>
            <p class="text-muted small mb-0">
                {{ $warehouseName ?: 'Gudang belum diset' }}
            </p>
        </div>
        <div class="agent-stock-header-actions">
            <div class="btn-group" role="group" aria-label="Tampilan satuan">
                <a href="{{ $largeUnitUrl }}" class="btn btn-sm btn-{{ $displayUnitMode === 'large' ? 'primary' : 'outline-primary' }}">
                    Satuan Besar
                </a>
                <a href="{{ $smallUnitUrl }}" class="btn btn-sm btn-{{ $displayUnitMode === 'small' ? 'primary' : 'outline-primary' }}">
                    Satuan Kecil
                </a>
            </div>
        </div>
    </header>

    <div class="card border-0 shadow-sm shop-order-card mb-3">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('agent-order.stock') }}" class="row g-2 align-items-center">
                @if ($displayUnitMode !== 'large')
                    <input type="hidden" name="display_unit" value="{{ $displayUnitMode }}">
                @endif
                <div class="col-md-5">
                    <input type="search" name="q" class="form-control" value="{{ $search }}"
                           placeholder="Cari nama produk atau SKU…" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" id="stockBarcodeLookup" class="form-control"
                               placeholder="Scan / cek barcode…" autocomplete="off" inputmode="text">
                        <button type="button" class="btn btn-outline-primary" id="btnStockBarcodeLookup">
                            <i class="ti ti-barcode"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="ti ti-search me-1"></i>Cari</button>
                    @if ($search !== '')
                        <a href="{{ route('agent-order.stock', $displayUnitMode !== 'large' ? ['display_unit' => $displayUnitMode] : []) }}" class="btn btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm shop-order-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle agent-stock-table">
                <thead class="table-light">
                    <tr>
                        <th>Produk</th>
                        <th>Varian</th>
                        <th>SKU</th>
                        <th class="text-end stock-col-qty">Stok</th>
                        <th class="text-center" style="width: 4.5rem">Barcode</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $item)
                        @php
                            $displayQty = (float) ($item['quantity'] ?? 0);
                        @endphp
                        <tr>
                            <td>{{ $item['product_name'] ?? '-' }}</td>
                            <td>{{ $item['variant_name'] ?? '-' }}</td>
                            <td class="text-muted">{{ $item['sku'] ?? '-' }}</td>
                            <td class="text-end stock-col-qty">
                                <div class="fw-semibold">
                                    {{ format_number($displayQty, 2, true) }}
                                    <small class="text-muted">{{ $item['unit'] ?? '' }}</small>
                                </div>
                                @if (!empty($item['all_units']) && count($item['all_units']) > 1)
                                    <div class="agent-stock-unit-detail stock-unit-detail mt-1">
                                        <small class="text-muted d-block fw-semibold">Setara di semua satuan:</small>
                                        @foreach ($item['all_units'] as $unitStock)
                                            <small class="text-muted d-block stock-unit-line">
                                                {{ format_number((float) $unitStock['quantity'], 2, true) }} {{ $unitStock['unit'] }}
                                            </small>
                                        @endforeach
                                    </div>
                                @endif
                                @if (!empty($item['conversion_chain_hint']))
                                    <small class="text-muted d-block fst-italic mt-1">{{ $item['conversion_chain_hint'] }}</small>
                                @elseif (!empty($item['conversion_hint']))
                                    <small class="text-muted d-block fst-italic mt-1">{{ $item['conversion_hint'] }}</small>
                                @endif
                                <div class="mt-1">
                                    @if (!empty($item['out']))
                                        <span class="badge bg-label-danger">Habis</span>
                                    @elseif (!empty($item['low']))
                                        <span class="badge bg-label-warning">Rendah</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-center">
                                <button type="button"
                                    class="btn btn-sm btn-outline-primary btn-stock-barcode-detail"
                                    title="Detail barcode"
                                    data-product-id="{{ $item['product_id'] }}"
                                    data-variant-id="{{ $item['variant_id'] }}"
                                    data-title="{{ $item['product_name'] }} · {{ $item['variant_name'] ?: ($item['sku'] ?: '') }}">
                                    <i class="ti ti-barcode"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-empty-state icon="ti ti-building-warehouse" title="Belum ada stok di gudang Anda" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($stocks->hasPages())
            <div class="card-footer">{{ $stocks->links() }}</div>
        @endif
    </div>

    <div class="modal fade" id="stockBarcodeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockBarcodeModalTitle">Detail Barcode</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div id="stockBarcodeLoading" class="text-center text-muted py-4">Memuat…</div>
                    <div id="stockBarcodeContent" class="d-none">
                        <div class="row g-3 mb-3" id="stockBarcodeKpis"></div>
                        <div class="mb-3" id="stockBarcodeConversion"></div>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Satuan</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Di gudang</th>
                                        <th class="text-end">Terjual</th>
                                    </tr>
                                </thead>
                                <tbody id="stockBarcodeSummary"></tbody>
                            </table>
                        </div>
                        <div class="mb-3" id="stockBarcodeLevels"></div>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <h6 class="mb-0">Hierarki barcode</h6>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" id="stockBarcodeExpandAll">Buka semua</button>
                                <button type="button" class="btn btn-outline-secondary" id="stockBarcodeCollapseAll">Tutup</button>
                            </div>
                        </div>
                        <p class="small text-muted mb-2">Barcode yang diterima di gudang Anda. Klik untuk buka isi di dalamnya.</p>
                        <div id="stockBarcodeTree" class="barcode-tree border rounded p-2" style="max-height: 420px; overflow: auto;"></div>
                        <small class="text-muted d-block mt-2" id="stockBarcodeNote"></small>
                    </div>
                    <div id="stockBarcodeEmpty" class="text-center text-muted py-4 d-none">Belum ada barcode untuk item ini di gudang Anda.</div>
                    <div id="stockBarcodeError" class="alert alert-danger d-none mb-0"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            var barcodesUrl = @json($barcodesDetailUrl ?? route('agent-order.stock.barcodes'));
            var lookupUrl = @json($barcodeLookupUrl ?? route('agent-order.stock.barcode-lookup'));
            var barcodeModalEl = document.getElementById('stockBarcodeModal');
            var barcodeModal = barcodeModalEl ? new bootstrap.Modal(barcodeModalEl) : null;

            function esc(value) {
                return $('<div>').text(value == null ? '' : String(value)).html();
            }

            function copyBarcode(text, btn) {
                var done = function() {
                    if (!btn) return;
                    var old = btn.innerHTML;
                    btn.innerHTML = '<i class="ti ti-check"></i>';
                    setTimeout(function() { btn.innerHTML = old; }, 1200);
                };
                var fallback = function() {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function() {
                        fallback();
                        done();
                    });
                } else {
                    fallback();
                    done();
                }
            }

            function copyBtnHtml(serial) {
                return '<button type="button" class="btn btn-sm btn-icon btn-outline-secondary btn-copy-barcode" title="Salin kode" data-serial="' + esc(serial) + '">' +
                    '<i class="ti ti-copy"></i></button>';
            }

            function barcodeStatusBadge(status) {
                return status === 'dispatched'
                    ? '<span class="badge bg-label-warning">Terjual</span>'
                    : '<span class="badge bg-label-success">Di gudang</span>';
            }

            function renderBarcodeTreeNode(node, depth) {
                var hasChildren = node.children && node.children.length > 0;
                var pad = Math.min(depth, 6) * 14;
                var html = '<div class="barcode-tree-node py-1 d-flex flex-wrap align-items-center gap-1" style="padding-left:' + pad + 'px">';

                if (hasChildren) {
                    var childId = 'stock-bc-' + node.id;
                    html += '<button type="button" class="btn btn-sm btn-link text-body text-decoration-none p-0 barcode-tree-toggle" data-target="#' + childId + '" aria-expanded="false">' +
                        '<i class="ti ti-chevron-right barcode-tree-icon align-middle"></i>' +
                        '</button>';
                    html += '<span class="badge bg-label-secondary">L' + esc(node.level) + ' · ' + esc(node.unit_label) + '</span>';
                    html += '<code class="font-monospace">' + esc(node.serial) + '</code>';
                    html += copyBtnHtml(node.serial);
                    html += barcodeStatusBadge(node.status);
                    html += ' <small class="text-muted">(' + node.children.length + ')</small>';
                    html += '</div>';
                    html += '<div id="' + childId + '" class="barcode-tree-children d-none mt-1">';
                    node.children.forEach(function(child) {
                        html += renderBarcodeTreeNode(child, depth + 1);
                    });
                    html += '</div>';
                    return html;
                }

                html += '<span class="badge bg-label-secondary">L' + esc(node.level) + ' · ' + esc(node.unit_label) + '</span>';
                html += '<code class="font-monospace">' + esc(node.serial) + '</code>';
                html += copyBtnHtml(node.serial);
                html += barcodeStatusBadge(node.status);
                html += '</div>';
                return html;
            }

            function renderLevels(levels) {
                if (!levels || !levels.length) {
                    $('#stockBarcodeLevels').empty();
                    return;
                }

                var tabs = '';
                var panes = '';
                levels.forEach(function(level, idx) {
                    var tabId = 'stockBarcodeLevel' + level.unit_level;
                    var active = idx === 0 ? ' active' : '';
                    var show = idx === 0 ? ' show active' : '';
                    tabs += '<li class="nav-item" role="presentation">' +
                        '<button type="button" class="nav-link' + active + '" data-bs-toggle="tab" data-bs-target="#' + tabId + '" role="tab">' +
                        'L' + esc(level.unit_level) + ' · ' + esc(level.unit_label) +
                        ' <span class="badge bg-label-secondary">' + level.total + '</span>' +
                        '</button></li>';

                    var rows = '';
                    (level.serials || []).forEach(function(item) {
                        rows += '<tr>' +
                            '<td><code class="font-monospace">' + esc(item.serial) + '</code></td>' +
                            '<td>' + barcodeStatusBadge(item.status) + '</td>' +
                            '<td class="text-end">' + copyBtnHtml(item.serial) + '</td>' +
                            '</tr>';
                    });

                    panes += '<div class="tab-pane fade' + show + '" id="' + tabId + '" role="tabpanel">' +
                        '<div class="d-flex justify-content-end mb-2">' +
                        '<button type="button" class="btn btn-sm btn-outline-primary btn-copy-barcode-level" data-level="' + level.unit_level + '">' +
                        '<i class="ti ti-copy me-1"></i>Salin semua L' + esc(level.unit_level) +
                        '</button></div>' +
                        '<div class="table-responsive" style="max-height: 280px; overflow: auto;">' +
                        '<table class="table table-sm align-middle mb-0">' +
                        '<thead class="table-light"><tr><th>Kode barcode</th><th>Status</th><th class="text-end" style="width:3.5rem"></th></tr></thead>' +
                        '<tbody>' + (rows || '<tr><td colspan="3" class="text-muted text-center">Tidak ada barcode di level ini.</td></tr>') + '</tbody>' +
                        '</table></div></div>';
                });

                $('#stockBarcodeLevels').html(
                    '<ul class="nav nav-pills flex-wrap gap-1 mb-3" role="tablist">' + tabs + '</ul>' +
                    '<div class="tab-content">' + panes + '</div>'
                );
                $('#stockBarcodeLevels').data('levels', levels);
            }

            function setBarcodeTreeExpanded($children, expanded) {
                $children.toggleClass('d-none', !expanded);
                var $btn = $children.prev('.barcode-tree-node').find('.barcode-tree-toggle');
                $btn.attr('aria-expanded', expanded ? 'true' : 'false');
                $btn.find('.barcode-tree-icon')
                    .toggleClass('ti-chevron-right', !expanded)
                    .toggleClass('ti-chevron-down', expanded);
            }

            function openBarcodeDetail(productId, variantId, title) {
                if (!barcodeModal) return;
                $('#stockBarcodeModalTitle').text(title || 'Detail Barcode');
                $('#stockBarcodeLoading').removeClass('d-none');
                $('#stockBarcodeContent, #stockBarcodeEmpty, #stockBarcodeError').addClass('d-none');
                barcodeModal.show();

                $.get(barcodesUrl, { product_id: productId, variant_id: variantId || null })
                    .done(function(res) {
                        $('#stockBarcodeLoading').addClass('d-none');
                        if (!res.success) {
                            $('#stockBarcodeError').removeClass('d-none').text(res.message || 'Gagal memuat barcode.');
                            return;
                        }

                        var totals = res.totals || {};
                        if (!totals.total) {
                            $('#stockBarcodeEmpty').removeClass('d-none');
                            return;
                        }

                        $('#stockBarcodeKpis').html(
                            '<div class="col-md-4"><small class="text-muted d-block">Total</small><div class="fw-semibold fs-5">' + (totals.total || 0) + '</div></div>' +
                            '<div class="col-md-4"><small class="text-muted d-block">Di gudang</small><div class="fw-semibold fs-5 text-success">' + (totals.ready || 0) + '</div></div>' +
                            '<div class="col-md-4"><small class="text-muted d-block">Terjual</small><div class="fw-semibold fs-5 text-warning">' + (totals.dispatched || 0) + '</div></div>'
                        );

                        if (res.conversion_chain && res.conversion_chain.length) {
                            $('#stockBarcodeConversion').html(
                                '<small class="text-muted d-block">Aturan konversi</small><div class="fw-medium">' +
                                res.conversion_chain.map(esc).join(' · ') + '</div>'
                            );
                        } else {
                            $('#stockBarcodeConversion').empty();
                        }

                        var summaryHtml = '';
                        (res.summary || []).forEach(function(row) {
                            summaryHtml += '<tr>' +
                                '<td>L' + esc(row.unit_level) + ' · ' + esc(row.unit_label) + '</td>' +
                                '<td class="text-end">' + row.total + '</td>' +
                                '<td class="text-end text-success">' + row.ready + '</td>' +
                                '<td class="text-end text-warning">' + row.dispatched + '</td>' +
                                '</tr>';
                        });
                        $('#stockBarcodeSummary').html(summaryHtml || '<tr><td colspan="4" class="text-muted text-center">—</td></tr>');
                        renderLevels(res.levels || []);

                        var tree = res.tree || [];
                        var treeHtml = '';
                        tree.forEach(function(node) {
                            treeHtml += renderBarcodeTreeNode(node, 0);
                        });
                        $('#stockBarcodeTree').html(
                            treeHtml || '<div class="text-muted text-center py-3">' +
                                ((res.totals && res.totals.dispatched)
                                    ? 'Semua barcode sudah keluar, tidak bisa dipakai lagi.'
                                    : 'Tidak ada hierarki Karton/Pack/Box.') +
                            '</div>'
                        );
                        $('#stockBarcodeNote').text(
                            tree.length
                                ? ('Hierarki L1–L3 · ' + tree.length + ' group level atas. Klik panah untuk membuka isi.')
                                : ''
                        );
                        $('#stockBarcodeContent').removeClass('d-none');
                    })
                    .fail(function(xhr) {
                        $('#stockBarcodeLoading').addClass('d-none');
                        $('#stockBarcodeError').removeClass('d-none').text(
                            (xhr.responseJSON && xhr.responseJSON.message) || 'Gagal memuat barcode.'
                        );
                    });
            }

            function lookupBarcode() {
                var serial = $.trim($('#stockBarcodeLookup').val() || '');
                if (!serial) {
                    $('#stockBarcodeLookup').focus();
                    return;
                }

                $.get(lookupUrl, { serial: serial })
                    .done(function(res) {
                        if (!res.success) {
                            alert(res.message || 'Barcode tidak ditemukan.');
                            return;
                        }
                        openBarcodeDetail(res.product_id, res.variant_id, res.title);
                    })
                    .fail(function(xhr) {
                        alert((xhr.responseJSON && xhr.responseJSON.message) || 'Barcode tidak ada di gudang Anda.');
                    });
            }

            $(document).on('click', '#stockBarcodeTree .barcode-tree-toggle', function(e) {
                e.preventDefault();
                var $children = $($(this).data('target'));
                setBarcodeTreeExpanded($children, $children.hasClass('d-none'));
            });

            $('#stockBarcodeExpandAll').on('click', function() {
                $('#stockBarcodeTree .barcode-tree-children').each(function() {
                    setBarcodeTreeExpanded($(this), true);
                });
            });

            $('#stockBarcodeCollapseAll').on('click', function() {
                $('#stockBarcodeTree .barcode-tree-children').each(function() {
                    setBarcodeTreeExpanded($(this), false);
                });
            });

            $(document).on('click', '#stockBarcodeModal .btn-copy-barcode', function(e) {
                e.preventDefault();
                e.stopPropagation();
                copyBarcode($(this).data('serial') || '', this);
            });

            $(document).on('click', '#stockBarcodeModal .btn-copy-barcode-level', function(e) {
                e.preventDefault();
                var levelNo = parseInt($(this).data('level'), 10);
                var levels = $('#stockBarcodeLevels').data('levels') || [];
                var match = levels.find(function(row) { return parseInt(row.unit_level, 10) === levelNo; });
                var codes = ((match && match.serials) || [])
                    .filter(function(item) { return item.status !== 'dispatched'; })
                    .map(function(item) { return item.serial; })
                    .filter(Boolean);
                if (!codes.length) return;
                copyBarcode(codes.join('\n'), this);
            });

            $(document).on('click', '.btn-stock-barcode-detail', function(e) {
                e.preventDefault();
                openBarcodeDetail($(this).data('product-id'), $(this).data('variant-id'), $(this).data('title'));
            });

            $('#btnStockBarcodeLookup').on('click', function(e) {
                e.preventDefault();
                lookupBarcode();
            });

            $('#stockBarcodeLookup').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    lookupBarcode();
                }
            });
        });
    </script>
@endpush
