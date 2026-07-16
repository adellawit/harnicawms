<x-app-layout>
    @section('title', 'Create Marketing Allocation | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Distribution'],
                ['label' => 'Marketing Allocation', 'url' => route('marketing-allocation.index')],
                ['label' => 'Create', 'active' => true],
            ]"
        />

        @if (session('error'))
            <x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </x-alert>
        @endif

        @if (!$fromWarehouse || !$toWarehouse)
            <x-alert type="warning" class="mb-3" :dismissible="false">
                Product warehouse (FG) or Marketing warehouse is not configured yet.
            </x-alert>
            <a href="{{ route('marketing-allocation.index') }}" class="btn btn-outline-secondary">Back</a>
        @elseif (empty($stockLines))
            <x-alert type="warning" class="mb-3" :dismissible="false">
                No stock available in <strong>{{ $fromWarehouse->name }}</strong>.
            </x-alert>
            <a href="{{ route('marketing-allocation.index') }}" class="btn btn-outline-secondary">Back</a>
        @else
            @php
                $oldLines = collect(old('lines', []))->keyBy('variant_id');
            @endphp

            <form method="POST"
                  action="{{ route('marketing-allocation.store') }}"
                  id="allocationForm"
                  onsubmit="return confirmTransfer()">
                @csrf
                <input type="hidden" name="allocation_date" value="{{ old('allocation_date', date('Y-m-d')) }}">
                <input type="hidden" name="notes" value="{{ old('notes') }}">

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="card-title mb-0">Create Marketing Allocation</h5>
                            <small class="text-muted">
                                {{ $fromWarehouse->name }}
                                <i class="ti ti-arrow-right mx-1"></i>
                                {{ $toWarehouse->name }}
                                · qty in largest unit
                            </small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('marketing-allocation.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary" id="btnSubmit">
                                <i class="ti ti-arrows-transfer-up me-1"></i> Transfer Stock
                            </button>
                        </div>
                    </div>

                    <div class="card-datatable text-nowrap">
                        <table class="table table-bordered" id="allocationTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:50px;">No</th>
                                    <th>Product</th>
                                    <th class="text-end">Available</th>
                                    <th>Unit</th>
                                    <th class="text-end" style="width:180px;">Qty to Allocate</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($stockLines as $i => $line)
                                    @php
                                        $oldQty = $oldLines->get($line['variant_id'])['qty'] ?? '';
                                    @endphp
                                    <tr data-available="{{ $line['quantity'] }}">
                                        <td>{{ $i + 1 }}</td>
                                        <td>
                                            <div class="fw-semibold text-heading">{{ $line['label'] }}</div>
                                            <input type="hidden" name="lines[{{ $i }}][variant_id]" value="{{ $line['variant_id'] }}">
                                            <input type="hidden" name="lines[{{ $i }}][unit_id]" value="{{ $line['unit_id'] }}">
                                        </td>
                                        <td class="text-end">
                                            {{ rtrim(rtrim(number_format((float) $line['quantity'], 4, '.', ''), '0'), '.') }}
                                        </td>
                                        <td>{{ $line['unit_label'] ?: '—' }}</td>
                                        <td class="text-end">
                                            <input type="number"
                                                   step="any"
                                                   min="0"
                                                   max="{{ $line['quantity'] }}"
                                                   name="lines[{{ $i }}][qty]"
                                                   class="form-control form-control-sm text-end qty-input d-inline-block"
                                                   style="min-width:120px; max-width:160px;"
                                                   value="{{ $oldQty }}"
                                                   placeholder="0">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        @endif
    </div>

    @push('page-js')
        <script>
            function confirmTransfer() {
                const inputs = document.querySelectorAll('#allocationTable .qty-input');
                let hasQty = false;
                let overstock = false;

                inputs.forEach(function (input) {
                    const qty = parseFloat(input.value || '0');
                    if (qty > 0) {
                        hasQty = true;
                    }
                    const available = parseFloat(input.closest('tr')?.dataset.available || '0');
                    if (qty > available + 1e-9) {
                        overstock = true;
                        input.classList.add('is-invalid');
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });

                if (!hasQty) {
                    alert('Enter quantity for at least one product.');
                    return false;
                }

                if (overstock) {
                    alert('One or more quantities exceed available stock.');
                    return false;
                }

                inputs.forEach(function (input) {
                    const qty = parseFloat(input.value || '0');
                    if (!(qty > 0)) {
                        const row = input.closest('tr');
                        row.querySelectorAll('input').forEach(function (el) {
                            el.disabled = true;
                        });
                    }
                });

                return confirm('Transfer selected stock to Marketing warehouse now?');
            }
        </script>
    @endpush
</x-app-layout>
