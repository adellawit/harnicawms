<x-app-layout>
    @section('title', 'Promotions | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
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
            <div class="card-datatable text-nowrap">
                <table class="table table-bordered" id="table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">No</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Buy</th>
                            <th>Get</th>
                            <th>Free WH</th>
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
                                    ≥ {{ rtrim(rtrim(number_format((float) $p->buy_min_qty, 4, '.', ''), '0'), '.') }}
                                    @if($p->buyVariant)
                                        of {{ $p->buyVariant->display_name ?? $p->buyVariant->sku }}
                                    @elseif($p->buyProduct)
                                        of {{ $p->buyProduct->name }}
                                    @endif
                                </td>
                                <td>
                                    {{ rtrim(rtrim(number_format((float) $p->get_qty, 4, '.', ''), '0'), '.') }}
                                    {{ $p->get_product_mode === 'same' ? '(same)' : ($p->getVariant?->display_name ?? $p->getProduct?->name) }}
                                </td>
                                <td>{{ $p->free_warehouse_type }}</td>
                                <td>
                                    @if($p->is_active)
                                        <span class="badge bg-label-success">Active</span>
                                    @else
                                        <span class="badge bg-label-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('promotions.edit', $p->id) }}" class="btn btn-sm btn-icon btn-text-secondary" title="Edit">
                                        <i class="ti ti-pencil"></i>
                                    </a>
                                    <a href="{{ route('promotions.show', $p->id) }}" class="btn btn-sm btn-icon btn-text-secondary" title="View">
                                        <i class="ti ti-eye"></i>
                                    </a>
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
                    language: { emptyTable: 'No promotions yet. Create a buy-X-get-Y rule to start.' },
                });
            });
        </script>
    @endpush
</x-app-layout>
