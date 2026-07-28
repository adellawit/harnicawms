<x-app-layout>
    @section('title', 'Barcode Dispatch | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            title="Barcode Dispatch"
            subtitle="Lacak serial produk yang terjual ke Agent atau Reseller. Transaksi lama tanpa assignment serial tidak bisa direkonstruksi otomatis."
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Reporting'],
                ['label' => 'Sales & Transaction'],
                ['label' => 'Barcode Dispatch', 'active' => true],
            ]"
        />

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('reporting.barcode-dispatch.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Dari Tanggal</label>
                            <input
                                type="text"
                                name="date_from"
                                class="form-control flatpickr-date"
                                value="{{ \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') }}"
                            >
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Sampai Tanggal</label>
                            <input
                                type="text"
                                name="date_to"
                                class="form-control flatpickr-date"
                                value="{{ \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') }}"
                            >
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" class="form-select select2">
                                <option value="">Semua Branch</option>
                                @foreach($options['branches'] as $branch)
                                    <option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
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
                            <label class="form-label">Customer / Reseller</label>
                            <select name="customer_id" class="form-select select2">
                                <option value="">Semua Customer</option>
                                @foreach($options['customers'] as $customer)
                                    <option value="{{ $customer->id }}" @selected($filters['customer_id'] === $customer->id)>
                                        {{ $customer->code }} · {{ $customer->name }}
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
                            <label class="form-label">Serial</label>
                            <input
                                type="search"
                                name="serial"
                                class="form-control font-monospace"
                                value="{{ $filters['serial'] }}"
                                placeholder="Nomor serial"
                            >
                        </div>
                        <div class="col-xl-2 col-md-4">
                            <label class="form-label">Sales Order</label>
                            <input
                                type="search"
                                name="sales_number"
                                class="form-control"
                                value="{{ $filters['sales_number'] }}"
                                placeholder="Nomor transaksi"
                            >
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
                                href="{{ route('reporting.barcode-dispatch.export', request()->except('page')) }}"
                                class="btn btn-label-success"
                            >
                                <i class="ti ti-file-spreadsheet me-1"></i>Export Excel
                            </a>
                            <a href="{{ route('reporting.barcode-dispatch.index') }}" class="btn btn-label-secondary">
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
                <x-dashboard.kpi-card title="Serial Terkirim" :value="format_number($kpis['serials'], 0, true)" icon="ti ti-barcode" iconColor="primary" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Sales Order" :value="format_number($kpis['orders'], 0, true)" icon="ti ti-receipt" iconColor="info" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Product" :value="format_number($kpis['products'], 0, true)" icon="ti ti-package" iconColor="warning" />
            </div>
            <div class="col-xl-3 col-md-6">
                <x-dashboard.kpi-card title="Agent Tujuan" :value="format_number($kpis['agents'], 0, true)" icon="ti ti-users" iconColor="success" />
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Serial</th>
                            <th>Product</th>
                            <th>Unit</th>
                            <th>Sales Order</th>
                            <th>Customer</th>
                            <th>Agent Tujuan</th>
                            <th>Branch</th>
                            <th>Dispatch</th>
                            <th>Operator</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td class="font-monospace fw-semibold">{{ $row->serial_number }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $row->product_name }}</div>
                                    <small class="text-muted">
                                        {{ $row->product_code }}{{ $row->variant_sku ? ' · '.$row->variant_sku : '' }}
                                    </small>
                                </td>
                                <td>{{ $row->unit_symbol ?: $row->unit_name }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $row->sales_number }}</div>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($row->sales_date)->format('d/m/Y') }}</small>
                                </td>
                                <td>
                                    <div>{{ $row->customer_name }}</div>
                                    @if($row->reseller_code)
                                        <small class="text-info">Reseller {{ $row->reseller_code }}</small>
                                    @else
                                        <small class="text-primary">Agent langsung</small>
                                    @endif
                                </td>
                                <td>{{ $row->agent_code }} · {{ $row->agent_name }}</td>
                                <td>{{ $row->branch_name ?? '-' }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->dispatched_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    {{ trim(($row->scanned_by_first_name ?? '').' '.($row->scanned_by_last_name ?? '')) ?: '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    Belum ada barcode completed untuk filter yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($rows->hasPages())
                <div class="card-footer bg-transparent">
                    {{ $rows->links('pagination.bootstrap-compact') }}
                </div>
            @endif
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush

    @push('page-js')
        <script src="{{ asset('assets/js/barcode-tracking-report.js') }}"></script>
    @endpush
</x-app-layout>
