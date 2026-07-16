<x-app-layout>
    @section('title', 'Edit Production Order | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production Order', 'url' => route('production.index')],
                ['label' => $order->order_number, 'url' => route('production.show', $order->id)],
                ['label' => 'Edit', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <form method="POST" action="{{ route('production.update', $order->id) }}">
            @csrf
            @method('PUT')
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Production Order</h5></div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 p-3 rounded bg-label-primary mb-3">
                        <span class="avatar-initial rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
                            <i class="ti ti-box-seam fs-4 text-white"></i>
                        </span>
                        <div>
                            <div class="text-uppercase small text-muted mb-1">Finished Good</div>
                            <div class="fw-bold fs-5 mb-0">{{ $order->variant?->display_name ?? $order->product?->name }}</div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Production Qty <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="planned_qty" class="form-control" value="{{ old('planned_qty', (float) $order->planned_qty) }}" required>
                            <input type="hidden" name="planned_unit_id" value="{{ old('planned_unit_id', $order->output_unit_id) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="productionDate">Production Date</label>
                            <input
                                type="text"
                                name="production_date"
                                id="productionDate"
                                class="form-control flatpickr-date"
                                placeholder="DD/MM/YYYY"
                                value="{{ old('production_date', optional($order->production_date)->format('d/m/Y')) }}"
                            >
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" class="form-control" value="{{ old('notes', $order->notes) }}" placeholder="Optional">
                        </div>
                    </div>
                </div>
            </div>

            @include('admin.production.partials.overhead-items', ['existingOverheads' => $order->overheads])

            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Save Changes</button>
            <a href="{{ route('production.show', $order->id) }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y', allowInput: true, disableMobile: true });
        </script>
    @endpush
</x-app-layout>
