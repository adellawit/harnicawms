@php
    $fmtRp = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $fmtNum = fn($v) => number_format((float) $v, 0, ',', '.');
    $pctChange = fn($now, $prev) => $prev > 0 ? round((($now - $prev) / $prev) * 100, 1) : ($now > 0 ? 100 : 0);

    $revDayPct = $pctChange($revenueToday, $revenueYesterday);
    $revMonPct = $pctChange($revenueThisMonth, $revenueLastMonth);
    $txDayPct  = $pctChange($txToday, $txYesterday);

    // Visibility helper: if no config exists at all, show everything (backward compatible).
    // Once configured, only show sections/widgets explicitly enabled.
    $dv = $dashboardVisibility ?? [];
    $hasConfig = !empty($dv);
    $sectionVisible = fn(string $section) => !$hasConfig || !empty($dv[$section]['_enabled']);
    $widgetVisible = fn(string $section, string $widget) => !$hasConfig || !isset($dv[$section]) || !empty($dv[$section][$widget]);
@endphp

<x-app-layout>

    @section('title', 'Dashboard POS & WMS | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
    @endpush

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}" />
        <style>
            .dashboard-stat-card { transition: transform 0.2s, box-shadow 0.2s; }
            .dashboard-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(105, 108, 255, 0.08) !important; }
            .section-divider { border-top: 2px solid #e9ecef; margin: 2rem 0 0.5rem; }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">

        @php
            $selectedOutlet = $outlets->firstWhere('id', $currentBranchId);
            $isDefaultPeriod = $periodStart->isSameDay($periodEnd->copy()->startOfMonth()) && $periodEnd->isSameDay(now()->startOfDay());
            $isFilterActive = filled($currentBranchId) || ! $isDefaultPeriod;
            $periodLabel = $periodStart->format('d M Y') . ' — ' . $periodEnd->format('d M Y');
        @endphp

        {{-- PAGE HEADER --}}
        <x-page-header
            title="Dashboard"
            subtitle="Real-time monitoring sales, inventory, warehouse, and operational."
            :breadcrumbs="[['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Dashboard', 'active' => true]]"
        >
            <x-slot:actions>
                <span class="badge bg-label-secondary">
                    <i class="ti ti-building-store me-1"></i>{{ $selectedOutlet->name ?? 'All Outlets' }}
                </span>
                <span class="badge bg-label-primary">
                    <i class="ti ti-calendar me-1"></i>{{ $periodLabel }}
                </span>
                <button type="button"
                        class="btn btn-sm {{ $isFilterActive ? 'btn-warning' : 'btn-primary' }}"
                        data-bs-toggle="modal"
                        data-bs-target="#dashboardFilterModal">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
            </x-slot:actions>
        </x-page-header>

        <x-modal id="dashboardFilterModal" title="Filter Dashboard">
            <div class="mb-3">
                <label class="form-label" for="outlet-filter">Branch / Outlet</label>
                <select id="outlet-filter" class="form-select" data-placeholder="Pilih Outlet" data-allow-clear="true">
                    <option value="">All Outlets</option>
                    @foreach($outlets as $o)
                        <option value="{{ $o->id }}" {{ $o->id === $currentBranchId ? 'selected' : '' }}>{{ $o->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-1">
                <label class="form-label" for="dashboard-period-range">Periode</label>
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                    <input type="text"
                           id="dashboard-period-range"
                           class="form-control"
                           placeholder="Pilih periode"
                           readonly
                           autocomplete="off"
                           value=""
                           data-date-from="{{ $periodStart->toDateString() }}"
                           data-date-to="{{ $periodEnd->toDateString() }}"
                           data-default-from="{{ $today->copy()->startOfMonth()->toDateString() }}"
                           data-default-to="{{ $today->toDateString() }}">
                </div>
                <div class="form-text">Default otomatis: tanggal 1 bulan ini sampai hari ini (contoh 1–3 Agu; besok jadi 1–4 Agu).</div>
            </div>
            <x-slot:footer>
                <button type="button" class="btn btn-label-dark" id="btnResetDashboardFilter">Reset</button>
                <button type="button" class="btn btn-primary" id="btnApplyDashboardFilter">Terapkan</button>
            </x-slot:footer>
        </x-modal>

        @php $partnerPksStats = $partnerPksStats ?? ['expiring' => 0, 'missing' => 0, 'expired' => 0]; @endphp
        <div class="row g-3 mb-4">
            <div class="col-12">
                <x-dashboard.section-header icon="ti ti-file-certificate" title="Partner Agent PKS" subtitle="Monitoring perjanjian kerja sama agent." />
            </div>
            <div class="col-md-4">
                <a href="{{ route('partner.agents.index', ['pks_status' => 'expiring']) }}" class="text-decoration-none">
                    <x-dashboard.kpi-card title="PKS Segera Berakhir" :value="$fmtNum($partnerPksStats['expiring'] ?? 0)" subtitle="≤ 30 hari" icon="ti ti-alert-triangle" iconColor="danger" />
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('partner.agents.index', ['pks_status' => 'missing']) }}" class="text-decoration-none">
                    <x-dashboard.kpi-card title="Belum Upload PKS" :value="$fmtNum($partnerPksStats['missing'] ?? 0)" subtitle="Sudah transaksi pertama" icon="ti ti-file-off" iconColor="warning" />
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('partner.agents.index', ['pks_status' => 'expired']) }}" class="text-decoration-none">
                    <x-dashboard.kpi-card title="PKS Expired" :value="$fmtNum($partnerPksStats['expired'] ?? 0)" icon="ti ti-calendar-x" iconColor="secondary" />
                </a>
            </div>
        </div>

        @if($sectionVisible('executive_overview'))
        {{-- EXECUTIVE OVERVIEW --}}
        <x-dashboard.section-header icon="ti ti-dashboard" title="Executive Overview" subtitle="Ringkasan performa bisnis untuk owner & manajemen." />

        @if($widgetVisible('executive_overview', 'kpi_cards'))
        <div class="dashboard-kpi-grid mb-4">
            <x-dashboard.kpi-card title="Revenue Today" :value="$fmtRp($revenueToday)" :trend="($revDayPct >= 0 ? '+' : '') . $revDayPct . '% vs yesterday'" :trendType="$revDayPct >= 0 ? 'up' : 'down'" icon="ti ti-cash" iconColor="success" />
            <x-dashboard.kpi-card title="Revenue This Month" :value="$fmtRp($revenueThisMonth)" :trend="($revMonPct >= 0 ? '+' : '') . $revMonPct . '% vs last month'" :trendType="$revMonPct >= 0 ? 'up' : 'down'" icon="ti ti-calendar-stats" iconColor="primary" />
            <x-dashboard.kpi-card title="Transactions Today" :value="$fmtNum($txToday)" :trend="($txDayPct >= 0 ? '+' : '') . $txDayPct . '% vs yesterday'" :trendType="$txDayPct >= 0 ? 'up' : 'down'" icon="ti ti-receipt-2" iconColor="info" />
            <x-dashboard.kpi-card title="Gross Profit" :value="$fmtRp($grossProfit)" :trend="number_format($profitMargin, 1) . '% margin'" :trendType="$profitMargin >= 0 ? 'up' : 'down'" icon="ti ti-report-money" iconColor="warning" />
            <x-dashboard.kpi-card title="Best Outlet" :value="$bestOutlet->name ?? '-'" :subtitle="$bestOutlet ? $fmtRp($bestOutlet->revenue) . ' /bln' : ''" icon="ti ti-building-store" iconColor="primary" />
            <x-dashboard.kpi-card title="Best Product" :value="$bestProduct->name ?? '-'" :subtitle="$bestProduct ? $fmtRp($bestProduct->revenue) . ' /bln' : ''" icon="ti ti-star" iconColor="warning" />
        </div>
        @endif

        <div class="row g-3 mb-4">
            @if($widgetVisible('executive_overview', 'sales_trend_chart'))
            <div class="col-xl-8">
                <x-dashboard.chart-card title="Sales Trend (Monthly)">
                    <div id="chart-executive-sales" style="height: 280px;"></div>
                </x-dashboard.chart-card>
            </div>
            @endif
            @if($widgetVisible('executive_overview', 'outlet_performance_chart'))
            <div class="col-xl-4">
                <x-dashboard.chart-card title="Outlet Performance">
                    <div id="chart-executive-outlet" style="height: 280px;"></div>
                </x-dashboard.chart-card>
            </div>
            @endif
        </div>
        @endif

        @if($sectionVisible('sales'))
        {{-- 1. SALES DASHBOARD --}}
        <div class="section-divider"></div>
        <x-dashboard.section-header icon="ti ti-chart-bar" title="Sales Dashboard" subtitle="Performa penjualan: revenue, transaksi, dan metode pembayaran." />

        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Revenue Today" :value="$fmtRp($revenueToday)" :trend="($revDayPct >= 0 ? '+' : '') . $revDayPct . '% vs yesterday'" :trendType="$revDayPct >= 0 ? 'up' : 'down'" icon="ti ti-cash" iconColor="success" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Revenue This Month" :value="$fmtRp($revenueThisMonth)" :trend="($revMonPct >= 0 ? '+' : '') . $revMonPct . '% vs last month'" :trendType="$revMonPct >= 0 ? 'up' : 'down'" icon="ti ti-calendar" iconColor="primary" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Transactions (Month)" :value="$fmtNum($txThisMonth)" icon="ti ti-receipt-2" iconColor="info" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Avg Order Value" :value="$fmtRp($aov)" icon="ti ti-coin" iconColor="warning" />
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-8">
                <x-dashboard.chart-card title="Sales Trend (Daily - Last 7 Days)">
                    <div id="chart-sales-daily" style="height: 280px;"></div>
                </x-dashboard.chart-card>
            </div>
            <div class="col-xl-4">
                <x-dashboard.chart-card title="Payment Methods">
                    <div id="chart-sales-payment" style="height: 280px;"></div>
                </x-dashboard.chart-card>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-4">
                <x-card class="shadow-sm border-0 h-100">
                    <x-slot:header><h6 class="mb-0 fw-bold">Top Selling Products</h6></x-slot:header>
                    <x-table class="table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>#</th><th>Product</th><th class="text-end">Qty</th><th class="text-end">Revenue</th></tr>
                        </thead>
                        <tbody>
                            @forelse($topProducts as $i => $p)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td class="text-truncate" style="max-width: 150px;">{{ $p->name }}</td>
                                    <td class="text-end">{{ $fmtNum($p->qty) }}</td>
                                    <td class="text-end">{{ $fmtRp($p->revenue) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                    </x-table>
                </x-card>
            </div>
            <div class="col-xl-4">
                <x-card class="shadow-sm border-0 h-100">
                    <x-slot:header><h6 class="mb-0 fw-bold">Top Categories</h6></x-slot:header>
                    <x-table class="table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Category</th><th class="text-end">Qty</th><th class="text-end">Revenue</th></tr>
                        </thead>
                        <tbody>
                            @forelse($topCategories as $c)
                                <tr>
                                    <td>{{ $c->category }}</td>
                                    <td class="text-end">{{ $fmtNum($c->qty) }}</td>
                                    <td class="text-end">{{ $fmtRp($c->revenue) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted text-center">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                    </x-table>
                </x-card>
            </div>
            <div class="col-xl-4">
                <x-dashboard.chart-card title="Sales per Outlet">
                    <div id="chart-sales-outlet" style="height: 240px;"></div>
                </x-dashboard.chart-card>
            </div>
        </div>

        @endif

        @if($sectionVisible('inventory'))
        {{-- 2. INVENTORY DASHBOARD --}}
        <div class="section-divider"></div>
        <x-dashboard.section-header icon="ti ti-packages" title="Inventory Dashboard" subtitle="Kondisi stok barang, inventory value, dan pergerakan stok." />

        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Total SKU" :value="$fmtNum($totalSku)" icon="ti ti-barcode" iconColor="primary" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Total Stock" :value="$fmtNum($totalStock) . ' pcs'" icon="ti ti-box" iconColor="info" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Inventory Value" :value="$fmtRp($inventoryValue)" icon="ti ti-report-analytics" iconColor="success" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Low Stock" :value="$lowStockCount" :trend="$outOfStockCount . ' out of stock'" trendType="down" icon="ti ti-alert-triangle" iconColor="danger" />
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-8">
                <x-dashboard.chart-card title="Stock by Category">
                    <div id="chart-inventory-category" style="height: 280px;"></div>
                </x-dashboard.chart-card>
            </div>
            <div class="col-xl-4">
                <x-card class="shadow-sm border-0 h-100">
                    <x-slot:header><h6 class="mb-0 fw-bold">Low Stock Products</h6></x-slot:header>
                    <x-table class="table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Product</th><th>Warehouse</th><th class="text-end">Stock</th><th class="text-end">Min</th></tr>
                        </thead>
                        <tbody>
                            @forelse($lowStockProducts as $ls)
                                <tr>
                                    <td class="text-truncate" style="max-width: 120px;">{{ $ls->name }}</td>
                                    <td>{{ $ls->warehouse }}</td>
                                    <td class="text-end fw-bold text-danger">{{ $fmtNum($ls->on_hand) }}</td>
                                    <td class="text-end">{{ $fmtNum($ls->min_stock) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center">Semua stok aman</td></tr>
                            @endforelse
                        </tbody>
                    </x-table>
                </x-card>
            </div>
        </div>

        @if(count($deadStockProducts) > 0)
        <div class="row g-3 mb-4">
            <div class="col-12">
                <x-card class="shadow-sm border-0">
                    <x-slot:header><h6 class="mb-0 fw-bold">Dead Stock (> 30 hari tidak bergerak)</h6></x-slot:header>
                    <x-table class="table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>SKU</th><th>Product</th><th>Warehouse</th><th class="text-end">On Hand</th><th class="text-end">Aging (hari)</th></tr>
                        </thead>
                        <tbody>
                            @foreach($deadStockProducts as $ds)
                                <tr>
                                    <td><code>{{ $ds->sku }}</code></td>
                                    <td>{{ $ds->name }}</td>
                                    <td>{{ $ds->warehouse }}</td>
                                    <td class="text-end">{{ $fmtNum($ds->on_hand) }}</td>
                                    <td class="text-end"><x-badge color="danger">{{ $ds->aging_days }} hari</x-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                </x-card>
            </div>
        </div>
        @endif

        @endif

        @if($sectionVisible('procurement'))
        {{-- 3. PROCUREMENT DASHBOARD --}}
        <div class="section-divider"></div>
        <x-dashboard.section-header icon="ti ti-truck-delivery" title="Procurement Dashboard" subtitle="Monitoring PO, penerimaan barang, dan performa supplier." />

        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Total PO (Month)" :value="$fmtNum($totalPO)" icon="ti ti-file-invoice" iconColor="primary" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Pending PO" :value="$fmtNum($pendingPO)" trendType="{{ $pendingPO > 0 ? 'down' : 'up' }}" icon="ti ti-clock" iconColor="warning" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Received Today" :value="$fmtNum($receivedToday) . ' PO'" icon="ti ti-truck-loading" iconColor="success" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Total Suppliers" :value="$fmtNum($topSuppliers->count())" icon="ti ti-users-group" iconColor="info" />
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-5">
                <x-dashboard.chart-card title="PO by Status">
                    <div id="chart-procurement-status" style="height: 280px;"></div>
                </x-dashboard.chart-card>
            </div>
            <div class="col-xl-7">
                <x-card class="shadow-sm border-0 h-100">
                    <x-slot:header><h6 class="mb-0 fw-bold">Top Suppliers</h6></x-slot:header>
                    <x-table class="table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Supplier</th><th class="text-end">PO Count</th><th class="text-end">Total Purchase</th></tr>
                        </thead>
                        <tbody>
                            @forelse($topSuppliers as $s)
                                <tr>
                                    <td>{{ $s->name }}</td>
                                    <td class="text-end">{{ $s->po_count }}</td>
                                    <td class="text-end">{{ $fmtRp($s->total_purchase) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted text-center">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                    </x-table>
                </x-card>
            </div>
        </div>

        @endif

        @if($sectionVisible('warehouse'))
        {{-- 4. WAREHOUSE / WMS DASHBOARD --}}
        <div class="section-divider"></div>
        <x-dashboard.section-header icon="ti ti-building-warehouse" title="Warehouse / WMS Dashboard" subtitle="Aktivitas inbound, outbound, dan pergerakan stok hari ini." />

        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Inbound Today" :value="$fmtNum($inboundToday) . ' pcs'" :subtitle="$inboundTodayCount . ' movement(s)'" icon="ti ti-truck-loading" iconColor="primary" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Outbound Today" :value="$fmtNum($outboundToday) . ' pcs'" :subtitle="$outboundTodayCount . ' movement(s)'" icon="ti ti-package-export" iconColor="info" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Low Stock Alerts" :value="$lowStockCount" trendType="{{ $lowStockCount > 0 ? 'down' : 'up' }}" icon="ti ti-alert-triangle" iconColor="warning" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Dead Stock Alerts" :value="$deadStockAlertCount" trendType="{{ $deadStockAlertCount > 0 ? 'down' : 'up' }}" icon="ti ti-alert-circle" iconColor="danger" />
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-7">
                <x-dashboard.chart-card title="Inbound vs Outbound (Last 7 Days)">
                    <div id="chart-wms-inout" style="height: 280px;"></div>
                </x-dashboard.chart-card>
            </div>
            <div class="col-xl-5">
                <x-card class="shadow-sm border-0 h-100">
                    <x-slot:header><h6 class="mb-0 fw-bold">Recent Warehouse Activity</h6></x-slot:header>
                    <div style="max-height: 280px; overflow-y: auto;">
                        <ul class="list-unstyled mb-0 px-1">
                            @forelse($recentActivities as $act)
                                <li class="py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                    <div class="d-flex align-items-start">
                                        <div class="me-2 mt-1">
                                            @if($act->type === 'in')
                                                <i class="ti ti-arrow-down-circle text-success"></i>
                                            @else
                                                <i class="ti ti-arrow-up-circle text-primary"></i>
                                            @endif
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold small">{{ $act->product->name ?? '-' }}</div>
                                            <small class="text-muted">
                                                {{ ucfirst($act->type) }} · {{ $fmtNum($act->quantity) }} pcs ·
                                                {{ $act->stockMutationType->name ?? '' }} ·
                                                {{ $act->created_at->format('H:i') }}
                                            </small>
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="py-3 text-muted text-center small">Belum ada aktivitas hari ini</li>
                            @endforelse
                        </ul>
                    </div>
                </x-card>
            </div>
        </div>

        @endif

        @if($sectionVisible('outlet_operations'))
        {{-- 5. OUTLET OPERATIONS --}}
        <div class="section-divider"></div>
        <x-dashboard.section-header icon="ti ti-building-store" title="Outlet Operations" subtitle="Penjualan per outlet, peak hours, refund & void." />

        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Active Outlets" :value="$fmtNum($salesPerOutlet->count())" icon="ti ti-building-store" iconColor="primary" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Avg Revenue / Outlet" :value="$salesPerOutlet->count() > 0 ? $fmtRp($salesPerOutlet->avg('revenue')) : 'Rp 0'" icon="ti ti-chart-line" iconColor="success" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Refund Today" :value="$fmtNum($refundCount)" trendType="{{ $refundCount > 0 ? 'down' : 'neutral' }}" icon="ti ti-rotate" iconColor="warning" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Void Today" :value="$fmtNum($voidCount)" trendType="{{ $voidCount > 0 ? 'down' : 'neutral' }}" icon="ti ti-trash" iconColor="danger" />
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-7">
                <x-dashboard.chart-card title="Sales per Hour (Today)">
                    <div id="chart-outlet-hourly" style="height: 280px;"></div>
                </x-dashboard.chart-card>
            </div>
            <div class="col-xl-5">
                <x-card class="shadow-sm border-0 h-100">
                    <x-slot:header><h6 class="mb-0 fw-bold">Sales per Outlet (Month)</h6></x-slot:header>
                    <x-table class="table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Outlet</th><th class="text-end">Tx</th><th class="text-end">Revenue</th></tr>
                        </thead>
                        <tbody>
                            @forelse($salesPerOutlet as $o)
                                <tr>
                                    <td>{{ $o->name }}</td>
                                    <td class="text-end">{{ $fmtNum($o->transactions) }}</td>
                                    <td class="text-end">{{ $fmtRp($o->revenue) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted text-center">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                    </x-table>
                </x-card>
            </div>
        </div>

        @endif

        @if($sectionVisible('finance'))
        {{-- 6. FINANCE DASHBOARD --}}
        <div class="section-divider"></div>
        <x-dashboard.section-header icon="ti ti-report-money" title="Finance Dashboard" subtitle="Revenue, COGS, gross profit, dan margin bulan ini." />

        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Revenue" :value="$fmtRp($revenueThisMonth)" icon="ti ti-cash" iconColor="success" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="COGS" :value="$fmtRp($cogsThisMonth)" icon="ti ti-report-analytics" iconColor="danger" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Gross Profit" :value="$fmtRp($grossProfit)" trendType="{{ $grossProfit >= 0 ? 'up' : 'down' }}" icon="ti ti-pig-money" iconColor="success" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Profit Margin" :value="number_format($profitMargin, 1) . '%'" trendType="{{ $profitMargin >= 20 ? 'up' : ($profitMargin >= 0 ? 'neutral' : 'down') }}" icon="ti ti-percentage" iconColor="primary" />
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-6">
                <x-dashboard.chart-card title="Revenue vs COGS">
                    <div id="chart-finance-revenue" style="height: 280px;"></div>
                </x-dashboard.chart-card>
            </div>
            <div class="col-xl-6">
                <x-card class="shadow-sm border-0 h-100">
                    <x-slot:header><h6 class="mb-0 fw-bold">Top Profit Products</h6></x-slot:header>
                    <x-table class="table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Product</th><th class="text-end">Revenue</th><th class="text-end">COGS</th><th class="text-end">Profit</th></tr>
                        </thead>
                        <tbody>
                            @forelse($topProfitProducts as $fp)
                                <tr>
                                    <td class="text-truncate" style="max-width: 140px;">{{ $fp->name }}</td>
                                    <td class="text-end">{{ $fmtRp($fp->revenue) }}</td>
                                    <td class="text-end">{{ $fmtRp($fp->cogs) }}</td>
                                    <td class="text-end fw-bold text-success">{{ $fmtRp($fp->profit) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                    </x-table>
                </x-card>
            </div>
        </div>

        @endif

        @if($sectionVisible('customer'))
        {{-- 7. CUSTOMER DASHBOARD --}}
        <div class="section-divider"></div>
        <x-dashboard.section-header icon="ti ti-users" title="Customer Dashboard" subtitle="Analisis pelanggan: total, new vs returning, dan top customers." />

        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Total Customers" :value="$fmtNum($totalCustomers)" icon="ti ti-users" iconColor="primary" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="New (This Month)" :value="$fmtNum($newCustomersMonth)" trendType="up" icon="ti ti-user-plus" iconColor="success" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Returning %" :value="$returningPct . '%'" icon="ti ti-refresh" iconColor="info" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Avg Purchase / Cust" :value="$fmtRp($avgPurchasePerCustomer)" icon="ti ti-wallet" iconColor="warning" />
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <x-card class="shadow-sm border-0">
                    <x-slot:header><h6 class="mb-0 fw-bold">Top Customers (This Month)</h6></x-slot:header>
                    <x-table class="table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>#</th><th>Customer</th><th class="text-end">Orders</th><th class="text-end">Revenue</th></tr>
                        </thead>
                        <tbody>
                            @forelse($topCustomers as $i => $tc)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $tc->name }}</td>
                                    <td class="text-end">{{ $fmtNum($tc->orders) }}</td>
                                    <td class="text-end">{{ $fmtRp($tc->revenue) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center">Belum ada data</td></tr>
                            @endforelse
                        </tbody>
                    </x-table>
                </x-card>
            </div>
        </div>

        @endif

        @if($sectionVisible('alerts') && ($lowStockCount > 0 || $deadStockAlertCount > 0 || $outOfStockCount > 0))
        {{-- 8. ALERT & MONITORING --}}
        <div class="section-divider"></div>
        <x-dashboard.section-header icon="ti ti-alert-triangle" title="Alert & Monitoring" subtitle="Notifikasi penting: low stock, dead stock, dan stok habis." />

        <div class="row g-3 mb-4">
            <div class="col-xl-4 col-md-6">
                <x-dashboard.kpi-card title="Low Stock Alert" :value="$lowStockCount" trendType="down" icon="ti ti-alert-triangle" iconColor="warning" />
            </div>
            <div class="col-xl-4 col-md-6">
                <x-dashboard.kpi-card title="Out of Stock" :value="$outOfStockCount" trendType="down" icon="ti ti-package-off" iconColor="danger" />
            </div>
            <div class="col-xl-4 col-md-6">
                <x-dashboard.kpi-card title="Dead Stock" :value="$deadStockAlertCount" trendType="down" icon="ti ti-alert-circle" iconColor="danger" />
            </div>
        </div>
        @endif

    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush

    @push('page-js')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        function pad(n) { return String(n).padStart(2, '0'); }
        function toYmd(d) {
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        }
        // Parse Y-m-d as local date (avoid UTC shift that can show previous day)
        function parseLocalYmd(ymd) {
            if (!ymd) return null;
            var p = String(ymd).split('-');
            if (p.length !== 3) return null;
            var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
            return isNaN(d.getTime()) ? null : d;
        }
        function buildDashboardUrl(branchId, dateFrom, dateTo, isCustomPeriod) {
            var params = new URLSearchParams();
            if (branchId) params.set('branch_id', branchId);
            if (isCustomPeriod && dateFrom && dateTo) {
                params.set('period_custom', '1');
                params.set('date_from', dateFrom);
                params.set('date_to', dateTo);
            }
            var qs = params.toString();
            return '{{ route("dashboard") }}' + (qs ? '?' + qs : '');
        }

        var rangeEl = document.getElementById('dashboard-period-range');
        // Source of truth = server (PHP), bukan jam browser
        var defaults = {
            from: rangeEl?.dataset.defaultFrom || '',
            to: rangeEl?.dataset.defaultTo || ''
        };
        var draftFrom = rangeEl?.dataset.dateFrom || defaults.from;
        var draftTo = rangeEl?.dataset.dateTo || defaults.to;
        var periodPicker = null;

        // ── Select2 inside modal ──
        $('#outlet-filter').select2({
            theme: 'bootstrap-5',
            allowClear: true,
            placeholder: 'Pilih Outlet',
            width: '100%',
            dropdownParent: $('#dashboardFilterModal'),
        });

        // ── Period range (default: tgl 1 bulan ini → hari ini) ──
        // Jangan prefill value HTML — flatpickr sering salah parse separator "—" jadi tanggal acak.
        if (rangeEl && typeof flatpickr !== 'undefined') {
            var fromDate = parseLocalYmd(draftFrom);
            var toDate = parseLocalYmd(draftTo);
            if (!fromDate || !toDate) {
                fromDate = parseLocalYmd(defaults.from);
                toDate = parseLocalYmd(defaults.to);
                draftFrom = defaults.from;
                draftTo = defaults.to;
            }

            rangeEl.value = '';
            periodPicker = flatpickr(rangeEl, {
                mode: 'range',
                dateFormat: 'd M Y',
                allowInput: false,
                maxDate: parseLocalYmd(defaults.to) || 'today',
                locale: { rangeSeparator: ' — ' },
                defaultDate: fromDate && toDate ? [fromDate, toDate] : null,
                onReady: function (selectedDates, _dateStr, instance) {
                    if (fromDate && toDate) {
                        instance.setDate([fromDate, toDate], false);
                    }
                },
                onChange: function (selectedDates) {
                    if (selectedDates.length === 2) {
                        draftFrom = toYmd(selectedDates[0]);
                        draftTo = toYmd(selectedDates[1]);
                        rangeEl.dataset.dateFrom = draftFrom;
                        rangeEl.dataset.dateTo = draftTo;
                    }
                }
            });
        }

        $('#btnApplyDashboardFilter').on('click', function () {
            var from = draftFrom || defaults.from;
            var to = draftTo || defaults.to;
            if (periodPicker && periodPicker.selectedDates.length === 2) {
                from = toYmd(periodPicker.selectedDates[0]);
                to = toYmd(periodPicker.selectedDates[1]);
            }
            // period_custom=1 → server pakai date_from/date_to; tanpa flag → default bulan ini
            var isCustom = from !== defaults.from || to !== defaults.to;
            window.location = buildDashboardUrl($('#outlet-filter').val(), from, to, isCustom);
        });

        // Reset: URL bersih → server hitung ulang 1 bulan ini → hari ini
        $('#btnResetDashboardFilter').on('click', function () {
            window.location = '{{ route("dashboard") }}';
        });

        const baseOpts = {
            chart: { fontFamily: 'inherit', toolbar: { show: false } },
            grid: { borderColor: '#f1f1f4', strokeDashArray: 3 },
            colors: ['#696cff', '#03c3ec', '#71dd37', '#ffab00', '#ff3e1d', '#8592a3'],
            tooltip: { theme: 'light' },
        };

        const fmtRp = (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(Number(v) || 0));

        function renderChart(selector, options) {
            var el = document.querySelector(selector);
            if (!el || typeof ApexCharts === 'undefined') {
                return null;
            }
            try {
                var chart = new ApexCharts(el, options);
                chart.render();
                return chart;
            } catch (err) {
                console.error('Dashboard chart failed:', selector, err);
                return null;
            }
        }

        // ── Executive: Monthly Sales Trend (Area) ──
        const monthlyData = @json($monthlySalesTrend);
        renderChart('#chart-executive-sales', {
            ...baseOpts,
            chart: { ...baseOpts.chart, type: 'area', height: 280 },
            series: [{ name: 'Revenue', data: monthlyData.map(d => Math.round(Number(d.revenue) || 0)) }],
            xaxis: { categories: monthlyData.map(d => d.month) },
            yaxis: { labels: { formatter: fmtRp } },
            stroke: { curve: 'smooth', width: 2 },
            markers: { size: 4, hover: { size: 6 } },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
            dataLabels: { enabled: false },
        });

        // ── Executive: Outlet Performance (Bar) ──
        const outletData = @json($salesPerOutlet);
        renderChart('#chart-executive-outlet', {
            ...baseOpts,
            chart: { ...baseOpts.chart, type: 'bar', height: 280 },
            series: [{ name: 'Revenue', data: outletData.map(d => Math.round(Number(d.revenue) || 0)) }],
            xaxis: { categories: outletData.map(d => d.name) },
            yaxis: { labels: { formatter: fmtRp } },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
            dataLabels: { enabled: false },
        });

        // ── Sales: Daily Trend (Area) ──
        const dailyData = @json($dailySalesTrend);
        renderChart('#chart-sales-daily', {
            ...baseOpts,
            chart: { ...baseOpts.chart, type: 'area', height: 280 },
            series: [
                { name: 'Revenue', data: dailyData.map(d => Math.round(Number(d.revenue) || 0)) },
                { name: 'Transactions', data: dailyData.map(d => Number(d.transactions) || 0) },
            ],
            xaxis: { categories: dailyData.map(d => d.date) },
            yaxis: [
                { title: { text: 'Revenue' }, labels: { formatter: fmtRp } },
                { opposite: true, title: { text: 'Transactions' }, labels: { formatter: (v) => Math.round(Number(v) || 0) } },
            ],
            stroke: { curve: 'smooth', width: 2 },
            markers: { size: 4, hover: { size: 6 } },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
            dataLabels: { enabled: false },
        });

        // ── Sales: Payment Methods (Donut) ──
        const payData = @json($paymentMethods);
        if (payData.length > 0) {
            renderChart('#chart-sales-payment', {
                ...baseOpts,
                chart: { ...baseOpts.chart, type: 'donut', height: 280 },
                series: payData.map(d => Math.round(Number(d.total) || 0)),
                labels: payData.map(d => d.name),
                legend: { position: 'bottom', fontSize: '12px' },
                dataLabels: { enabled: true, formatter: (v) => v.toFixed(1) + '%' },
            });
        }

        // ── Sales: Per Outlet (Horizontal Bar) ──
        if (outletData.length > 0) {
            renderChart('#chart-sales-outlet', {
                ...baseOpts,
                chart: { ...baseOpts.chart, type: 'bar', height: 240 },
                series: [{ name: 'Revenue', data: outletData.map(d => Math.round(Number(d.revenue) || 0)) }],
                xaxis: { categories: outletData.map(d => d.name) },
                yaxis: { labels: { formatter: fmtRp } },
                plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
                dataLabels: { enabled: false },
            });
        }

        // ── Inventory: Stock by Category (Bar) ──
        const catStock = @json($stockByCategory);
        if (catStock.length > 0) {
            renderChart('#chart-inventory-category', {
                ...baseOpts,
                chart: { ...baseOpts.chart, type: 'bar', height: 280 },
                series: [{ name: 'Qty', data: catStock.map(d => Math.round(Number(d.qty) || 0)) }],
                xaxis: { categories: catStock.map(d => d.category) },
                plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
                dataLabels: { enabled: false },
                colors: ['#03c3ec'],
            });
        }

        // ── Procurement: PO by Status (Donut) ──
        const poStatus = @json($poByStatus);
        if (poStatus.length > 0) {
            renderChart('#chart-procurement-status', {
                ...baseOpts,
                chart: { ...baseOpts.chart, type: 'donut', height: 280 },
                series: poStatus.map(d => Number(d.count) || 0),
                labels: poStatus.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
                legend: { position: 'bottom', fontSize: '12px' },
            });
        }

        // ── WMS: Inbound vs Outbound (Grouped Bar) ──
        const wmsData = @json($wmsWeeklyTrend);
        const wmsDates = [...new Set(wmsData.map(d => d.date))].sort();
        const wmsIn = wmsDates.map(dt => { const r = wmsData.find(d => d.date === dt && d.type === 'in'); return r ? Math.round(Number(r.qty) || 0) : 0; });
        const wmsOut = wmsDates.map(dt => { const r = wmsData.find(d => d.date === dt && d.type === 'out'); return r ? Math.round(Number(r.qty) || 0) : 0; });
        if (wmsDates.length > 0) {
            renderChart('#chart-wms-inout', {
                ...baseOpts,
                chart: { ...baseOpts.chart, type: 'bar', height: 280 },
                series: [{ name: 'Inbound', data: wmsIn }, { name: 'Outbound', data: wmsOut }],
                xaxis: { categories: wmsDates },
                plotOptions: { bar: { borderRadius: 3, columnWidth: '50%' } },
                dataLabels: { enabled: false },
                colors: ['#71dd37', '#696cff'],
            });
        }

        // ── Outlet: Hourly Sales (Bar) ──
        const hourlyData = @json($hourlySales);
        if (hourlyData.length > 0) {
            renderChart('#chart-outlet-hourly', {
                ...baseOpts,
                chart: { ...baseOpts.chart, type: 'bar', height: 280 },
                series: [{ name: 'Revenue', data: hourlyData.map(d => Math.round(Number(d.revenue) || 0)) }],
                xaxis: { categories: hourlyData.map(d => String(Math.round(Number(d.hour) || 0)).padStart(2, '0') + ':00') },
                yaxis: { labels: { formatter: fmtRp } },
                plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
                dataLabels: { enabled: false },
                colors: ['#ffab00'],
            });
        }

        // ── Finance: Revenue vs COGS (Grouped Bar Monthly) ──
        if (monthlyData.length > 0) {
            renderChart('#chart-finance-revenue', {
                ...baseOpts,
                chart: { ...baseOpts.chart, type: 'bar', height: 280 },
                series: [
                    { name: 'Revenue', data: monthlyData.map(d => Math.round(Number(d.revenue) || 0)) },
                ],
                xaxis: { categories: monthlyData.map(d => d.month) },
                yaxis: { labels: { formatter: fmtRp } },
                plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
                dataLabels: { enabled: false },
                colors: ['#71dd37'],
            });
        }
    });
    </script>
    @endpush

</x-app-layout>
