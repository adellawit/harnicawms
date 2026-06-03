<x-app-layout>
    @section('title', 'Summary Sales Report | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
    @endpush

    @push('page-css')
        <style>
            .summary-kpi-card { transition: transform .2s, box-shadow .2s; }
            .summary-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(99,108,255,.08) !important; }
            .summary-scroll-table { max-height: 420px; overflow-y: auto; }
            .summary-scroll-table thead.sticky-top th { background: #f8f9fa !important; }
            .summary-stat-box { padding: .85rem 1rem; border-radius: .5rem; height: 100%; }
            .summary-stat-box .label { font-size: .75rem; color: #6c757d; margin-bottom: .25rem; }
            .summary-stat-box .value { font-size: 1.15rem; font-weight: 600; color: #212529; line-height: 1.2; }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            title="Summary Sales"
            subtitle="Laporan ringkas transaksi, metode pembayaran, rata-rata penjualan, dan performa produk."
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Reporting'],
                ['label' => 'Summary Sales', 'active' => true],
            ]"
        />

        @php
            $fmtRp = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        @endphp

        {{-- FILTER CARD --}}
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pb-0">
                <h6 class="mb-0 fw-bold"><i class="ti ti-filter me-1"></i> Filter Laporan</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('reporting.summary-sales.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small text-muted">Branch</label>
                            <select name="branch_id" class="form-select select2">
                                <option value="">All Branch</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" @if($branchId === $b->id) selected @endif>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small text-muted">Date From</label>
                            <input type="text" name="date_from" class="form-control flatpickr-date" value="{{ format_date_id($dateFrom) }}" placeholder="DD/MM/YYYY" required>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small text-muted">Date To</label>
                            <input type="text" name="date_to" class="form-control flatpickr-date" value="{{ format_date_id($dateTo) }}" placeholder="DD/MM/YYYY" required>
                        </div>
                        <div class="col-lg-3 col-md-6 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="ti ti-search me-1"></i> Tampilkan
                            </button>
                            <a href="{{ route('reporting.summary-sales.index') }}" class="btn btn-label-dark">
                                <i class="ti ti-x"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- KPI SUMMARY --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="summary-kpi-card h-100">
                    <x-dashboard.kpi-card title="Total Revenue" :value="$fmtRp($totalRevenue ?? 0)" icon="ti ti-cash" iconColor="success" />
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="summary-kpi-card h-100">
                    <x-dashboard.kpi-card title="Total Transactions" :value="format_number($totalTransactions ?? 0, 0, true)" icon="ti ti-receipt-2" iconColor="primary" />
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="summary-kpi-card h-100">
                    <x-dashboard.kpi-card title="Avg Order Value (AOV)" :value="$fmtRp($avgOrderValue ?? 0)" icon="ti ti-coin" iconColor="warning" />
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="summary-kpi-card h-100">
                    <x-dashboard.kpi-card title="Avg Items / Transaksi" :value="format_number($avgItemsPerTransaction ?? 0, 2, true)" icon="ti ti-shopping-cart" iconColor="info" />
                </div>
            </div>
        </div>

        {{-- AVERAGE ROW (Transaksi + Produk) --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-bold">Rata-rata Transaksi</h6>
                            <small class="text-muted">
                                {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
                                · {{ (int) $daysInRange }} hari
                            </small>
                        </div>
                        <i class="ti ti-chart-bar text-primary fs-4"></i>
                    </div>
                    <div class="card-body pt-2">
                        <div class="row g-3">
                            <div class="col-md-4 col-6">
                                <div class="summary-stat-box bg-label-primary">
                                    <div class="label">Avg transaksi / hari</div>
                                    <div class="value">{{ format_number($avgTransactionsPerDay ?? 0, 2, true) }}</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6">
                                <div class="summary-stat-box bg-label-success">
                                    <div class="label">Avg revenue / hari</div>
                                    <div class="value">{{ $fmtRp($avgRevenuePerDay ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-12">
                                <div class="summary-stat-box bg-label-secondary">
                                    <div class="label">Produk unik terjual</div>
                                    <div class="value">{{ format_number($distinctProductsSold ?? 0, 0, true) }} <small class="text-muted fw-normal">SKU</small></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-bold">Rata-rata Penjualan Produk</h6>
                            <small class="text-muted">Dihitung dari produk yang ada penjualan di periode ini.</small>
                        </div>
                        <i class="ti ti-box-seam text-warning fs-4"></i>
                    </div>
                    <div class="card-body pt-2">
                        <div class="row g-2 small">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                    <span class="text-muted">Avg revenue / produk</span>
                                    <span class="fw-semibold">{{ $fmtRp($avgRevenuePerProductSold ?? 0) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2">
                                    <span class="text-muted">Avg qty / produk</span>
                                    <span class="fw-semibold">{{ format_number($avgQtyPerProductSold ?? 0, 2, true) }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                    <span class="text-muted">Total qty item terjual</span>
                                    <span class="fw-semibold">{{ format_number($totalItemsSold ?? 0, 0, true) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2">
                                    <span class="text-muted">Total revenue</span>
                                    <span class="fw-semibold">{{ $fmtRp($totalRevenue ?? 0) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- CHARTS ROW 1 --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-8">
                <x-dashboard.chart-card title="Sales Trend (Revenue & Transaksi)">
                    <div id="chart-sales-summary-daily" style="height: 300px;"></div>
                </x-dashboard.chart-card>
            </div>
            <div class="col-xl-4">
                <x-dashboard.chart-card title="By Metode Bayar">
                    <div id="chart-sales-summary-payment" style="height: 300px;"></div>
                </x-dashboard.chart-card>
            </div>
        </div>

        {{-- CHARTS ROW 2 --}}
        <div class="row g-3 mb-4">
            <div class="col-12">
                <x-dashboard.chart-card title="Average Order Value (AOV) Harian">
                    <div id="chart-sales-summary-aov" style="height: 260px;"></div>
                </x-dashboard.chart-card>
            </div>
        </div>

        {{-- TABLES ROW: Summary Harian + By Kategori --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-transparent border-0">
                        <h6 class="mb-0 fw-bold">Summary Harian</h6>
                        <small class="text-muted">Rekap per hari: transaksi, revenue, dan AOV.</small>
                    </div>
                    <div class="summary-scroll-table">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Tanggal</th>
                                    <th class="text-end">Transaksi</th>
                                    <th class="text-end">Revenue</th>
                                    <th class="text-end">AOV</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($dailyTrend as $row)
                                @php
                                    $tx = (int) $row->transactions;
                                    $rev = (float) $row->revenue;
                                    $aov = $tx > 0 ? $rev / $tx : 0;
                                @endphp
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($row->date)->format('d/m/Y') }}</td>
                                    <td class="text-end">{{ format_number($tx, 0, true) }}</td>
                                    <td class="text-end">{{ $fmtRp($rev) }}</td>
                                    <td class="text-end">{{ $fmtRp($aov) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Belum ada transaksi pada periode ini.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-transparent border-0">
                        <h6 class="mb-0 fw-bold">By Kategori Produk</h6>
                        <small class="text-muted">Total qty & revenue per kategori.</small>
                    </div>
                    <div class="summary-scroll-table">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Kategori</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($categorySummary as $cat)
                                <tr>
                                    <td>{{ $cat->category }}</td>
                                    <td class="text-end">{{ format_number($cat->qty ?? 0, 0, true) }}</td>
                                    <td class="text-end">{{ $fmtRp($cat->revenue ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">Belum ada data kategori.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- TABLE FULL WIDTH: Product performance --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold">Performa Produk</h6>
                    <small class="text-muted">Qty, revenue, dan rata-rata harga jual per unit.</small>
                </div>
                <span class="badge bg-label-primary">{{ $productSalesAvg->count() }} produk</span>
            </div>
            <div class="summary-scroll-table">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Produk</th>
                            <th class="text-end" style="width: 100px;">Qty</th>
                            <th class="text-end" style="width: 160px;">Revenue</th>
                            <th class="text-end" style="width: 140px;">Avg / unit</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($productSalesAvg as $i => $p)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $p->name }}</td>
                            <td class="text-end">{{ format_number($p->qty ?? 0, 0, true) }}</td>
                            <td class="text-end">{{ $fmtRp($p->revenue ?? 0) }}</td>
                            <td class="text-end">{{ $fmtRp($p->avg_unit_price ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Belum ada penjualan produk.</td>
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
                    placeholder: 'All Branch',
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
                if (dailyData.length > 0 && document.querySelector('#chart-sales-summary-daily')) {
                    new ApexCharts(document.querySelector('#chart-sales-summary-daily'), {
                        ...baseOpts,
                        chart: { ...baseOpts.chart, type: 'area', height: 300 },
                        series: [
                            { name: 'Revenue', type: 'column', data: dailyData.map(d => Math.round(d.revenue)) },
                            { name: 'Transaksi', type: 'line', data: dailyData.map(d => d.transactions) },
                        ],
                        xaxis: { categories: dailyData.map(d => d.date) },
                        yaxis: [
                            { title: { text: 'Revenue' }, labels: { formatter: fmtRp } },
                            { opposite: true, title: { text: 'Transaksi' } },
                        ],
                        stroke: { curve: 'smooth', width: [0, 3] },
                        fill: { type: ['solid', 'gradient'], gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
                    }).render();
                }

                const payData = @json($paymentByMethod);
                if (payData.length > 0 && document.querySelector('#chart-sales-summary-payment')) {
                    new ApexCharts(document.querySelector('#chart-sales-summary-payment'), {
                        ...baseOpts,
                        chart: { ...baseOpts.chart, type: 'donut', height: 300 },
                        series: payData.map(d => Math.round(d.total)),
                        labels: payData.map(d => d.name),
                        legend: { position: 'bottom', fontSize: '12px' },
                        dataLabels: { enabled: true, formatter: (v) => v.toFixed(1) + '%' },
                    }).render();
                }

                const aovData = @json($dailyAovTrend);
                if (aovData.length > 0 && document.querySelector('#chart-sales-summary-aov')) {
                    new ApexCharts(document.querySelector('#chart-sales-summary-aov'), {
                        ...baseOpts,
                        chart: { ...baseOpts.chart, type: 'line', height: 260 },
                        series: [{ name: 'AOV', data: aovData.map(d => Math.round(d.aov)) }],
                        xaxis: { categories: aovData.map(d => d.date) },
                        yaxis: { labels: { formatter: fmtRp } },
                        stroke: { curve: 'smooth', width: 3 },
                        markers: { size: 4 },
                        colors: ['#ffab00'],
                    }).render();
                }
            });
        </script>
    @endpush
</x-app-layout>
