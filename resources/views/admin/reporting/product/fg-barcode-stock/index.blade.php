<x-app-layout>
    @section('title', 'FG Barcode & Stock | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            title="FG Barcode & Stock"
            subtitle="Bandingkan serial barcode ready vs stok gudang FG (stok dikonversi ke unit serial yang sama). Serial tanpa stok/variant ditandai orphan."
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Reporting'],
                ['label' => 'Inventory & Warehouse'],
                ['label' => 'FG Barcode & Stock', 'active' => true],
            ]"
        />

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('reporting.fg-barcode-stock.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label">Gudang FG</label>
                            <select name="warehouse_id" class="form-select select2">
                                @foreach($options['warehouses'] as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected($filters['warehouse_id'] === $warehouse->id)>
                                        {{ $warehouse->code }} · {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label">Product</label>
                            <select name="product_id" class="form-select select2">
                                <option value="">Semua Product</option>
                                @foreach($options['products'] as $product)
                                    <option value="{{ $product->id }}" @selected($filters['product_id'] === $product->id)>
                                        {{ $product->code }} · {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <label class="form-label">Variant</label>
                            <select name="variant_id" class="form-select select2">
                                <option value="">Semua Variant</option>
                                @foreach($options['variants'] as $variant)
                                    <option value="{{ $variant->id }}" @selected($filters['variant_id'] === $variant->id)>
                                        {{ $variant->sku }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Unit</label>
                            <select name="unit_id" class="form-select select2">
                                <option value="">Semua Unit</option>
                                @foreach($options['units'] as $unit)
                                    <option value="{{ $unit->id }}" @selected($filters['unit_id'] === $unit->id)>
                                        {{ $unit->symbol ?: $unit->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Status</label>
                            <select name="mismatch_only" class="form-select">
                                <option value="0" @selected(! $filters['mismatch_only'])>Semua</option>
                                <option value="1" @selected($filters['mismatch_only'])>Hanya mismatch</option>
                            </select>
                        </div>
                        <div class="col-xl-1 col-md-3">
                            <label class="form-label">Baris</label>
                            <select name="per_page" class="form-select">
                                @foreach([10, 20, 50, 100] as $size)
                                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-12 d-flex flex-wrap justify-content-end gap-2">
                            <a
                                href="{{ route('reporting.fg-barcode-stock.export', array_merge(request()->except('page'), ['export' => 'summary'])) }}"
                                class="btn btn-label-success"
                            >
                                <i class="ti ti-file-spreadsheet me-1"></i>Export Ringkasan
                            </a>
                            <a
                                href="{{ route('reporting.fg-barcode-stock.export', array_merge(request()->except('page'), ['export' => 'serials'])) }}"
                                class="btn btn-label-primary"
                            >
                                <i class="ti ti-barcode me-1"></i>Export Serial Ready
                            </a>
                            <a href="{{ route('reporting.fg-barcode-stock.index') }}" class="btn btn-label-secondary">
                                Reset
                            </a>
                            <button class="btn btn-primary" type="submit">
                                <i class="ti ti-search me-1"></i>Terapkan Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Baris Product" :value="format_number($kpis['rows'], 0, true)" icon="ti ti-packages" iconColor="primary" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Stok FG (asli)" :value="format_number($kpis['stock_qty'], 0, true)" icon="ti ti-building-warehouse" iconColor="info" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Serial Ready" :value="format_number($kpis['serial_ready'], 0, true)" icon="ti ti-barcode" iconColor="success" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Mismatch" :value="format_number($kpis['mismatch_rows'], 0, true)" icon="ti ti-alert-triangle" iconColor="warning" />
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Variant</th>
                            <th>Unit</th>
                            <th>Warehouse</th>
                            <th class="text-end">Stok equiv.</th>
                            <th class="text-end">Serial Ready</th>
                            <th class="text-end">Selisih</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $statusLabel = match ($row->status) {
                                    'surplus' => 'Serial surplus',
                                    'shortage' => 'Serial shortage',
                                    'orphan' => 'Orphan (tanpa stok/variant)',
                                    default => 'OK',
                                };
                                $statusClass = match ($row->status) {
                                    'surplus' => 'bg-label-warning',
                                    'shortage' => 'bg-label-danger',
                                    'orphan' => 'bg-label-secondary',
                                    default => 'bg-label-success',
                                };
                            @endphp
                            <tr class="{{ $row->status !== 'ok' ? ($row->status === 'orphan' ? 'table-secondary' : 'table-warning') : '' }}">
                                <td>
                                    <div class="fw-semibold">{{ $row->product_name }}</div>
                                    <div class="text-muted small">{{ $row->product_code }}</div>
                                </td>
                                <td>{{ $row->variant_sku ?: '—' }}</td>
                                <td>{{ $row->unit_symbol ?: $row->unit_name }}</td>
                                <td>
                                    <div>{{ $row->warehouse_code }}</div>
                                    <div class="text-muted small">{{ $row->warehouse_name }}</div>
                                </td>
                                <td class="text-end">{{ format_number($row->stock_qty, 0, true) }}</td>
                                <td class="text-end">{{ format_number($row->serial_ready, 0, true) }}</td>
                                <td class="text-end fw-semibold">{{ format_number($row->variance, 0, true) }}</td>
                                <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-label-primary btn-view-serials"
                                        data-product-id="{{ $row->product_id }}"
                                        data-variant-id="{{ $row->product_variant_id }}"
                                        data-null-variant="{{ $row->product_variant_id ? 0 : 1 }}"
                                        data-unit-id="{{ $row->unit_id }}"
                                        data-title="{{ $row->product_name }} · {{ $row->variant_sku ?: 'No variant' }} · {{ $row->unit_symbol ?: $row->unit_name }}"
                                    >
                                        Serials
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    Tidak ada data stok FG / serial ready untuk filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($rows->hasPages())
                <div class="card-footer">
                    {{ $rows->links() }}
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="fgSerialModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fgSerialModalTitle">Serial Ready</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="fgSerialModalLoading" class="text-muted py-4 text-center">Memuat…</div>
                    <div id="fgSerialModalEmpty" class="text-muted py-4 text-center d-none">Tidak ada serial ready.</div>
                    <div class="table-responsive d-none" id="fgSerialModalTableWrap">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Serial</th>
                                    <th>Variant</th>
                                    <th>Unit</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody id="fgSerialModalBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(function () {
                $('.select2').select2({ width: '100%', dropdownParent: $('body') });

                var serialUrl = @json(route('reporting.fg-barcode-stock.serials'));
                var modalEl = document.getElementById('fgSerialModal');
                var modal = modalEl ? new bootstrap.Modal(modalEl) : null;

                $(document).on('click', '.btn-view-serials', function () {
                    var $btn = $(this);
                    $('#fgSerialModalTitle').text('Serial Ready — ' + ($btn.data('title') || ''));
                    $('#fgSerialModalLoading').removeClass('d-none');
                    $('#fgSerialModalEmpty').addClass('d-none');
                    $('#fgSerialModalTableWrap').addClass('d-none');
                    $('#fgSerialModalBody').empty();
                    if (modal) modal.show();

                    $.getJSON(serialUrl, {
                        warehouse_id: @json($filters['warehouse_id']),
                        product_id: $btn.data('product-id'),
                        variant_id: $btn.data('variant-id') || '',
                        null_variant: $btn.data('null-variant') ? 1 : 0,
                        unit_id: $btn.data('unit-id'),
                        per_page: 100
                    }).done(function (res) {
                        $('#fgSerialModalLoading').addClass('d-none');
                        var rows = (res.data && res.data.serials) || [];
                        if (!rows.length) {
                            $('#fgSerialModalEmpty').removeClass('d-none');
                            return;
                        }
                        var html = '';
                        rows.forEach(function (row) {
                            html += '<tr>';
                            html += '<td class="font-monospace">' + (row.serial_number || '') + '</td>';
                            html += '<td>' + (row.variant_sku || '—') + '</td>';
                            html += '<td>' + (row.unit_symbol || row.unit_name || '') + '</td>';
                            html += '<td>' + (row.created_at ? String(row.created_at).replace('T', ' ').substring(0, 16) : '') + '</td>';
                            html += '</tr>';
                        });
                        $('#fgSerialModalBody').html(html);
                        $('#fgSerialModalTableWrap').removeClass('d-none');
                    }).fail(function () {
                        $('#fgSerialModalLoading').addClass('d-none');
                        $('#fgSerialModalEmpty').removeClass('d-none').text('Gagal memuat serial.');
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
