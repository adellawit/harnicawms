<x-app-layout>
    @section('title', 'Edit BOM | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production', 'url' => route('production.index')],
                ['label' => 'Bill of Materials', 'url' => route('bom.index')],
                ['label' => 'Edit', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('bom.update', $bom->id) }}" id="bomForm">
            @csrf
            @method('PUT')

            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-1">Edit Bill of Materials</h5>
                        <small class="text-muted">Update recipe name and raw material components.</small>
                    </div>
                    <a href="{{ route('bom.show', $bom->id) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-eye me-1"></i>View Detail
                    </a>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-stretch">
                        <div class="col-lg-5">
                            <div class="bom-output-panel h-100">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="bom-output-icon">
                                        <i class="ti ti-box-seam"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-uppercase small text-muted mb-1">Finished Good</div>
                                        <div class="fw-bold fs-5 mb-0 text-truncate">
                                            {{ $bom->variant?->display_name ?? $bom->product?->name }}
                                        </div>
                                        <div class="small text-muted mt-1">
                                            Output:
                                            {{ rtrim(rtrim(number_format($bom->output_quantity ?? 1, 4), '0'), '.') }}
                                            {{ $bom->variant?->product?->defaultUnit?->symbol
                                                ?: ($bom->variant?->product?->defaultUnit?->name ?? 'unit') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <label class="form-label" for="bomName">Recipe Name <span class="text-danger">*</span></label>
                            <input type="text" id="bomName" name="name" class="form-control form-control-lg"
                                required value="{{ old('name', $bom->name) }}"
                                placeholder="e.g. Foredi FG - Standard Recipe">
                            <small class="text-muted">This name appears on the BOM list and production order.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="bom-stat-card">
                        <small class="text-muted d-block">Components</small>
                        <div class="bom-stat-value" id="summaryCount">0</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="bom-stat-card">
                        <small class="text-muted d-block">Total HPP Old</small>
                        <div class="bom-stat-value text-muted" id="summaryTotalOld">Rp 0</div>
                        <small class="text-muted">BOM baseline</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="bom-stat-card is-primary">
                        <small class="text-muted d-block">Total HPP New</small>
                        <div class="bom-stat-value text-primary" id="summaryTotal">Rp 0</div>
                        <small class="text-muted">Current FIFO</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="bom-stat-card">
                        <small class="text-muted d-block">Difference</small>
                        <div class="bom-stat-value" id="summaryDiff">Rp 0</div>
                        <small class="text-muted" id="summaryDiffLabel">No cost change</small>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-1">Components / Raw Materials</h5>
                        <small class="text-muted">
                            <strong>HPP Old</strong> = saved baseline ·
                            <strong>HPP New</strong> = current FIFO. Save updates the next baseline.
                        </small>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="btnAddComponent">
                        <i class="ti ti-plus me-1"></i>Add Component
                    </button>
                </div>
                <div class="card-body p-3">
                    <div id="componentList" class="bom-component-list"></div>
                    <div id="componentEmpty" class="bom-empty-state text-center text-muted py-5 d-none">
                        <i class="ti ti-packages d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
                        No components yet. Click <strong>Add Component</strong> to start.
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pb-4">
                <a href="{{ route('bom.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i>Back to List
                </a>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('bom.show', $bom->id) }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i>Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>

    @push('page-css')
    <style>
        .bom-output-panel {
            border: 1px solid rgba(var(--bs-primary-rgb), .18);
            background: rgba(var(--bs-primary-rgb), .04);
            border-radius: .6rem;
            padding: 1rem 1.1rem;
        }
        .bom-output-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--bs-primary);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }
        .bom-stat-card {
            border: 1px solid #e7e7e8;
            border-radius: .6rem;
            background: #fff;
            padding: .9rem 1rem;
            height: 100%;
        }
        .bom-stat-card.is-primary {
            border-color: rgba(var(--bs-primary-rgb), .35);
            box-shadow: 0 0 0 1px rgba(var(--bs-primary-rgb), .06);
        }
        .bom-stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1.3;
            margin-top: .15rem;
            font-variant-numeric: tabular-nums;
        }
        .bom-component-list {
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }
        .bom-component-card {
            border: 1px solid #e7e7e8;
            border-radius: .6rem;
            background: #fff;
            overflow: hidden;
        }
        .bom-component-card-main {
            display: grid;
            grid-template-columns: 40px minmax(0, 1.5fr) minmax(110px, .55fr) minmax(140px, .7fr);
            gap: .75rem 1rem;
            align-items: end;
            padding: 1rem;
        }
        .bom-component-card-costs {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
            border-top: 1px dashed #e7e7e8;
            background: rgba(0,0,0,.015);
            padding: .85rem 1rem;
        }
        .bom-cost-box .label {
            display: block;
            font-size: .72rem;
            color: #697a8d;
            margin-bottom: .2rem;
        }
        .bom-cost-box .value {
            font-weight: 650;
            font-variant-numeric: tabular-nums;
        }
        .bom-component-index {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #f2f3f5;
            color: #697a8d;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
            font-weight: 600;
            margin-bottom: .35rem;
        }
        .bom-empty-state {
            border: 1px dashed #d9dee3;
            border-radius: .6rem;
            background: #fafbfc;
        }
        @media (max-width: 991.98px) {
            .bom-component-card-main {
                grid-template-columns: 40px 1fr;
            }
            .bom-component-card-main .bom-field-qty,
            .bom-component-card-main .bom-field-unit {
                grid-column: 2;
            }
            .bom-component-card-costs {
                grid-template-columns: 1fr;
            }
        }
    </style>
    @endpush

    @push('page-js')
    <script>
        const COMPONENTS = @json($components);
        const PREFILL_ITEMS = @json($items);
        let idx = 0;

        function optionsHtml(selectedId) {
            let h = '<option value="">-- Select component --</option>';
            COMPONENTS.forEach(c => {
                const nat = c.nature ? ' [' + c.nature + ']' : '';
                const sel = selectedId && selectedId === c.id ? 'selected' : '';
                h += `<option value="${c.id}" ${sel}>${c.label}${nat}</option>`;
            });
            return h;
        }

        function unitOptionsHtml(comp, selectedId) {
            let h = '<option value="">-- Unit --</option>';
            if (comp) {
                comp.units.forEach(u => {
                    const sel = (selectedId && selectedId === u.id) || (!selectedId && u.id === comp.default_unit_id) ? 'selected' : '';
                    h += `<option value="${u.id}" ${sel}>${u.label}</option>`;
                });
            }
            return h;
        }

        function formatCurrency(v) {
            return 'Rp ' + Number(v || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
        }

        function reindexCards() {
            document.querySelectorAll('#componentList .bom-component-card').forEach((card, i) => {
                const badge = card.querySelector('.bom-component-index');
                if (badge) badge.textContent = String(i + 1);
            });
            const empty = document.getElementById('componentEmpty');
            const hasRows = document.querySelectorAll('#componentList .bom-component-card').length > 0;
            empty.classList.toggle('d-none', hasRows);
        }

        function setRowCost(rowIdx, oldCost, newCost) {
            const card = document.getElementById('comp-card-' + rowIdx);
            if (!card) return;

            const oldEl = card.querySelector('.hpp-lama-value');
            const newEl = card.querySelector('.hpp-baru-value');
            const diffEl = card.querySelector('.hpp-diff-value');
            const oldVal = Number(oldCost || 0);
            const newVal = Number(newCost || 0);
            const diff = newVal - oldVal;

            if (oldEl) {
                oldEl.textContent = formatCurrency(oldVal);
                oldEl.dataset.cost = oldVal;
            }
            if (newEl) {
                newEl.textContent = formatCurrency(newVal);
                newEl.dataset.cost = newVal;
            }
            if (diffEl) {
                diffEl.textContent = (diff > 0 ? '+' : '') + formatCurrency(diff);
                diffEl.classList.remove('text-danger', 'text-success', 'text-muted');
                if (diff > 0) diffEl.classList.add('text-danger');
                else if (diff < 0) diffEl.classList.add('text-success');
                else diffEl.classList.add('text-muted');
            }
            updateSummary();
        }

        function updateRowCost(rowIdx) {
            const card = document.getElementById('comp-card-' + rowIdx);
            if (!card) return;
            const compSel = card.querySelector('select[name$="[variant_id]"]');
            const unitSel = card.querySelector('select[name$="[unit_id]"]');
            const oldEl = card.querySelector('.hpp-lama-value');
            const comp = COMPONENTS.find(c => c.id === compSel?.value);
            const cost = (comp && unitSel?.value) ? (comp.costs[unitSel.value] ?? 0) : 0;
            const oldCost = parseFloat(oldEl?.dataset.cost || '0') || cost;
            setRowCost(rowIdx, oldCost, cost);
        }

        function syncUnit(sel, rowIdx) {
            const card = document.getElementById('comp-card-' + rowIdx);
            const comp = COMPONENTS.find(c => c.id === sel.value);
            const unitSel = card?.querySelector('select[name$="[unit_id]"]');
            const oldEl = card?.querySelector('.hpp-lama-value');
            if (oldEl) oldEl.dataset.cost = '0';
            if (unitSel) unitSel.innerHTML = unitOptionsHtml(comp, null);
            updateRowCost(rowIdx);
        }

        function updateSummary() {
            let count = 0;
            let totalOld = 0;
            let totalNew = 0;

            document.querySelectorAll('#componentList .bom-component-card').forEach(card => {
                const qty = parseFloat(card.querySelector('input[name$="[quantity]"]')?.value || '0');
                const oldCost = parseFloat(card.querySelector('.hpp-lama-value')?.dataset.cost || '0');
                const newCost = parseFloat(card.querySelector('.hpp-baru-value')?.dataset.cost || '0');
                totalOld += oldCost * qty;
                totalNew += newCost * qty;
                count += 1;
            });

            const diff = totalNew - totalOld;
            document.getElementById('summaryCount').textContent = count;
            document.getElementById('summaryTotalOld').textContent = formatCurrency(totalOld);
            document.getElementById('summaryTotal').textContent = formatCurrency(totalNew);

            const diffEl = document.getElementById('summaryDiff');
            const diffLabel = document.getElementById('summaryDiffLabel');
            diffEl.textContent = (diff >= 0 ? '+' : '') + formatCurrency(diff);
            diffEl.classList.remove('text-danger', 'text-success', 'text-muted');
            if (diff > 0) {
                diffEl.classList.add('text-danger');
                diffLabel.textContent = 'Material cost increased';
            } else if (diff < 0) {
                diffEl.classList.add('text-success');
                diffLabel.textContent = 'Material cost decreased';
            } else {
                diffEl.classList.add('text-muted');
                diffLabel.textContent = 'No cost change';
            }

            reindexCards();
        }

        function removeCard(rowIdx) {
            const card = document.getElementById('comp-card-' + rowIdx);
            if (card) card.remove();
            updateSummary();
        }

        function addRow(prefill) {
            const rowIdx = idx;
            const card = document.createElement('div');
            card.className = 'bom-component-card';
            card.id = 'comp-card-' + rowIdx;
            card.innerHTML = `
                <div class="bom-component-card-main">
                    <div>
                        <div class="bom-component-index">${rowIdx + 1}</div>
                        <button type="button" class="btn btn-sm btn-icon btn-outline-danger" onclick="removeCard(${rowIdx})" title="Remove">
                            <i class="ti ti-trash"></i>
                        </button>
                    </div>
                    <div class="bom-field-comp">
                        <label class="form-label small mb-1">Component <span class="text-danger">*</span></label>
                        <select name="components[${rowIdx}][variant_id]" class="form-select" required
                            onchange="syncUnit(this, ${rowIdx})">${optionsHtml(prefill?.variant_id)}</select>
                    </div>
                    <div class="bom-field-qty">
                        <label class="form-label small mb-1">Qty <span class="text-danger">*</span></label>
                        <input type="number" step="any" min="0.000001" name="components[${rowIdx}][quantity]"
                            class="form-control" required value="${prefill?.quantity ?? ''}" oninput="updateSummary()">
                    </div>
                    <div class="bom-field-unit">
                        <label class="form-label small mb-1">Unit <span class="text-danger">*</span></label>
                        <select name="components[${rowIdx}][unit_id]" class="form-select" required
                            onchange="updateRowCost(${rowIdx})">${unitOptionsHtml(null)}</select>
                    </div>
                </div>
                <div class="bom-component-card-costs">
                    <div class="bom-cost-box">
                        <span class="label">HPP Old</span>
                        <div class="value text-muted hpp-lama-value" data-cost="0">—</div>
                    </div>
                    <div class="bom-cost-box">
                        <span class="label">HPP New</span>
                        <div class="value text-primary hpp-baru-value" data-cost="0">—</div>
                    </div>
                    <div class="bom-cost-box">
                        <span class="label">Diff (New − Old)</span>
                        <div class="value hpp-diff-value text-muted">—</div>
                    </div>
                </div>`;

            document.getElementById('componentList').appendChild(card);

            if (prefill && prefill.variant_id) {
                const comp = COMPONENTS.find(c => c.id === prefill.variant_id);
                const unitSel = card.querySelector('select[name$="[unit_id]"]');
                unitSel.innerHTML = unitOptionsHtml(comp, prefill.unit_id);
                setRowCost(rowIdx, prefill.old_cost, prefill.new_cost);
            }

            idx++;
            updateSummary();
        }

        document.getElementById('btnAddComponent').addEventListener('click', function () {
            addRow();
        });

        if (PREFILL_ITEMS.length) {
            PREFILL_ITEMS.forEach(item => addRow(item));
        } else {
            addRow();
        }
    </script>
    @endpush
</x-app-layout>
