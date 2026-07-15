<x-app-layout>
    @section('title', $allocation->allocation_number.' | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Distribution'],
                ['label' => 'Marketing Allocation', 'url' => route('marketing-allocation.index')],
                ['label' => $allocation->allocation_number, 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-0">{{ $allocation->allocation_number }}</h5>
                    <p class="text-muted small mb-0 mt-1">Marketing stock allocation detail</p>
                </div>
                <span class="badge bg-label-success">Completed</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="text-muted small">Date</div>
                        <div class="fw-semibold">{{ optional($allocation->allocation_date)->format('d M Y') }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">From Warehouse</div>
                        <div class="fw-semibold">{{ $allocation->fromWarehouse?->name }}</div>
                        <div class="small text-muted">{{ $allocation->fromWarehouse?->code }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">To Warehouse</div>
                        <div class="fw-semibold">{{ $allocation->toWarehouse?->name }}</div>
                        <div class="small text-muted">{{ $allocation->toWarehouse?->code }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Notes</div>
                        <div>{{ $allocation->notes ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="card-title mb-0">Items</h5></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th>Unit</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Total Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($allocation->items as $item)
                            <tr>
                                <td>{{ $item->variant?->display_name ?? $item->product?->name ?? '-' }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>
                                <td>{{ $item->unit?->symbol ?? $item->unit?->name ?? '-' }}</td>
                                <td class="text-end">Rp {{ number_format((float) $item->unit_cost, 2, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format((float) $item->total_cost, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    @php
                        $grandTotal = $allocation->items->sum(fn ($i) => (float) $i->total_cost);
                    @endphp
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">Grand Total</th>
                            <th class="text-end">Rp {{ number_format($grandTotal, 2, ',', '.') }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('marketing-allocation.index') }}" class="btn btn-outline-secondary">Back to List</a>
            <a href="{{ route('marketing-allocation.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Create Allocation
            </a>
        </div>
    </div>
</x-app-layout>
