<x-app-layout>
    @section('title', 'Buat BOM | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Produksi'],
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
                    <div class="row g-3">
                        @if ($selected)
                            <input type="hidden" name="product_variant_id" id="productVariantId" value="{{ $selected->id }}">
                            <div class="col-md-6">
                                <label class="form-label">Produk Jadi</label>
                                <input type="text" class="form-control" value="{{ $selected->display_name ?? $selected->product?->name }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama Resep <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required
                                    value="{{ old('name', ($selected->display_name ?? $selected->product?->name) . ' - Resep Standar') }}">
                            </div>
                        @else
                            <div class="col-md-6">
                                <label class="form-label">Nama Resep <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required value="{{ old('name') }}" placeholder="mis. Jamu Sehat Herbal - Resep Standar">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Produk Jadi <span class="text-danger">*</span></label>
                                <select name="product_variant_id" id="productVariantId" class="form-select" required>
                                    <option value="">-- Pilih --</option>
                                    @foreach ($outputs as $v)
                                        <option value="{{ $v['id'] }}" @selected(old('product_variant_id') === $v['id'])>{{ $v['label'] }} @if($v['nature'])[{{ $v['nature'] }}]@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-md-3">
                            <label class="form-label">Qty Resep <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="output_quantity" id="outputQty" class="form-control"
                                value="{{ old('output_quantity', 1) }}" required>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Satuan Output Resep <span class="text-danger">*</span></label>
                            <select name="output_unit_id" id="outputUnitId" class="form-select" required>
                                @forelse ($outputUnits as $u)
                                    <option value="{{ $u['id'] }}" @selected(old('output_unit_id', $selected?->product?->default_unit_id) === $u['id'])>
                                        {{ $u['label'] }}@if($u['hint']) — {{ $u['hint'] }}@endif
                                    </option>
                                @empty
                                    <option value="">-- Pilih produk jadi dulu --</option>
                                @endforelse
                            </select>
                        </div>
                    </div>
                    <p class="text-muted small mb-0 mt-2">
                        <i class="ti ti-info-circle me-1"></i>
                        Resep didefinisikan per <strong>qty + satuan output</strong> di atas (mis. <strong>1 Karton</strong>).
                        Saat produksi, qty akan dikonversi otomatis ke satuan ini.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Komponen / Bahan Baku</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()"><i class="ti ti-plus me-1"></i> Tambah Bahan</button>
                </div>
                <div class="card-body border-bottom bg-light" id="calcPanel">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="fw-semibold small"><i class="ti ti-calculator me-1"></i> Kalkulator Resep</span>
                        <button type="button" class="btn btn-sm btn-outline-info" id="btnCalcRow" disabled>
                            Hitung kebutuhan bahan (baris aktif)
                        </button>
                    </div>
                    <p class="text-muted small mb-0" id="calcHint">
                        Pilih bahan & satuan pada baris, lalu klik hitung. Sistem menghitung dari satuan terkecil FG → bahan (basis 1:1 sachet).
                    </p>
                    <div id="calcResult" class="alert alert-info small py-2 mt-2 d-none mb-0"></div>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th style="width:45%">Bahan</th>
                                <th style="width:20%">Qty</th>
                                <th style="width:25%">Satuan</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="rows"></tbody>
                    </table>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan Resep</button>
            <a href="{{ route('bom.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>

    @push('page-js')
    <script>
        const COMPONENTS = @json($components);
        const OUTPUT_UNITS_URL = @json(route('bom.output-units'));
        const CALCULATE_URL = @json(route('bom.calculate'));
        let idx = 0;
        let activeRow = 0;

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

        function syncUnit(sel, i) {
            const comp = COMPONENTS.find(c => c.id === sel.value);
            const unitSel = document.getElementById('unit-' + i);
            if (unitSel) {
                unitSel.innerHTML = unitOptionsHtml(comp, null);
            }
            activeRow = i;
            document.getElementById('btnCalcRow').disabled = !sel.value;
        }

        function loadOutputUnits(variantId) {
            const unitSel = document.getElementById('outputUnitId');
            if (!variantId) {
                unitSel.innerHTML = '<option value="">-- Pilih produk jadi dulu --</option>';
                return;
            }
            fetch(OUTPUT_UNITS_URL + '?' + new URLSearchParams({ product_variant_id: variantId }), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(r => r.json())
            .then(data => {
                unitSel.innerHTML = (data.units || []).map(u =>
                    `<option value="${u.id}" ${u.id === data.default_unit_id ? 'selected' : ''}>${u.label}${u.hint ? ' — ' + u.hint : ''}</option>`
                ).join('') || '<option value="">Tidak ada satuan</option>';
            })
            .catch(() => {});
        }

        function calculateRow() {
            const variantId = document.getElementById('productVariantId')?.value;
            const outputQty = parseFloat(document.getElementById('outputQty')?.value || '0');
            const outputUnitId = document.getElementById('outputUnitId')?.value;
            const compSel = document.querySelector(`select[name="components[${activeRow}][variant_id]"]`);
            const unitSel = document.getElementById('unit-' + activeRow);
            const qtyInput = document.querySelector(`input[name="components[${activeRow}][quantity]"]`);
            const resultEl = document.getElementById('calcResult');

            if (!variantId || !compSel?.value || !outputUnitId || outputQty <= 0) {
                resultEl.classList.remove('d-none');
                resultEl.textContent = 'Lengkapi produk jadi, qty output, dan pilih bahan pada baris.';
                return;
            }

            const params = new URLSearchParams({
                product_variant_id: variantId,
                output_qty: outputQty,
                output_unit_id: outputUnitId,
                component_variant_id: compSel.value,
                component_unit_id: unitSel?.value || '',
            });

            fetch(CALCULATE_URL + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(async r => {
                const data = await r.json();
                if (!r.ok) throw new Error(data.message || 'Gagal menghitung');
                return data;
            })
            .then(data => {
                if (qtyInput) qtyInput.value = data.suggested_qty;
                if (unitSel && data.component_unit_id) unitSel.value = data.component_unit_id;
                resultEl.classList.remove('d-none');
                resultEl.textContent = data.hint;
            })
            .catch(err => {
                resultEl.classList.remove('d-none');
                resultEl.textContent = err.message || 'Gagal menghitung';
            });
        }

        function addRow() {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><select name="components[${idx}][variant_id]" class="form-select" required onchange="syncUnit(this, ${idx})">${optionsHtml()}</select></td>
                <td><input type="number" step="any" min="0.000001" name="components[${idx}][quantity]" class="form-control" required></td>
                <td><select name="components[${idx}][unit_id]" id="unit-${idx}" class="form-select" required>${unitOptionsHtml(null)}</select></td>
                <td><button type="button" class="btn btn-sm btn-icon btn-outline-danger" onclick="this.closest('tr').remove()"><i class="ti ti-x"></i></button></td>`;
            document.getElementById('rows').appendChild(tr);
            activeRow = idx;
            idx++;
        }

        document.getElementById('productVariantId')?.addEventListener('change', function() {
            loadOutputUnits(this.value);
        });
        document.getElementById('btnCalcRow')?.addEventListener('click', calculateRow);

        addRow();
    </script>
    @endpush
</x-app-layout>
