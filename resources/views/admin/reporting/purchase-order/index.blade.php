<x-app-layout>
    @section('title', 'Purchase Order Report | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
    @endpush

    @push('page-css')
        <style>
            .po-kpi-card { transition: transform .2s, box-shadow .2s; }
            .po-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(99,108,255,.08) !important; }
            .po-scroll-table { max-height: 440px; overflow-y: auto; }
            .po-scroll-table thead.sticky-top th { background: #f8f9fa !important; z-index: 2; }
            .po-stat-box { padding: .85rem 1rem; border-radius: .5rem; height: 100%; }
            .po-stat-box .label { font-size: .75rem; color: #6c757d; margin-bottom: .25rem; }
            .po-stat-box .value { font-size: 1.1rem; font-weight: 600; color: #212529; line-height: 1.2; }
            .po-status-badge { font-size: .7rem; padding: .25rem .5rem; }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            title="Purchase Order Report"
            subtitle="Laporan ringkas purchase order: total belanja, supplier, produk, dan status."
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Reporting'],
                ['label' => 'Purchase Order', 'active' => true],
            ]"
        />

        @php
            $fmtRp = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
            $statusBadge = [
                'draft' => 'bg-label-secondary',
                'process' => 'bg-label-info',
                'receiving' => 'bg-label-primary',
                'payment' => 'bg-label-warning',
                'received' => 'bg-label-success',
                'cancelled' => 'bg-label-danger',
                'void' => 'bg-label-danger',
            ];
        @endphp

        {{-- FILTER CARD --}}
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pb-0">
                <h6 class="mb-0 fw-bold"><i class="ti ti-filter me-1"></i> Filter Laporan</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('reporting.purchase-order.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small text-muted">Branch</label>
                            <select name="branch_id" class="form-select select2">
                                <option value="">All Branch</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" @selected($branchId === $b->id)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small text-muted">Supplier</label>
                            <select name="supplier_id" class="form-select select2">
                                <option value="">All Supplier</option>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}" @selected($supplierId === $s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label small text-muted">Status</label>
                            <select name="status" class="form-select select2">
                                <option value="">All Status</option>
                                @foreach($statusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label small text-muted">Date From</label>
                            <input type="text" name="date_from" class="form-control flatpickr-date" value="{{ format_date_id($dateFrom) }}" placeholder="DD/MM/YYYY" required>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label small text-muted">Date To</label>
                            <input type="text" name="date_to" class="form-control flatpickr-date" value="{{ format_date_id($dateTo) }}" placeholder="DD/MM/YYYY" required>
                        </div>
                        <div class="col-12 d-flex gap-2 justify-content-end">
                            <a href="{{ route('reporting.purchase-order.index') }}" class="btn btn-label-dark">
                                <i class="ti ti-x me-1"></i> Reset
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-search me-1"></i> Tampilkan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- KPI SUMMARY --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="po-kpi-card h-100">
                    <x-dashboard.kpi-card
                        title="Total Purchase"
                        :value="$fmtRp($totalPurchase ?? 0)"
                        subtitle="Exclude cancelled / void"
                        icon="ti ti-cash"
                        iconColor="success"
                    />
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="po-kpi-card h-100">
                    <x-dashboard.kpi-card
                        title="Total PO"
                        :value="format_number($totalPoCount ?? 0, 0, true)"
                        :subtitle="'Active: ' . format_number($totalPoActive ?? 0, 0, true)"
                        icon="ti ti-file-invoice"
                        iconColor="primary"
                    />
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="po-kpi-card h-100">
                    <x-dashboard.kpi-card
                        title="Avg PO Value"
                        :value="$fmtRp($avgPoValue ?? 0)"
                        subtitle="Rata-rata nilai per PO"
                        icon="ti ti-coin"
                        iconColor="warning"
                    />
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="po-kpi-card h-100">
                    <x-dashboard.kpi-card
                        title="Total Tax & Discount"
                        :value="$fmtRp(($totalTax ?? 0) + ($totalDiscount ?? 0))"
                        :subtitle="'Tax ' . $fmtRp($totalTax ?? 0) . ' · Disc ' . $fmtRp($totalDiscount ?? 0)"
                        icon="ti ti-receipt"
                        iconColor="info"
                    />
                </div>
            </div>
        </div>

        {{-- STATUS BREAKDOWN --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-bold">Status PO</h6>
                            <small class="text-muted">
                                Periode {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
                                · {{ (int) $daysInRange }} hari
                            </small>
                        </div>
                        <i class="ti ti-clipboard-list text-primary fs-4"></i>
                    </div>
                    <div class="card-body pt-2">
                        <div class="row g-3">
                            <div class="col-md-4 col-6">
                                <div class="po-stat-box bg-label-success">
                                    <div class="label">Received</div>
                                    <div class="value">{{ format_number($totalReceived ?? 0, 0, true) }} PO</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="po-stat-box bg-label-warning">
                                    <div class="label">Pending / Proses</div>
                                    <div class="value">{{ format_number(max(0, $totalPending ?? 0), 0, true) }} PO</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-12">
                                <div class="po-stat-box bg-label-danger">
                                    <div class="label">Cancelled</div>
                                    <div class="value">{{ format_number($totalCancelled ?? 0, 0, true) }} PO</div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-borderless mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Status</th>
                                        <th class="text-end">Jumlah</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($statusAgg as $row)
                                    <tr>
                                        <td>
                                            <span class="badge po-status-badge {{ $statusBadge[$row->status] ?? 'bg-label-secondary' }}">
                                                {{ $statusOptions[$row->status] ?? ucfirst($row->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">{{ format_number($row->cnt ?? 0, 0, true) }}</td>
                                        <td class="text-end">{{ $fmtRp($row->total ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Tidak ada data pada periode ini.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-bold">Ringkasan Belanja</h6>
                            <small class="text-muted">Breakdown nilai PO aktif.</small>
                        </div>
                        <i class="ti ti-receipt-2 text-warning fs-4"></i>
                    </div>
                    <div class="card-body pt-2">
                        <div class="row g-2 small">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                    <span class="text-muted">Subtotal</span>
                                    <span class="fw-semibold">{{ $fmtRp($totalSubtotal ?? 0) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                    <span class="text-muted">Pajak</span>
                                    <span class="fw-semibold">{{ $fmtRp($totalTax ?? 0) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2">
                                    <span class="text-muted">Diskon</span>
                                    <span class="fw-semibold text-danger">- {{ $fmtRp($totalDiscount ?? 0) }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                    <span class="text-muted">Total Grand</span>
                                    <span class="fw-semibold">{{ $fmtRp($totalPurchase ?? 0) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                    <span class="text-muted">Avg / PO</span>
                                    <span class="fw-semibold">{{ $fmtRp($avgPoValue ?? 0) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2">
                                    <span class="text-muted">Avg / hari</span>
                                    <span class="fw-semibold">{{ $fmtRp(($totalPurchase ?? 0) / max(1, $daysInRange)) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- CHARTS ROW --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-8">
                <x-dashboard.chart-card title="Trend Purchase (Nilai & Jumlah PO)">
                    <div id="chart-po-daily" style="height: 300px;"></div>
                </x-dashboard.chart-card>
            </div>
            <div class="col-xl-4">
                <x-dashboard.chart-card title="Distribusi Status PO">
                    <div id="chart-po-status" style="height: 300px;"></div>
                </x-dashboard.chart-card>
            </div>
        </div>

        {{-- SUPPLIER & PRODUCT ROW --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-bold">Top Supplier</h6>
                            <small class="text-muted">Supplier dengan total belanja terbesar.</small>
                        </div>
                        <span class="badge bg-label-primary">Top 15</span>
                    </div>
                    <div class="po-scroll-table">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Supplier</th>
                                    <th class="text-end" style="width: 80px;">PO</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end d-none d-md-table-cell">Avg</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($topSuppliers as $i => $s)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $s->supplier }}</td>
                                    <td class="text-end">{{ format_number($s->po_count ?? 0, 0, true) }}</td>
                                    <td class="text-end">{{ $fmtRp($s->total_amount ?? 0) }}</td>
                                    <td class="text-end d-none d-md-table-cell">{{ $fmtRp($s->avg_amount ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada data supplier.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-bold">Top Produk Dibeli</h6>
                            <small class="text-muted">Qty, subtotal, dan harga rata-rata per unit.</small>
                        </div>
                        <span class="badge bg-label-primary">Top 15</span>
                    </div>
                    <div class="po-scroll-table">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Produk</th>
                                    <th class="text-end" style="width: 90px;">Qty</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-end d-none d-md-table-cell">Avg / unit</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($topProducts as $i => $p)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $p->product }}</td>
                                    <td class="text-end">{{ format_number($p->qty ?? 0, 0, true) }}</td>
                                    <td class="text-end">{{ $fmtRp($p->subtotal ?? 0) }}</td>
                                    <td class="text-end d-none d-md-table-cell">{{ $fmtRp($p->avg_unit_price ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada data produk.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- LATEST PURCHASE ORDERS --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold">Daftar Purchase Order Terbaru</h6>
                    <small class="text-muted">50 PO terakhir sesuai filter.</small>
                </div>
                <span class="badge bg-label-primary">{{ $latestPO->count() }} PO</span>
            </div>
            <div class="po-scroll-table">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>No. PO</th>
                            <th>Tanggal</th>
                            <th>Supplier</th>
                            <th>Branch</th>
                            <th class="text-center" style="width: 110px;">Status</th>
                            <th class="text-end" style="width: 150px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($latestPO as $i => $po)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td class="fw-semibold">{{ $po->purchase_number }}</td>
                            <td>{{ \Carbon\Carbon::parse($po->purchase_date)->format('d/m/Y') }}</td>
                            <td>{{ optional($po->supplier)->name ?? $po->supplier_name ?? '-' }}</td>
                            <td>{{ optional($po->branch)->name ?? '-' }}</td>
                            <td class="text-center">
                                <span class="badge po-status-badge {{ $statusBadge[$po->status] ?? 'bg-label-secondary' }}">
                                    {{ $statusOptions[$po->status] ?? ucfirst($po->status) }}
                                </span>
                            </td>
                            <td class="text-end">{{ $fmtRp($po->total ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Belum ada purchase order pada periode ini.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
    @endpush

    @push('page-js')
        <script>
            $(document).ready(function () {
                $('.select2').select2({
                    theme: 'bootstrap-5',
                    allowClear: true,
                    width: '100%'
                });

                $('.flatpickr-date').flatpickr({
                    dateFormat: 'd/m/Y',
                    allowInput: true
                });
            });

            document.addEventListener('DOMContentLoaded', function () {
                const baseOpts = {
                    chart: { fontFamily: 'inherit', toolbar: { show: false } },
                    grid: { borderColor: '#f1f1f4', strokeDashArray: 3 },
                    colors: ['#696cff', '#03c3ec', '#71dd37', '#ffab00', '#ff3e1d', '#8592a3'],
                    tooltip: { theme: 'light' },
                    dataLabels: { enabled: false },
                };

                const fmtRp = (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(v || 0));

                const dailyData = @json($dailyTrend);
                if (dailyData.length > 0 && document.querySelector('#chart-po-daily')) {
                    new ApexCharts(document.querySelector('#chart-po-daily'), {
                        ...baseOpts,
                        chart: { ...baseOpts.chart, type: 'line', height: 300, stacked: false },
                        series: [
                            { name: 'Nilai Purchase', type: 'column', data: dailyData.map(d => Math.round(d.total_amount)) },
                            { name: 'Jumlah PO', type: 'line', data: dailyData.map(d => d.po_count) },
                        ],
                        xaxis: { categories: dailyData.map(d => d.date) },
                        yaxis: [
                            { title: { text: 'Nilai' }, labels: { formatter: fmtRp } },
                            { opposite: true, title: { text: 'Jumlah PO' } },
                        ],
                        stroke: { curve: 'smooth', width: [0, 3] },
                        fill: { type: ['solid', 'gradient'], gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
                    }).render();
                }

                const statusData = @json($statusAgg);
                if (statusData.length > 0 && document.querySelector('#chart-po-status')) {
                    const labels = statusData.map(d => d.status ? d.status.charAt(0).toUpperCase() + d.status.slice(1) : '-');
                    new ApexCharts(document.querySelector('#chart-po-status'), {
                        ...baseOpts,
                        chart: { ...baseOpts.chart, type: 'donut', height: 300 },
                        series: statusData.map(d => parseInt(d.cnt) || 0),
                        labels: labels,
                        legend: { position: 'bottom', fontSize: '12px' },
                        dataLabels: { enabled: true, formatter: (v) => v.toFixed(1) + '%' },
                    }).render();
                }
            });
        </script>
    @endpush
</x-app-layout>
