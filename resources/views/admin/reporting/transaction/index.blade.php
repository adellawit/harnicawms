<x-app-layout>
    @section('title', ($isSalesByCustomer ?? false) ? 'Sales by Customer | ' : 'Transaction Report | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        @if($isSalesByCustomer ?? false)
            <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
        @endif
    @endpush
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :title="($isSalesByCustomer ?? false) ? 'Sales by Customer' : 'Transaction Report'"
            :subtitle="($isSalesByCustomer ?? false)
                ? 'Laporan penjualan per pelanggan, termasuk top 10 customer berdasarkan nilai transaksi.'
                : 'Laporan daftar transaksi penjualan per periode, branch, status, dan metode pembayaran.'"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Reporting'],
                ['label' => ($isSalesByCustomer ?? false) ? 'Sales by Customer' : 'Transaction Report', 'active' => true],
            ]"
        />

        @php
            $fmtRp = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
            $currentRoute = request()->route()?->getName() ?? 'reporting.transaction.index';
        @endphp

        <div class="card mb-4 shadow-sm border-0">
            <div class="card-body">
                <form method="GET" action="{{ route($currentRoute) }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label small text-muted">Branch</label>
                            <select name="branch_id" class="form-select select2">
                                <option value="">All Branch</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" @selected($branchId === $b->id)>{{ $b->name }}</option>
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
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label small text-muted">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                @foreach($statuses as $s)
                                    <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($isSalesByCustomer ?? false)
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label small text-muted">Customer</label>
                                <select name="customer_id" class="form-select select2">
                                    <option value="">All Customer</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->customer_id }}" @selected(($customerId ?? null) === $customer->customer_id)>{{ $customer->customer_name ?: '-' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label small text-muted">Payment Method</label>
                                <select name="payment_method_id" class="form-select select2">
                                    <option value="">All Payment</option>
                                    @foreach($paymentMethods as $pm)
                                        <option value="{{ $pm->id }}" @selected($paymentMethodId === $pm->id)>{{ $pm->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label small text-muted">Per Page</label>
                            <select name="per_page" class="form-select">
                                @foreach([10, 20, 50, 100] as $pp)
                                    <option value="{{ $pp }}" @selected(($perPage ?? 20) == $pp)>{{ $pp }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6 d-flex gap-2 align-items-end">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="ti ti-search me-1"></i> Filter
                            </button>
                            <a href="{{ route($currentRoute) }}" class="btn btn-label-dark" title="Reset filter">
                                <i class="ti ti-x"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Total Transactions" :value="format_number($totalTransactions, 0, true)" icon="ti ti-receipt-2" iconColor="primary" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Total Amount" :value="$fmtRp($totalAmount)" icon="ti ti-cash" iconColor="success" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Paid" :value="format_number($totalPaid, 0, true)" icon="ti ti-check" iconColor="info" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Avg Transaction" :value="$fmtRp($avgTransaction)" icon="ti ti-calculator" iconColor="warning" />
            </div>
        </div>

        @if($isSalesByCustomer ?? false)
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <x-dashboard.chart-card title="Top 10 Customer by Value">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">
                                {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}
                                – {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
                                · berdasarkan total nilai transaksi
                            </small>
                            <span class="badge bg-label-primary">Top 10</span>
                        </div>
                        <div id="chart-top-customers-value" style="height: 360px;"></div>
                        @if(($topCustomersByValue ?? collect())->isEmpty())
                            <p class="text-center text-muted small mb-0 mt-2">Tidak ada data customer untuk filter yang dipilih.</p>
                        @endif
                    </x-dashboard.chart-card>
                </div>
            </div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold">{{ ($isSalesByCustomer ?? false) ? 'Sales by Customer List' : 'Transaction List' }}</h6>
                    <small class="text-muted">Total unpaid: {{ format_number($totalUnpaid, 0, true) }}</small>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Sales Number</th>
                            <th>Customer</th>
                            <th>Branch</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">Tax</th>
                            <th class="text-end">Discount</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $trx)
                            <tr>
                                <td>{{ optional($trx->sales_date)->format('d/m/Y') }}</td>
                                <td class="fw-semibold">{{ $trx->sales_number }}</td>
                                <td>{{ $trx->customer_name ?: 'Walk-in Customer' }}</td>
                                <td>{{ $trx->branch?->name ?? '-' }}</td>
                                <td>{{ $trx->methodPayment?->name ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-label-{{ $trx->status === 'completed' ? 'success' : ($trx->status === 'draft' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst($trx->status) }}
                                    </span>
                                    <span class="badge bg-label-{{ $trx->payment_status === 'paid' ? 'success' : 'danger' }}">
                                        {{ ucfirst((string) $trx->payment_status) }}
                                    </span>
                                </td>
                                <td class="text-end">{{ $fmtRp($trx->subtotal) }}</td>
                                <td class="text-end">{{ $fmtRp($trx->tax_amount) }}</td>
                                <td class="text-end">{{ $fmtRp(($trx->discount_amount ?? 0) + ($trx->item_discount_total ?? 0)) }}</td>
                                <td class="text-end fw-semibold">{{ $fmtRp($trx->total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No transaction data found for selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($transactions->total() > 0)
                <div class="card-footer bg-transparent border-top">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                        <p class="text-muted small mb-0">
                            Showing {{ $transactions->firstItem() ?? 0 }} to {{ $transactions->lastItem() ?? 0 }}
                            of {{ format_number($transactions->total(), 0, true) }} transactions
                        </p>
                        @if($transactions->hasPages())
                            {{ $transactions->withQueryString()->links('pagination.bootstrap-compact') }}
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
        @if($isSalesByCustomer ?? false)
            <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
        @endif
    @endpush
    @push('page-js')
        <script>
            $(document).ready(function () {
                $('.select2').select2({
                    theme: 'bootstrap-5',
                    allowClear: true,
                    placeholder: 'Select option',
                    width: '100%'
                });

                $('.flatpickr-date').flatpickr({
                    dateFormat: 'd/m/Y',
                    allowInput: true
                });
            });

            @if($isSalesByCustomer ?? false)
            document.addEventListener('DOMContentLoaded', function () {
                const topCustomers = @json($topCustomersChartData ?? []);

                const chartEl = document.querySelector('#chart-top-customers-value');
                if (!chartEl || topCustomers.length === 0) {
                    return;
                }

                const fmtRp = (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(v));
                const sorted = [...topCustomers].sort((a, b) => b.value - a.value);

                new ApexCharts(chartEl, {
                    chart: {
                        type: 'bar',
                        height: 360,
                        fontFamily: 'inherit',
                        toolbar: { show: false },
                    },
                    series: [{
                        name: 'Total Value',
                        data: sorted.map((d) => Math.round(d.value)),
                    }],
                    colors: ['#696cff'],
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            borderRadius: 4,
                            barHeight: '65%',
                            dataLabels: { position: 'right' },
                        },
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: (val) => fmtRp(val),
                        style: { fontSize: '11px' },
                        offsetX: 4,
                    },
                    xaxis: {
                        categories: sorted.map((d) => d.name),
                        labels: { formatter: fmtRp },
                    },
                    yaxis: {
                        labels: {
                            maxWidth: 220,
                            style: { fontSize: '12px' },
                        },
                    },
                    grid: { borderColor: '#f1f1f4', strokeDashArray: 3, padding: { right: 24 } },
                    tooltip: {
                        theme: 'light',
                        y: {
                            formatter: (val, opts) => {
                                const row = sorted[opts.dataPointIndex];
                                return fmtRp(val) + ' · ' + row.orders + ' transaksi';
                            },
                        },
                    },
                }).render();
            });
            @endif
        </script>
    @endpush
</x-app-layout>
