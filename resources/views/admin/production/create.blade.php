<x-app-layout>
    @section('title', 'Buat Produksi | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Produksi'],
                ['label' => 'Production Order', 'url' => route('production.index')],
                ['label' => 'Buat', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        @include('admin.partials.product-variant-select2')

        <form method="POST" action="{{ route('production.store') }}" id="productionForm">
            @csrf
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Perintah Produksi</h5></div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Produk Jadi <span class="text-danger">*</span></label>
                            <select name="product_variant_id" id="productSelect" class="form-select" required style="width:100%"></select>
                            <div id="noBomWarning" class="d-none mt-2">
                                <x-alert type="warning" class="mb-0" :dismissible="false">
                                    Produk ini belum punya resep (BOM). <a href="{{ route('bom.create') }}">Buat resep dulu</a>.
                                </x-alert>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Qty Produksi <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="planned_qty" id="plannedQty" class="form-control" value="{{ old('planned_qty', 1) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Satuan Produksi <span class="text-danger">*</span></label>
                            <input type="hidden" name="planned_unit_id" id="plannedUnitId" value="{{ old('planned_unit_id') }}">
                            <div id="plannedUnitOptions" class="d-flex flex-wrap gap-1">
                                <span class="text-muted small">Pilih produk...</span>
                            </div>
                            <small class="text-muted" id="plannedUnitHint"></small>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Overhead (Rp)</label>
                            <input type="number" step="any" min="0" name="overhead_cost" class="form-control" value="{{ old('overhead_cost', 0) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tgl Produksi</label>
                            <input type="date" name="production_date" class="form-control" value="{{ old('production_date', date('Y-m-d')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Expired Produk Jadi <span class="text-muted small">(FEFO)</span></label>
                            <input type="date" name="output_expiry_date" class="form-control" value="{{ old('output_expiry_date') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Catatan</label>
                            <input type="text" name="notes" class="form-control" placeholder="opsional" value="{{ old('notes') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4" id="bomPreview" style="display:none">
                <div class="card-header"><h5 class="card-title mb-0">Kebutuhan Bahan Baku</h5></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Bahan</th><th class="text-end">Stok Tersedia</th><th class="text-end">Qty Dibutuhkan</th><th class="text-center">Status</th></tr></thead>
                        <tbody id="bomRows"></tbody>
                    </table>
                </div>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="mark_pending_receiving" value="1" id="pendingReceivingChk">
                <label class="form-check-label" for="pendingReceivingChk">Tandai produksi langsung selesai (lewati draft, lanjut ke Receiving)</label>
            </div>
            <div id="bomStockWarn" class="d-none mb-3">
                <x-alert type="warning" class="mb-0" :dismissible="false">
                    Stok bahan baku tidak mencukupi. Terima barang atau kurangi qty produksi sebelum melanjutkan.
                </x-alert>
            </div>

            <button type="submit" class="btn btn-primary" id="btnSubmit" disabled><i class="ti ti-check me-1"></i> Proses Produksi</button>
            <a href="{{ route('production.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>

    @push('page-js')
    <script>
        const bomForProductUrl = @json(route('production.bom-for-product'));
        const previewUrl = @json(route('production.bom-preview'));
        let previewTimer = null;
        let plannedUnitId = document.getElementById('plannedUnitId')?.value || null;
        let currentBom = null;

        function setPlannedUnit(unitId) {
            plannedUnitId = unitId;
            const hidden = document.getElementById('plannedUnitId');
            if (hidden) hidden.value = unitId || '';
        }

        function renderPlannedUnitOptions(units, selectedId) {
            const container = document.getElementById('plannedUnitOptions');
            if (!container) return;

            if (!units || !units.length) {
                container.innerHTML = '<span class="text-muted small">Pilih produk...</span>';
                return;
            }

            const sel = selectedId || units[0]?.id;
            setPlannedUnit(sel);

            container.innerHTML = units.map(u => `
                <label class="btn btn-sm ${u.id === sel ? 'btn-primary' : 'btn-outline-primary'} planned-unit-btn">
                    <input type="radio" class="d-none" value="${u.id}" ${u.id === sel ? 'checked' : ''}>
                    ${u.label}
                </label>
            `).join('');

            container.querySelectorAll('.planned-unit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.querySelector('input').value;
                    container.querySelectorAll('.planned-unit-btn').forEach(b => {
                        b.classList.remove('btn-primary');
                        b.classList.add('btn-outline-primary');
                    });
                    this.classList.remove('btn-outline-primary');
                    this.classList.add('btn-primary');
                    setPlannedUnit(id);
                    renderBom();
                });
            });

            const hint = document.getElementById('plannedUnitHint');
            if (hint && currentBom) {
                hint.textContent = `Resep BOM: ${currentBom.output_quantity} ${currentBom.output_unit} per batch`;
            }
        }

        function setBomStockWarn(visible) {
            document.getElementById('bomStockWarn')?.classList.toggle('d-none', !visible);
        }

        function loadBomForProduct(variantId) {
            const noBomWarning = document.getElementById('noBomWarning');
            const box = document.getElementById('bomPreview');
            const submitBtn = document.getElementById('btnSubmit');

            noBomWarning.classList.add('d-none');
            box.style.display = 'none';
            submitBtn.disabled = true;
            currentBom = null;

            if (!variantId) {
                renderPlannedUnitOptions([], null);
                return;
            }

            fetch(bomForProductUrl + '?product_variant_id=' + encodeURIComponent(variantId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(async r => {
                if (r.status === 404) {
                    noBomWarning.classList.remove('d-none');
                    renderPlannedUnitOptions([], null);
                    return null;
                }
                return r.json();
            })
            .then(data => {
                if (!data) return;
                currentBom = data;
                renderPlannedUnitOptions(data.units, data.output_unit_id);
                renderBom();
            });
        }

        function renderBom() {
            const variantId = document.getElementById('productSelect').value;
            const qty = parseFloat(document.getElementById('plannedQty').value || '0');
            const box = document.getElementById('bomPreview');
            const rows = document.getElementById('bomRows');
            const submitBtn = document.getElementById('btnSubmit');

            if (!variantId || !currentBom || qty <= 0) {
                box.style.display = 'none';
                setBomStockWarn(false);
                submitBtn.disabled = true;
                return;
            }

            clearTimeout(previewTimer);
            previewTimer = setTimeout(function() {
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
                .then(r => r.json())
                .then(data => {
                    rows.innerHTML = '';
                    let allOk = true;
                    (data.items || []).forEach(it => {
                        const need = it.qty;
                        const ok = it.available >= need;
                        if (!ok) allOk = false;
                        const unit = it.unit ? ` ${it.unit}` : '';
                        rows.innerHTML += `<tr class="${ok ? '' : 'table-danger'}">
                            <td>${it.label}</td>
                            <td class="text-end">${(+it.available.toFixed(4))}${unit}</td>
                            <td class="text-end">${(+need.toFixed(4))}${unit}</td>
                            <td class="text-center">${ok
                                ? '<span class="badge bg-label-success">Cukup</span>'
                                : '<span class="badge bg-label-danger">Kurang</span>'}</td>
                        </tr>`;
                    });
                    submitBtn.disabled = !allOk;
                    setBomStockWarn(!allOk);
                    box.style.display = '';
                })
                .catch(() => {
                    box.style.display = 'none';
                    setBomStockWarn(false);
                    submitBtn.disabled = true;
                });
            }, 250);
        }

        window.initProductVariantSelect2($('#productSelect'), {
            nature: 'FINISHED_GOOD',
            placeholder: 'Cari produk jadi...',
            onSelect: function (data) {
                loadBomForProduct(data.id);
            },
        });

        document.getElementById('productSelect')?.addEventListener('change', function() {
            if (!this.value) loadBomForProduct(null);
        });
        document.getElementById('plannedQty')?.addEventListener('change', renderBom);
        document.getElementById('plannedQty')?.addEventListener('input', renderBom);
    </script>
    @endpush
</x-app-layout>
