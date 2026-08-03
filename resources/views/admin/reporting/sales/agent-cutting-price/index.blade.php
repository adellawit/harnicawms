<x-app-layout>
    @section('title', 'Agent Cutting Price | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            title="Agent Cutting Price"
            subtitle="Deteksi penjualan Agent/Reseller di bawah MAP (Minimum Advertised) dari konfigurasi Cutting Price — tidak memakai price list."
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Reporting'],
                ['label' => 'Sales & Transaction'],
                ['label' => 'Agent Cutting Price', 'active' => true],
            ]"
        />

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('reporting.agent-cutting-price.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Dari Tanggal</label>
                            <input type="text" name="date_from" class="form-control flatpickr-date" placeholder="DD/MM/YYYY"
                                   value="{{ \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') }}">
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Sampai Tanggal</label>
                            <input type="text" name="date_to" class="form-control flatpickr-date" placeholder="DD/MM/YYYY"
                                   value="{{ \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') }}">
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label">Agent</label>
                            <select name="agent_id" class="form-select select2">
                                <option value="">Semua Agent</option>
                                @foreach($options['agents'] as $agent)
                                    <option value="{{ $agent->id }}" @selected($filters['agent_id'] === $agent->id)>
                                        {{ $agent->code }} · {{ $agent->name }}
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
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Min Gap %</label>
                            <input type="number" step="0.01" min="0" max="100" name="min_gap_percent" class="form-control"
                                   value="{{ $filters['min_gap_percent'] ?: '' }}" placeholder="0">
                        </div>
                        <div class="col-xl-1 col-md-3">
                            <label class="form-label">Baris</label>
                            <select name="per_page" class="form-select">
                                @foreach([10, 20, 50, 100] as $size)
                                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-filter me-1"></i> Terapkan
                            </button>
                            <a
                                href="{{ route('reporting.agent-cutting-price.export', array_merge(request()->except('page'), ['export' => 'summary'])) }}"
                                class="btn btn-label-success"
                            >
                                <i class="ti ti-file-spreadsheet me-1"></i> Export Summary
                            </a>
                            <a
                                href="{{ route('reporting.agent-cutting-price.export', array_merge(request()->except('page'), ['export' => 'detail'])) }}"
                                class="btn btn-label-success"
                            >
                                <i class="ti ti-file-spreadsheet me-1"></i> Export Detail
                            </a>
                            <a href="{{ route('reporting.agent-cutting-price.index') }}" class="btn btn-label-secondary">
                                Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <x-dashboard.kpi-card title="Total Agent" :value="format_number($kpis['total_agents'], 0, true)" icon="ti ti-users" iconColor="primary" />
            </div>
            <div class="col-md-3">
                <x-dashboard.kpi-card title="Agent Melanggar" :value="format_number($kpis['agents_violating'], 0, true)" icon="ti ti-user-exclamation" iconColor="warning" />
            </div>
            <div class="col-md-3">
                <x-dashboard.kpi-card title="Transaksi Melanggar" :value="format_number($kpis['transactions_violating'], 0, true)" icon="ti ti-receipt-off" iconColor="danger" />
            </div>
            <div class="col-md-3">
                <x-dashboard.kpi-card title="Kerugian Margin" :value="'Rp '.format_number($kpis['margin_loss'], 0, true)" icon="ti ti-currency-dollar" iconColor="info" />
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body py-3">
                <div class="fw-semibold mb-2">Kriteria Kepatuhan</div>
                <div class="d-flex flex-wrap gap-3 small">
                    <span><span class="badge bg-label-success me-1">95–100%</span> Sangat Patuh</span>
                    <span><span class="badge bg-label-warning me-1">90–95%</span> Perlu Perhatian</span>
                    <span><span class="badge bg-label-danger me-1">&lt;90%</span> Sering Cutting Price</span>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Agent</th>
                            <th class="text-end">Kepatuhan</th>
                            <th>Status</th>
                            <th class="text-end">Trx Melanggar</th>
                            <th class="text-end">Item Cutting</th>
                            <th class="text-end">Kerugian Margin</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $compliance = (float) ($row->compliance_percent ?? 0);
                                if ($compliance >= 95) {
                                    $statusLabel = 'Sangat Patuh';
                                    $statusClass = 'bg-label-success';
                                } elseif ($compliance >= 90) {
                                    $statusLabel = 'Perlu Perhatian';
                                    $statusClass = 'bg-label-warning';
                                } else {
                                    $statusLabel = 'Sering Cutting Price';
                                    $statusClass = 'bg-label-danger';
                                }
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $row->agent_name }}</div>
                                    <div class="text-muted small">{{ $row->agent_code }}</div>
                                </td>
                                <td class="text-end fw-semibold">{{ format_number($compliance, 2, true) }}%</td>
                                <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                <td class="text-end">{{ format_number($row->transactions_violating ?? 0, 0, true) }} / {{ format_number($row->transaction_count, 0, true) }}</td>
                                <td class="text-end">{{ format_number($row->cutting_items, 0, true) }} / {{ format_number($row->total_items ?? 0, 0, true) }}</td>
                                <td class="text-end fw-semibold {{ (float) $row->total_gap_amount > 0 ? 'text-danger' : '' }}">
                                    Rp {{ format_number($row->total_gap_amount, 0, true) }}
                                </td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-label-primary btn-view-details"
                                        data-agent-id="{{ $row->agent_id }}"
                                        data-title="{{ $row->agent_code }} · {{ $row->agent_name }}"
                                        @disabled((int) ($row->cutting_items ?? 0) === 0)
                                    >
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    Tidak ada data penjualan Agent/Reseller untuk filter ini.
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

    <div class="modal fade" id="cuttingDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cuttingDetailTitle">Detail Cutting Price</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Trx</th>
                                    <th>Penjual</th>
                                    <th>Produk</th>
                                    <th>Unit</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Net</th>
                                    <th class="text-end">MAP Floor</th>
                                    <th class="text-end">Selisih</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody id="cuttingDetailBody">
                                <tr><td colspan="11" class="text-center text-muted py-4">Memuat...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-muted" id="cuttingDetailMeta"></small>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-label-secondary" id="cuttingDetailPrev" disabled>Prev</button>
                            <button type="button" class="btn btn-sm btn-label-secondary" id="cuttingDetailNext" disabled>Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush

    @push('page-js')
        <script>
            $(function () {
                $('.select2').select2({ width: '100%', dropdownParent: $('body') });
                $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y', allowInput: true, disableMobile: true });

                var detailUrl = @json(route('reporting.agent-cutting-price.details'));
                var filterQuery = @json(request()->except(['page', 'agent_id']));
                var currentAgentId = null;
                var currentPage = 1;
                var lastPage = 1;
                var modalEl = document.getElementById('cuttingDetailModal');
                var modal = modalEl ? new bootstrap.Modal(modalEl) : null;

                function formatRp(n) {
                    return Number(n || 0).toLocaleString('id-ID');
                }

                function loadDetails(agentId, page) {
                    currentAgentId = agentId;
                    currentPage = page || 1;
                    $('#cuttingDetailBody').html('<tr><td colspan="11" class="text-center text-muted py-4">Memuat...</td></tr>');

                    $.get(detailUrl, Object.assign({}, filterQuery, {
                        agent_id: agentId,
                        per_page: 50,
                        page: currentPage
                    })).done(function (res) {
                        if (!res.success) {
                            $('#cuttingDetailBody').html('<tr><td colspan="11" class="text-center text-danger py-4">Gagal memuat data.</td></tr>');
                            return;
                        }

                        var rows = res.data.rows || [];
                        if (!rows.length) {
                            $('#cuttingDetailBody').html('<tr><td colspan="11" class="text-center text-muted py-4">Tidak ada detail.</td></tr>');
                        } else {
                            var html = rows.map(function (r) {
                                return '<tr>' +
                                    '<td>' + (r.sales_date || '-') + '</td>' +
                                    '<td>' + (r.sales_number || '-') + '</td>' +
                                    '<td>' + (r.seller || '-') + '</td>' +
                                    '<td><div>' + (r.product || '-') + '</div><div class="text-muted small">' + (r.variant_sku || '') + '</div></td>' +
                                    '<td>' + (r.unit || '-') + '</td>' +
                                    '<td class="text-end">' + formatRp(r.quantity) + '</td>' +
                                    '<td class="text-end">' + formatRp(r.agent_unit_price) + '</td>' +
                                    '<td class="text-end">' + formatRp(r.agent_net_price) + '</td>' +
                                    '<td class="text-end">' + formatRp(r.distributor_price) + '</td>' +
                                    '<td class="text-end text-danger fw-semibold">' + formatRp(r.gap_amount) + '</td>' +
                                    '<td class="text-end">' + formatRp(r.gap_percent) + '%</td>' +
                                    '</tr>';
                            }).join('');
                            $('#cuttingDetailBody').html(html);
                        }

                        lastPage = res.data.meta.last_page || 1;
                        $('#cuttingDetailMeta').text('Halaman ' + res.data.meta.current_page + ' / ' + lastPage + ' · Total ' + res.data.meta.total);
                        $('#cuttingDetailPrev').prop('disabled', currentPage <= 1);
                        $('#cuttingDetailNext').prop('disabled', currentPage >= lastPage);
                    }).fail(function () {
                        $('#cuttingDetailBody').html('<tr><td colspan="11" class="text-center text-danger py-4">Gagal memuat data.</td></tr>');
                    });
                }

                $('.btn-view-details').on('click', function () {
                    var agentId = $(this).data('agent-id');
                    var title = $(this).data('title');
                    $('#cuttingDetailTitle').text('Detail Cutting · ' + title);
                    if (modal) modal.show();
                    loadDetails(agentId, 1);
                });

                $('#cuttingDetailPrev').on('click', function () {
                    if (currentPage > 1) loadDetails(currentAgentId, currentPage - 1);
                });
                $('#cuttingDetailNext').on('click', function () {
                    if (currentPage < lastPage) loadDetails(currentAgentId, currentPage + 1);
                });
            });
        </script>
    @endpush
</x-app-layout>
