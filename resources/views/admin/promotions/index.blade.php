<x-app-layout>
    @section('title', 'Promotions | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <style>
            #table th { white-space: nowrap; vertical-align: middle; }        /* header tak pernah wrap */
            #table td { white-space: nowrap; vertical-align: middle; }
            #table td:nth-child(3),                                            /* Name: boleh wrap */
            #table td:nth-child(5) { white-space: normal; word-break: break-word; }  /* Detail: boleh wrap */
            #table td:nth-child(5) { min-width: 240px; }
            #table th:last-child, #table td:last-child { text-align: center; } /* Actions rata tengah */
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'CRM'],
                ['label' => 'Promotions', 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0">Promotions</h5>
                <a href="{{ route('promotions.create') }}" class="btn btn-primary">
                    <i class="ti ti-plus me-1"></i> Create Promotion
                </a>
            </div>
            <div class="card-datatable">
                <table class="table table-bordered align-middle" id="table" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">No</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Detail</th>
                            <th>Status</th>
                            <th style="width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($promotions as $i => $p)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <a href="{{ route('promotions.show', $p->id) }}" class="fw-semibold text-heading">{{ $p->code }}</a>
                                </td>
                                <td>{{ $p->name }}</td>
                                <td>
                                    @if (($p->promotion_type ?? 'product') === 'marketing')
                                        <span class="badge bg-label-info">Marketing</span>
                                    @else
                                        <span class="badge bg-label-primary">Product</span>
                                    @endif
                                </td>
                                <td>
                                    @if (($p->promotion_type ?? 'product') === 'marketing')
                                        @php
                                            $targetLabel = $p->target_type === 'agent' ? 'Agen' : ($p->target_type === 'reseller' ? 'Reseller' : 'Agen & Reseller');
                                            $specific = $p->targetReseller?->name ?? $p->targetAgent?->name;
                                            $syarat = $p->min_purchase_type === 'qty'
                                                ? 'min '.rtrim(rtrim(number_format((float) $p->min_purchase_value, 4, '.', ''), '0'), '.').' item'
                                                : 'min Rp '.number_format((float) $p->min_purchase_value, 0, ',', '.');
                                            $diskon = $p->discount_type === 'percent'
                                                ? rtrim(rtrim(number_format((float) $p->discount_value, 4, '.', ''), '0'), '.').'%'
                                                : 'Rp '.number_format((float) $p->discount_value, 0, ',', '.');
                                        @endphp
                                        <div class="small"><span class="text-muted">Target:</span> {{ $targetLabel }}@if($specific) · <strong>{{ $specific }}</strong>@endif</div>
                                        <div class="small"><span class="text-muted">Syarat:</span> {{ $syarat }} &nbsp;·&nbsp; <span class="text-muted">Diskon:</span> <strong>{{ $diskon }}</strong></div>
                                        @if($p->reactivates_reseller)
                                            <span class="badge bg-label-warning mt-1">Reaktivasi reseller</span>
                                        @endif
                                    @else
                                        <div class="small">
                                            <span class="text-muted">Beli:</span>
                                            ≥ {{ rtrim(rtrim(number_format((float) $p->buy_min_qty, 4, '.', ''), '0'), '.') }}
                                            @if($p->buyVariant) {{ $p->buyVariant->display_name ?? $p->buyVariant->sku }}
                                            @elseif($p->buyProduct) {{ $p->buyProduct->name }}
                                            @endif
                                        </div>
                                        <div class="small">
                                            <span class="text-muted">Gratis:</span>
                                            {{ rtrim(rtrim(number_format((float) $p->get_qty, 4, '.', ''), '0'), '.') }}
                                            {{ $p->get_product_mode === 'same' ? '(produk sama)' : ($p->getVariant?->display_name ?? $p->getProduct?->name) }}
                                            &nbsp;·&nbsp; <span class="text-muted">WH:</span> {{ $p->free_warehouse_type }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($p->is_active)
                                        <span class="badge bg-label-success">Active</span>
                                    @else
                                        <span class="badge bg-label-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <a href="{{ route('promotions.edit', $p->id) }}" class="btn btn-sm btn-icon btn-text-secondary" title="Edit">
                                            <i class="ti ti-pencil"></i>
                                        </a>
                                        <a href="{{ route('promotions.show', $p->id) }}" class="btn btn-sm btn-icon btn-text-secondary" title="View">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(function () {
                $('#table').DataTable({
                    order: [[1, 'asc']],
                    pageLength: 25,
                    autoWidth: false,
                    columnDefs: [
                        { targets: 0, width: '48px', className: 'text-center' },   // No
                        { targets: 3, width: '96px' },                            // Type
                        { targets: 5, width: '90px' },                            // Status
                        { targets: 6, width: '110px', orderable: false },         // Actions
                    ],
                    language: { emptyTable: 'No promotions yet. Create a buy-X-get-Y rule to start.' },
                });
            });
        </script>
    @endpush
</x-app-layout>
