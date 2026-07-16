<x-app-layout>
    @section('title', 'Create Production Order | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

    @push('page-css')
        <style>
            #bomEmptyState { min-height: 120px; }
            #bomPreview table td,
            #bomPreview table th { vertical-align: middle; }
            .bom-meta-chip {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.25rem 0.65rem;
                border-radius: 0.375rem;
                background: rgba(var(--bs-primary-rgb), 0.08);
                color: var(--bs-primary);
                font-size: 0.8125rem;
            }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production Order', 'url' => route('production.index')],
                ['label' => 'Create', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </x-alert>
        @endif

        @if ($outputs->isEmpty())
            <x-alert type="warning" class="mb-3" :dismissible="false">
                No finished goods with an active BOM. <a href="{{ route('bom.create') }}">Create a Bill of Materials</a> first.
            </x-alert>
        @endif

        <form method="POST" action="{{ route('production.store') }}" id="productionForm" onsubmit="return confirm('Submit production order? Raw materials from the BOM will be deducted from warehouse stock immediately.')">
            @csrf

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Production Order</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="productSelect">Product <span class="text-danger">*</span></label>
                            <select name="product_variant_id" id="productSelect" class="form-select" required>
                                <option value="">-- Select product --</option>
                                @foreach ($outputs as $item)
                                    <option
                                        value="{{ $item['id'] }}"
                                        data-bom-name="{{ $item['bom_name'] }}"
                                        data-output-unit="{{ $item['output_unit'] }}"
                                        data-output-qty="{{ $item['output_quantity'] }}"
                                        @selected(old('product_variant_id') === $item['id'])
                                    >
                                        {{ $item['label'] }}
                                        @if ($item['output_unit'])
                                            ({{ $item['output_unit'] }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <div id="noBomWarning" class="d-none mt-2">
                                <x-alert type="warning" class="mb-0" :dismissible="false">
                                    This product has no BOM recipe. <a href="{{ route('bom.create') }}">Create a Bill of Materials first</a>.
                                </x-alert>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="plannedQty">Production Qty <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="planned_qty" id="plannedQty" class="form-control" value="{{ old('planned_qty', 1) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="plannedUnitSelect">Production Unit <span class="text-danger">*</span></label>
                            <input type="hidden" name="planned_unit_id" id="plannedUnitId" value="{{ old('planned_unit_id') }}">
                            <select id="plannedUnitSelect" class="form-select" disabled>
                                <option value="">-- Select product first --</option>
                            </select>
                            <small class="text-muted d-block mt-1" id="plannedUnitHint"></small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="productionDate">Production Date</label>
                            <input
                                type="text"
                                name="production_date"
                                id="productionDate"
                                class="form-control flatpickr-date"
                                placeholder="DD/MM/YYYY"
                                value="{{ old('production_date', date('d/m/Y')) }}"
                            >
                        </div>
                        <div class="col-md-9">
                            <label class="form-label" for="notes">Notes</label>
                            <input type="text" name="notes" id="notes" class="form-control" placeholder="Optional" value="{{ old('notes') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4" id="bomCard">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-0">BOM Materials</h5>
                        <small class="text-muted">Recipe quantities × production qty</small>
                    </div>
                    <div id="bomMeta" class="d-none">
                        <span class="bom-meta-chip" id="bomMetaChip">
                            <i class="ti ti-flask"></i>
                            <span id="bomMetaText">—</span>
                        </span>
                    </div>
                </div>

                <div id="bomEmptyState" class="card-body text-center text-muted d-flex flex-column align-items-center justify-content-center">
                    <i class="ti ti-package-off mb-2" style="font-size: 2rem;"></i>
                    <div class="fw-medium">Select a product</div>
                    <small>BOM materials will appear here, scaled by production qty.</small>
                </div>

                <div id="bomLoading" class="card-body text-center text-muted d-none">
                    <span class="spinner-border spinner-border-sm me-1"></span> Loading BOM materials...
                </div>

                <div id="bomPreview" class="d-none">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Material</th>
                                    <th class="text-end">Available</th>
                                    <th class="text-end">Required</th>
                                    <th class="text-center" style="width:120px;">Status</th>
                                </tr>
                            </thead>
                            <tbody id="bomRows"></tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="row g-2 text-center">
                            <div class="col-md-4">
                                <div class="text-muted small">Warehouse</div>
                                <div class="fw-semibold" id="bomWarehouse">—</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Materials</div>
                                <div class="fw-semibold" id="bomCount">0</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Stock Check</div>
                                <div class="fw-semibold" id="bomStockSummary">—</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @include('admin.production.partials.overhead-items')

            <div id="bomStockWarn" class="d-none mb-3">
                <x-alert type="warning" class="mb-0" :dismissible="false">
                    Insufficient raw material stock. Receive goods or reduce production qty before continuing.
                </x-alert>
            </div>

            <x-alert type="info" class="mb-3" :dismissible="false">
                On submit, BOM materials are deducted from stock immediately (e.g. Foredi: 1 Box FG = 4 Sachet RM). Finished goods are added later on Receiving.
            </x-alert>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary" id="btnSubmit" disabled>
                    <i class="ti ti-check me-1"></i> Submit
                </button>
                <a href="{{ route('production.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y', allowInput: true, disableMobile: true });
        </script>
        <script>
            (function () {
                const bomForProductUrl = @json(route('production.bom-for-product'));
                const previewUrl = @json(route('production.bom-preview'));
                const oldUnitId = @json(old('planned_unit_id'));

                let previewTimer = null;
                let plannedUnitId = oldUnitId || null;
                let currentBom = null;

                const $product = $('#productSelect');
                const $unitSelect = $('#plannedUnitSelect');
                const qtyInput = document.getElementById('plannedQty');
                const unitHidden = document.getElementById('plannedUnitId');
                const submitBtn = document.getElementById('btnSubmit');

                $product.select2({
                    placeholder: '-- Select product --',
                    allowClear: true,
                    width: '100%',
                });

                $unitSelect.select2({
                    placeholder: '-- Select unit --',
                    allowClear: false,
                    width: '100%',
                });

                function setPlannedUnit(unitId) {
                    plannedUnitId = unitId || null;
                    if (unitHidden) {
                        unitHidden.value = plannedUnitId || '';
                    }
                }

                function formatQty(n) {
                    const num = Number(n || 0);
                    return num.toLocaleString(undefined, {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 4,
                    });
                }

                function showState(state) {
                    document.getElementById('bomEmptyState')?.classList.toggle('d-none', state !== 'empty');
                    document.getElementById('bomLoading')?.classList.toggle('d-none', state !== 'loading');
                    document.getElementById('bomPreview')?.classList.toggle('d-none', state !== 'preview');
                    document.getElementById('bomMeta')?.classList.toggle('d-none', state === 'empty');
                }

                function setBomStockWarn(visible) {
                    document.getElementById('bomStockWarn')?.classList.toggle('d-none', !visible);
                }

                function resetUnitSelect() {
                    $unitSelect
                        .prop('disabled', true)
                        .empty()
                        .append('<option value="">-- Select product first --</option>')
                        .trigger('change');
                    setPlannedUnit(null);
                    document.getElementById('plannedUnitHint').textContent = '';
                }

                function renderUnitOptions(units, selectedId) {
                    $unitSelect.empty();

                    if (!units || !units.length) {
                        resetUnitSelect();
                        return;
                    }

                    // Largest unit = first in chain (default/base packaging unit).
                    const largestUnitId = units[0].id;
                    const sel = selectedId || largestUnitId;
                    units.forEach(function (u) {
                        const opt = new Option(u.label, u.id, u.id === sel, u.id === sel);
                        $unitSelect.append(opt);
                    });

                    $unitSelect.prop('disabled', false).val(sel).trigger('change');
                    setPlannedUnit(sel);

                    if (currentBom) {
                        document.getElementById('plannedUnitHint').textContent =
                            'BOM recipe: ' + currentBom.output_quantity + ' ' + (currentBom.output_unit || '') + ' per batch';
                        document.getElementById('bomMetaText').textContent =
                            currentBom.output_quantity + ' ' + (currentBom.output_unit || '') + ' / batch';
                    }
                }

                function clearBom() {
                    currentBom = null;
                    document.getElementById('bomRows').innerHTML = '';
                    document.getElementById('bomWarehouse').textContent = '—';
                    document.getElementById('bomCount').textContent = '0';
                    document.getElementById('bomStockSummary').textContent = '—';
                    document.getElementById('bomMetaText').textContent = '—';
                    showState('empty');
                    setBomStockWarn(false);
                    submitBtn.disabled = true;
                    resetUnitSelect();
                }

                function loadBomForProduct(variantId) {
                    document.getElementById('noBomWarning')?.classList.add('d-none');
                    submitBtn.disabled = true;
                    currentBom = null;

                    if (!variantId) {
                        clearBom();
                        return;
                    }

                    showState('loading');

                    fetch(bomForProductUrl + '?product_variant_id=' + encodeURIComponent(variantId), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    })
                        .then(async function (r) {
                            if (r.status === 404) {
                                document.getElementById('noBomWarning')?.classList.remove('d-none');
                                clearBom();
                                return null;
                            }
                            if (!r.ok) {
                                throw new Error('Failed to load BOM');
                            }
                            return r.json();
                        })
                        .then(function (data) {
                            if (!data) return;
                            currentBom = data;
                            // Prefer old input on validation fail; otherwise default to largest unit.
                            const largestUnitId = (data.units && data.units[0]) ? data.units[0].id : null;
                            renderUnitOptions(data.units, oldUnitId || largestUnitId);
                            renderBom();
                        })
                        .catch(function () {
                            clearBom();
                        });
                }

                function renderBom() {
                    const variantId = $product.val();
                    const qty = parseFloat(qtyInput.value || '0');

                    if (!variantId || !currentBom || !(qty > 0)) {
                        if (!variantId || !currentBom) {
                            showState('empty');
                        }
                        setBomStockWarn(false);
                        submitBtn.disabled = true;
                        return;
                    }

                    clearTimeout(previewTimer);
                    previewTimer = setTimeout(function () {
                        showState('loading');

                        const params = new URLSearchParams({
                            product_variant_id: variantId,
                            planned_qty: qty,
                        });
                        if (plannedUnitId) {
                            params.set('planned_unit_id', plannedUnitId);
                        }

                        fetch(previewUrl + '?' + params.toString(), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        })
                            .then(function (r) {
                                if (!r.ok) {
                                    throw new Error('Failed to preview BOM');
                                }
                                return r.json();
                            })
                            .then(function (data) {
                                const rows = document.getElementById('bomRows');
                                rows.innerHTML = '';

                                let allOk = true;
                                const items = data.items || [];

                                items.forEach(function (it) {
                                    const need = Number(it.qty || 0);
                                    const available = Number(it.available || 0);
                                    const ok = available >= need;
                                    if (!ok) allOk = false;
                                    const unit = it.unit ? ' ' + it.unit : '';

                                    rows.innerHTML +=
                                        '<tr class="' + (ok ? '' : 'table-danger') + '">' +
                                            '<td><div class="fw-medium">' + (it.label || '—') + '</div></td>' +
                                            '<td class="text-end">' + formatQty(available) + unit + '</td>' +
                                            '<td class="text-end fw-semibold">' + formatQty(need) + unit + '</td>' +
                                            '<td class="text-center">' +
                                                (ok
                                                    ? '<span class="badge bg-label-success">Sufficient</span>'
                                                    : '<span class="badge bg-label-danger">Short</span>') +
                                            '</td>' +
                                        '</tr>';
                                });

                                document.getElementById('bomWarehouse').textContent = data.warehouse_name || '—';
                                document.getElementById('bomCount').textContent = String(items.length);
                                document.getElementById('bomStockSummary').innerHTML = allOk
                                    ? '<span class="text-success">Ready</span>'
                                    : '<span class="text-danger">Not ready</span>';

                                submitBtn.disabled = !allOk || items.length === 0;
                                setBomStockWarn(!allOk && items.length > 0);
                                showState('preview');
                            })
                            .catch(function () {
                                showState('empty');
                                setBomStockWarn(false);
                                submitBtn.disabled = true;
                            });
                    }, 250);
                }

                $product.on('change', function () {
                    loadBomForProduct($(this).val());
                });

                $unitSelect.on('change', function () {
                    setPlannedUnit($(this).val());
                    if (currentBom) {
                        renderBom();
                    }
                });

                qtyInput?.addEventListener('input', renderBom);
                qtyInput?.addEventListener('change', renderBom);

                @if (old('product_variant_id'))
                    loadBomForProduct(@json(old('product_variant_id')));
                @endif
            })();
        </script>
    @endpush
</x-app-layout>
