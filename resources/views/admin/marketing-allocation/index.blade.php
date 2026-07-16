<x-app-layout>
    @section('title', 'Marketing Allocation | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasCreate = session('permissions.Marketing Allocation.is_create', true) == 1
                || session('permissions.Marketing Allocation.is_create', false) === true
                || true;
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Distribution'],
                ['label' => 'Marketing Allocation', 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0">Marketing Allocation</h5>
                @if ($hasCreate)
                    <a href="{{ route('marketing-allocation.create') }}" class="btn btn-primary">
                        <i class="ti ti-plus me-1"></i> Create Allocation
                    </a>
                @endif
            </div>
            <div class="card-datatable text-nowrap">
                <table class="table table-bordered" id="table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">No</th>
                            <th>Allocation No.</th>
                            <th>Date</th>
                            <th>From Warehouse</th>
                            <th>To Warehouse</th>
                            <th class="text-end">Items</th>
                            <th>Status</th>
                            <th style="width:90px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($allocations as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <a href="{{ route('marketing-allocation.show', $row->id) }}" class="fw-semibold text-heading">
                                        {{ $row->allocation_number }}
                                    </a>
                                </td>
                                <td data-order="{{ optional($row->allocation_date)->format('Y-m-d') }}">
                                    {{ optional($row->allocation_date)->format('d M Y') }}
                                </td>
                                <td>
                                    <div>{{ $row->fromWarehouse?->name ?? '-' }}</div>
                                    <small class="text-muted">{{ $row->fromWarehouse?->code }}</small>
                                </td>
                                <td>
                                    <div>{{ $row->toWarehouse?->name ?? '-' }}</div>
                                    <small class="text-muted">{{ $row->toWarehouse?->code }}</small>
                                </td>
                                <td class="text-end">{{ $row->items->count() }}</td>
                                <td>
                                    <span class="badge bg-label-success">Completed</span>
                                </td>
                                <td>
                                    <a href="{{ route('marketing-allocation.show', $row->id) }}"
                                       class="btn btn-sm btn-icon btn-text-secondary"
                                       title="View">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                        @endforelse
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
                    order: [[2, 'desc']],
                    pageLength: 25,
                    language: {
                        emptyTable: 'No marketing allocations yet. Create one to move stock from Product warehouse to Marketing warehouse.',
                    },
                });
            });
        </script>
    @endpush
</x-app-layout>
