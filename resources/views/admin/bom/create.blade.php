<x-app-layout>
    @section('title', 'Buat BOM | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production', 'url' => route('production.index')],
                ['label' => 'Bill of Materials', 'url' => route('bom.index')],
                ['label' => 'Buat', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <form method="POST" action="{{ route('bom.store') }}" id="bomForm">
            @csrf
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Produk Jadi (Output)</h5></div>
                <div class="card-body">
                    @if ($selected)
                        <input type="hidden" name="product_variant_id" id="productVariantId" value="{{ $selected->id }}">
                        <div class="d-flex align-items-center gap-3 p-3 rounded bg-label-primary mb-3">
                            <span class="avatar-initial rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
                                <i class="ti ti-box-seam fs-4 text-white"></i>
                            </span>
                            <div>
                                <div class="text-uppercase small text-muted mb-1">Produk Jadi</div>
                                <div class="fw-bold fs-5 mb-0">{{ $selected->display_name ?? $selected->product?->name }}</div>
                                @if ($selected->product?->defaultUnit)
                                    <div class="small text-muted">Satuan dasar: {{ $selected->product->defaultUnit->name }}</div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label">Produk Jadi <span class="text-danger">*</span></label>
                            <select name="product_variant_id" id="productVariantId" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                @foreach ($outputs as $v)
                                    <option value="{{ $v['id'] }}" @selected(old('product_variant_id') === $v['id'])>{{ $v['label'] }} @if($v['nature'])[{{ $v['nature'] }}]@endif</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="form-label">Nama Resep <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                            value="{{ old('name', $selected ? (($selected->display_name ?? $selected->product?->name) . ' - Resep Standar') : '') }}"
                            placeholder="mis. Jamu Sehat Herbal - Resep Standar">
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Komponen / Bahan Baku</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()"><i class="ti ti-plus me-1"></i> Tambah Bahan</button>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th style="width:35%">Bahan</th>
                                <th style="width:15%">Qty</th>
                                <th style="width:20%">Satuan</th>
                                <th class="text-end" style="width:12%">HPP Lama</th>
                                <th class="text-end" style="width:12%">HPP Baru</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="rows"></tbody>
                    </table>
                </div>
                <div class="card-footer bg-light">
                    <div class="row text-center">
                        <div class="col-md-6">
                            <div class="text-muted small">Jumlah Bahan</div>
                            <div class="fw-bold fs-5" id="summaryCount">0</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Estimasi Total HPP per Unit Produk</div>
                            <div class="fw-bold fs-5 text-primary" id="summaryTotal">Rp 0</div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan Resep</button>
            <a href="{{ route('bom.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>

    @push('page-js')
    <script>
        const COMPONENTS = @json($components);
        let idx = 0;

        function optionsHtml() {
            let h = '<option value="">-- Pilih bahan --</option>';
            COMPONENTS.forEach(c => {
                const nat = c.nature ? ' [' + c.nature + ']' : '';
                h += `<option value="${c.id}">${c.label}${nat}</option>`;
            });
            return h;
        }

        function unitOptionsHtml(comp, selectedId) {
            let h = '<option value="">-- Satuan --</option>';
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
            if (lamaEl) lamaEl.textContent = formatCurrency(oldCost);
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
            let total = 0;
            document.querySelectorAll('#rows tr').forEach(tr => {
                const costEl = tr.querySelector('.hpp-baru-value');
                const qtyInput = tr.querySelector('input[name$="[quantity]"]');
                total += parseFloat(costEl?.dataset.cost || '0') * parseFloat(qtyInput?.value || '0');
                count += 1;
            });
            document.getElementById('summaryCount').textContent = count;
            document.getElementById('summaryTotal').textContent = formatCurrency(total);
        }

        function addRow() {
            const rowIdx = idx;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><select name="components[${rowIdx}][variant_id]" class="form-select" required onchange="syncUnit(this, ${rowIdx})">${optionsHtml()}</select></td>
                <td><input type="number" step="any" min="0.000001" name="components[${rowIdx}][quantity]" class="form-control" required oninput="updateSummary()"></td>
                <td><select name="components[${rowIdx}][unit_id]" id="unit-${rowIdx}" class="form-select" required onchange="updateRowCost(${rowIdx})">${unitOptionsHtml(null)}</select></td>
                <td class="text-end"><span id="hpp-lama-${rowIdx}" class="hpp-lama-value">-</span></td>
                <td class="text-end"><span id="hpp-baru-${rowIdx}" class="hpp-baru-value" data-cost="0">-</span></td>
                <td><button type="button" class="btn btn-sm btn-icon btn-outline-danger" onclick="this.closest('tr').remove(); updateSummary();"><i class="ti ti-x"></i></button></td>`;
            document.getElementById('rows').appendChild(tr);
            idx++;
            updateSummary();
        }

        addRow();
    </script>
    @endpush
</x-app-layout>
