<x-app-layout>
    @section('title', 'Receive Production Output | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 72px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production Order', 'url' => route('production.index')],
                ['label' => $order->order_number, 'url' => route('production.show', $order->id)],
                ['label' => 'Receive', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </x-alert>
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>
        @endif

        @php
            $outputUnit = $order->outputUnit?->symbol ?? $order->outputUnit?->name ?? '';
            $productName = $order->variant?->display_name ?? $order->product?->name;
            $plannedQty = (float) $order->planned_qty;
            $qtyLevels = $order->product && $order->output_unit_id && $plannedQty > 0
                ? \App\Support\ProductionQuantityDisplay::qtyLevelBreakdown($order->product, $plannedQty, $order->output_unit_id)
                : [];
            $materialCost = (float) ($order->total_material_cost ?? 0);
            $overheadCost = (float) ($order->overhead_cost ?? 0);
        @endphp

        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h5 class="card-title mb-0">Receive Output</h5>
                        <span class="badge bg-label-warning">Pending Receiving</span>
                    </div>
                    <div class="fw-medium">{{ $productName }}</div>
                    <small class="text-muted">{{ $order->order_number }}</small>
                </div>
                <a href="{{ route('production.show', $order->id) }}" class="btn btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Back
                </a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Production Date</small>
                        <div class="fw-medium">{{ optional($order->production_date)->format('d M Y') ?: '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">RM Warehouse</small>
                        <div class="fw-medium">{{ $order->sourceWarehouse?->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">FG Warehouse</small>
                        <div class="fw-medium">{{ $order->outputWarehouse?->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Planned Qty</small>
                        <div class="fw-medium">
                            {{ format_number($plannedQty, 4, true) }}
                            @if ($outputUnit)<span class="text-muted">{{ $outputUnit }}</span>@endif
                        </div>
                        @if (count($qtyLevels) > 1)
                            <div class="small text-muted lh-sm mt-1">
                                @foreach ($qtyLevels as $level)
                                    @if (! $level['is_base'])
                                        <div>{{ format_number($level['qty'], 4, true) }} {{ $level['label'] }}</div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('production.receive.store', $order->id) }}" id="receiveForm"
            onsubmit="return confirm('Submit receive? Finished goods will be added to warehouse. Raw materials were already deducted on submit.')">
            @csrf

            <div class="row g-3 mb-4">
                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Actual Output</h6>
                            <small class="text-muted">Enter the quantity produced to receive into FG warehouse</small>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="actualQty">Actual Qty <span class="text-danger">*</span></label>
                                    <input
                                        type="number"
                                        step="any"
                                        min="0.000001"
                                        name="actual_qty"
                                        id="actualQty"
                                        class="form-control"
                                        value="{{ $defaultActualQty }}"
                                        required
                                    >
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="actualUnitId">Unit <span class="text-danger">*</span></label>
                                    <select name="actual_unit_id" id="actualUnitId" class="form-select select2" required>
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit['id'] }}" @selected($defaultUnitId === $unit['id'])>
                                                {{ $unit['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="outputExpiryDate">Expiry Date <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        name="output_expiry_date"
                                        id="outputExpiryDate"
                                        class="form-control flatpickr-date"
                                        placeholder="DD/MM/YYYY"
                                        value="{{ old('output_expiry_date') }}"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="border rounded p-3 mt-3 bg-label-primary bg-opacity-10">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <small class="text-muted text-uppercase fw-semibold">Qty Preview</small>
                                    <span class="badge bg-label-secondary" id="previewPrimary">—</span>
                                </div>
                                <div class="small text-muted lh-sm" id="previewLevels">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Cost Summary</h6>
                            <small class="text-muted">Based on materials already deducted</small>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Material Cost</span>
                                <span class="fw-medium">Rp {{ format_number($materialCost, 2, true) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Overhead</span>
                                <span class="fw-medium">Rp {{ format_number($overheadCost, 2, true) }}</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold">Total Cost</span>
                                <span class="fw-bold text-primary">Rp {{ format_number($materialCost + $overheadCost, 2, true) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <span class="text-muted">Est. Unit Cost</span>
                                <span class="fw-medium" id="estUnitCost">—</span>
                            </div>
                            <x-alert type="info" class="mt-3 mb-0" :dismissible="false">
                                <small class="mb-0">Raw materials were deducted when the production order was submitted. Receiving only adds finished goods stock.</small>
                            </x-alert>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 sticky-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-package me-1"></i> Receive
                </button>
                <a href="{{ route('production.show', $order->id) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

    @push('page-css')
        <style>
            .sticky-actions {
                position: sticky;
                bottom: 0;
                padding: 0.75rem 0;
                background: linear-gradient(180deg, transparent, var(--bs-body-bg) 35%);
                z-index: 2;
            }
        </style>
    @endpush

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            (function () {
                const unitFactors = @json($unitFactors);
                const units = @json(collect($units)->values());
                const totalCost = {{ (float) ($materialCost + $overheadCost) }};

                $('.select2').select2({ width: '100%', minimumResultsForSearch: 8 });
                $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y', allowInput: true, disableMobile: true });

                function formatQty(n) {
                    const num = Number(n || 0);
                    return num.toLocaleString(undefined, {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 4,
                    });
                }

                function formatRp(n) {
                    return 'Rp ' + Number(n || 0).toLocaleString(undefined, {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 2,
                    });
                }

                function actualInOutputUnit() {
                    const actualQty = parseFloat(document.getElementById('actualQty').value || '0');
                    const selectedUnitId = document.getElementById('actualUnitId').value;
                    const factor = unitFactors[selectedUnitId] ?? 1;
                    return actualQty * factor;
                }

                function recalcPreview() {
                    const actualQty = parseFloat(document.getElementById('actualQty').value || '0');
                    const selectedUnitId = document.getElementById('actualUnitId').value;
                    const selectedUnit = units.find(function (u) { return u.id === selectedUnitId; });
                    const actualOutput = actualInOutputUnit();

                    document.getElementById('previewPrimary').textContent = actualQty > 0
                        ? formatQty(actualQty) + ' ' + (selectedUnit?.label || '')
                        : '—';

                    const lines = [];
                    units.forEach(function (u) {
                        if (u.id === selectedUnitId) return;
                        const toOutput = unitFactors[u.id] ?? 1;
                        if (!toOutput) return;
                        const qtyInUnit = actualOutput / toOutput;
                        lines.push('<div>' + formatQty(qtyInUnit) + ' ' + u.label + '</div>');
                    });
                    document.getElementById('previewLevels').innerHTML = lines.length ? lines.join('') : '—';

                    const est = document.getElementById('estUnitCost');
                    if (actualOutput > 0 && totalCost > 0) {
                        est.textContent = formatRp(totalCost / actualOutput) + ' / output unit';
                    } else {
                        est.textContent = '—';
                    }
                }

                $('#actualUnitId').on('change', recalcPreview);
                document.getElementById('actualQty')?.addEventListener('input', recalcPreview);
                recalcPreview();
            })();
        </script>
    @endpush
</x-app-layout>
