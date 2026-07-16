<x-app-layout>
    @section('title', 'Create BOM | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production', 'url' => route('production.index')],
                ['label' => 'Bill of Materials', 'url' => route('bom.index')],
                ['label' => 'Create', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <form method="POST" action="{{ route('bom.store') }}" id="bomForm">
            @csrf

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Finished Good (Output)</h5>
                </div>
                <div class="card-body">
                    @if ($selected)
                        <input type="hidden" name="product_variant_id" id="productVariantId" value="{{ $selected->id }}">
                        <div class="d-flex align-items-center gap-3 p-3 rounded border bg-label-primary mb-3">
                            <span class="avatar-initial rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
                                <i class="ti ti-box-seam fs-4 text-white"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="text-uppercase small text-muted mb-1">Finished Good</div>
                                <div class="fw-bold fs-5 mb-0 text-truncate">{{ $selected->display_name ?? $selected->product?->name }}</div>
                                @if ($selected->product?->defaultUnit)
                                    <div class="small text-muted">Base unit: {{ $selected->product->defaultUnit->name }}</div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label" for="productVariantId">Finished Good <span class="text-danger">*</span></label>
                            <select name="product_variant_id" id="productVariantId" class="form-select" required>
                                <option value="">-- Select product --</option>
                                @foreach ($outputs as $v)
                                    <option value="{{ $v['id'] }}" @selected(old('product_variant_id') === $v['id'])>
                                        {{ $v['label'] }} @if($v['nature'])[{{ $v['nature'] }}]@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="form-label" for="bomName">Recipe Name <span class="text-danger">*</span></label>
                        <input type="text" id="bomName" name="name" class="form-control" required
                            value="{{ old('name', $selected ? (($selected->display_name ?? $selected->product?->name) . ' - Standard Recipe') : '') }}"
                            placeholder="e.g. Foredi FG - Standard Recipe">
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-0">Components / Raw Materials</h5>
                        <small class="text-muted">Add materials required to produce 1 output unit.</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()">
                        <i class="ti ti-plus me-1"></i>Add Component
                    </button>
                </div>
                <div class="px-3 pt-3">
                    <div class="alert alert-primary d-flex align-items-start gap-2 mb-0 py-2" role="alert">
                        <i class="ti ti-info-circle mt-1"></i>
                        <div class="small mb-0">
                            <strong>HPP Old</strong> = cost baseline saved on the BOM (previous snapshot).
                            <strong class="ms-1">HPP New</strong> = current FIFO cost from warehouse stock.
                            Difference helps you review material cost changes before producing.
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:32%">Component</th>
                                <th style="width:12%">Qty</th>
                                <th style="width:18%">Unit</th>
                                <th class="text-end" style="width:16%">
                                    HPP Old
                                    <i class="ti ti-help-circle text-muted" title="Baseline cost stored on this BOM"></i>
                                </th>
                                <th class="text-end" style="width:16%">
                                    HPP New
                                    <i class="ti ti-help-circle text-muted" title="Current FIFO unit cost from stock"></i>
                                </th>
                                <th style="width:6%"></th>
                            </tr>
                        </thead>
                        <tbody id="rows"></tbody>
                    </table>
                </div>
                <div class="card-footer bg-light">
                    <div class="row g-3 text-center">
                        <div class="col-md-4">
                            <div class="text-muted small">Components</div>
                            <div class="fw-bold fs-5" id="summaryCount">0</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Est. Total (HPP Old)</div>
                            <div class="fw-bold fs-5 text-muted" id="summaryTotalOld">Rp 0</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Est. Total (HPP New)</div>
                            <div class="fw-bold fs-5 text-primary" id="summaryTotal">Rp 0</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pb-3">
                <a href="{{ route('bom.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-floppy me-1"></i>Save BOM
                </button>
            </div>
        </form>
    </div>

    @push('page-js')
    <script>
        const COMPONENTS = @json($components);
        let idx = 0;

        function optionsHtml() {
            let h = '<option value="">-- Select component --</option>';
            COMPONENTS.forEach(c => {
                const nat = c.nature ? ' [' + c.nature + ']' : '';
                h += `<option value="${c.id}">${c.label}${nat}</option>`;
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

        function setRowCost(rowIdx, oldCost, newCost) {
            const lamaEl = document.getElementById('hpp-lama-' + rowIdx);
            const baruEl = document.getElementById('hpp-baru-' + rowIdx);
            if (lamaEl) {
                lamaEl.textContent = formatCurrency(oldCost);
                lamaEl.dataset.cost = oldCost || 0;
            }
            if (baruEl) {
                baruEl.textContent = formatCurrency(newCost);
                baruEl.dataset.cost = newCost || 0;
            }
            updateSummary();
        }

        function updateRowCost(rowIdx) {
            const compSel = document.querySelector(`select[name="components[${rowIdx}][variant_id]"]`);
            const unitSel = document.getElementById('unit-' + rowIdx);
            const comp = COMPONENTS.find(c => c.id === compSel?.value);
            const cost = (comp && unitSel?.value) ? (comp.costs[unitSel.value] ?? 0) : 0;
            // On create there is no saved baseline yet — both start from current FIFO.
            setRowCost(rowIdx, cost, cost);
        }

        function syncUnit(sel, rowIdx) {
            const comp = COMPONENTS.find(c => c.id === sel.value);
            const unitSel = document.getElementById('unit-' + rowIdx);
            if (unitSel) {
                unitSel.innerHTML = unitOptionsHtml(comp, null);
            }
            updateRowCost(rowIdx);
        }

        function updateSummary() {
            let count = 0;
            let totalOld = 0;
            let totalNew = 0;
            document.querySelectorAll('#rows tr').forEach(tr => {
                const oldEl = tr.querySelector('.hpp-lama-value');
                const newEl = tr.querySelector('.hpp-baru-value');
                const qtyInput = tr.querySelector('input[name$="[quantity]"]');
                const qty = parseFloat(qtyInput?.value || '0');
                totalOld += parseFloat(oldEl?.dataset.cost || '0') * qty;
                totalNew += parseFloat(newEl?.dataset.cost || '0') * qty;
                count += 1;
            });
            document.getElementById('summaryCount').textContent = count;
            document.getElementById('summaryTotalOld').textContent = formatCurrency(totalOld);
            document.getElementById('summaryTotal').textContent = formatCurrency(totalNew);
        }

        function addRow() {
            const rowIdx = idx;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><select name="components[${rowIdx}][variant_id]" class="form-select" required onchange="syncUnit(this, ${rowIdx})">${optionsHtml()}</select></td>
                <td><input type="number" step="any" min="0.000001" name="components[${rowIdx}][quantity]" class="form-control" required oninput="updateSummary()"></td>
                <td><select name="components[${rowIdx}][unit_id]" id="unit-${rowIdx}" class="form-select" required onchange="updateRowCost(${rowIdx})">${unitOptionsHtml(null)}</select></td>
                <td class="text-end"><span id="hpp-lama-${rowIdx}" class="hpp-lama-value text-muted" data-cost="0">—</span></td>
                <td class="text-end"><span id="hpp-baru-${rowIdx}" class="hpp-baru-value fw-semibold text-primary" data-cost="0">—</span></td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-icon btn-outline-danger" onclick="this.closest('tr').remove(); updateSummary();" title="Remove"><i class="ti ti-x"></i></button></td>`;
            document.getElementById('rows').appendChild(tr);
            idx++;
            updateSummary();
        }

        addRow();
    </script>
    @endpush
</x-app-layout>
