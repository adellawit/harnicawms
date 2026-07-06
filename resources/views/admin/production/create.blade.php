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

        @if ($boms->isEmpty())
            <x-alert type="warning">Belum ada BOM. <a href="{{ route('bom.create') }}">Buat resep dulu</a>.</x-alert>
        @else
        <form method="POST" action="{{ route('production.store') }}">
            @csrf
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Perintah Produksi</h5></div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Gudang Bahan Baku <span class="text-danger">*</span></label>
                            <select name="source_warehouse_id" id="sourceWarehouseId" class="form-select" required>
                                @foreach ($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(old('source_warehouse_id', optional($wip)->id) === $wh->id)>
                                        {{ $wh->code }} - {{ $wh->name }}
                                        @if($wh->warehouse_type_code) ({{ $wh->warehouse_type_code }}) @endif
                                        @if($wh->branch) - {{ $wh->branch->name }} @endif
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Bahan baku akan dikonsumsi dari gudang ini (FIFO).</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gudang Produk Jadi <span class="text-danger">*</span></label>
                            <select name="output_warehouse_id" id="outputWarehouseId" class="form-select" required>
                                @foreach ($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(old('output_warehouse_id', optional($fg)->id) === $wh->id)>
                                        {{ $wh->code }} - {{ $wh->name }}
                                        @if($wh->warehouse_type_code) ({{ $wh->warehouse_type_code }}) @endif
                                        @if($wh->branch) - {{ $wh->branch->name }} @endif
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Hasil produksi akan masuk ke gudang ini.</small>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Resep (BOM) <span class="text-danger">*</span></label>
                            <select name="bom_id" id="bomSelect" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                @foreach ($boms as $bom)
                                    <option value="{{ $bom->id }}" @selected(old('bom_id') === $bom->id)>
                                        {{ $bom->name }} → {{ $bom->variant?->display_name ?? $bom->product?->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Qty Produksi <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="planned_qty" id="plannedQty" class="form-control" value="{{ old('planned_qty', 1) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Satuan Produksi <span class="text-danger">*</span></label>
                            <input type="hidden" name="planned_unit_id" id="plannedUnitId" value="{{ old('planned_unit_id') }}">
                            <div id="plannedUnitOptions" class="d-flex flex-wrap gap-1">
                                <span class="text-muted small">Pilih BOM...</span>
                            </div>
                            <small class="text-muted" id="plannedUnitHint"></small>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Overhead (Rp)</label>
                            <input type="number" step="any" min="0" name="overhead_cost" class="form-control" value="{{ old('overhead_cost', 0) }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tgl Produksi</label>
                            <input type="date" name="production_date" class="form-control" value="{{ old('production_date', date('Y-m-d')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Expired Produk Jadi <span class="text-muted small">(FEFO)</span></label>
                            <input type="date" name="output_expiry_date" class="form-control" value="{{ old('output_expiry_date') }}">
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Catatan</label>
                            <input type="text" name="notes" class="form-control" placeholder="opsional" value="{{ old('notes') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4" id="bomPreview" style="display:none">
                <div class="card-header"><h5 class="card-title mb-0">Kebutuhan Bahan Baku — <span id="previewWarehouseName">-</span></h5></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Bahan</th><th class="text-end">Stok Tersedia</th><th class="text-end">Qty Dibutuhkan</th><th class="text-center">Status</th></tr></thead>
                        <tbody id="bomRows"></tbody>
                    </table>
                </div>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="complete" value="1" id="completeChk" checked>
                <label class="form-check-label" for="completeChk">Langsung selesaikan produksi (konsumsi bahan baku FIFO & hitung HPP produk jadi)</label>
            </div>
            <div id="bomStockWarn" class="d-none mb-3">
                <x-alert type="warning" class="mb-0" :dismissible="false">
                    Stok bahan baku di gudang sumber tidak mencukupi. Terima barang atau kurangi qty produksi sebelum melanjutkan.
                </x-alert>
            </div>

            <button type="button" class="btn btn-outline-info me-2" id="btnSimulateProduction" data-bs-toggle="modal" data-bs-target="#productionSimModal">
                <i class="ti ti-calculator me-1"></i> Simulasi Produksi
            </button>
            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Proses Produksi</button>
            <a href="{{ route('production.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>

        {{-- Modal Simulasi Produksi --}}
        <div class="modal fade" id="productionSimModal" tabindex="-1" aria-labelledby="productionSimModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="productionSimModalLabel">
                            <i class="ti ti-calculator me-1"></i> Simulasi Produksi
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="simFormError" class="alert alert-danger d-none mb-3"></div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Qty Produksi</label>
                                <input type="number" step="any" min="0.000001" id="simPlannedQty" class="form-control" value="1">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Satuan Produksi <span class="text-muted small">(hasil produk jadi)</span></label>
                                <div id="simUnitOptions" class="d-flex flex-wrap gap-2">
                                    <span class="text-muted small">Pilih BOM terlebih dahulu...</span>
                                </div>
                            </div>
                        </div>

                        <div id="simLoading" class="text-center py-4 d-none">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="text-muted mt-2 mb-0">Menghitung simulasi...</p>
                        </div>

                        <div id="simResults" class="d-none">
                            <div class="card mb-3 border-primary">
                                <div class="card-header bg-label-primary py-2">
                                    <strong>Hasil Produksi — <span id="simOutputProduct">-</span></strong>
                                </div>
                                <div class="card-body py-3">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <p class="mb-1 text-muted small">Qty Produksi</p>
                                            <p class="fw-semibold mb-0" id="simOutputQty">-</p>
                                        </div>
                                        <div class="col-md-4">
                                            <p class="mb-1 text-muted small">Setara Satuan BOM</p>
                                            <p class="fw-semibold mb-0" id="simOutputInBomUnit">-</p>
                                        </div>
                                        <div class="col-md-4">
                                            <p class="mb-1 text-muted small">Breakdown Kemasan</p>
                                            <p class="fw-semibold mb-0" id="simOutputBreakdown">-</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header py-2">
                                    <strong>Rantai Konversi Produk Jadi</strong>
                                </div>
                                <div class="card-body py-2">
                                    <div id="simConversionChain" class="d-flex flex-wrap gap-2"></div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                    <strong>Kebutuhan Bahan & Sisa Stok — <span id="simWarehouseName">-</span></strong>
                                    <span id="simStockBadge"></span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Bahan</th>
                                                <th class="text-end">Stok Awal</th>
                                                <th class="text-end">Dipakai</th>
                                                <th class="text-end">Sisa</th>
                                                <th>Breakdown Sisa</th>
                                            </tr>
                                        </thead>
                                        <tbody id="simMaterialRows"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-primary" id="btnApplySimQty">
                            <i class="ti ti-arrow-down me-1"></i> Terapkan Qty ke Form
                        </button>
                        <button type="button" class="btn btn-primary" id="btnRunSimulation">
                            <i class="ti ti-refresh me-1"></i> Hitung Ulang
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    @push('page-js')
    <script>
        const previewUrl = @json(route('production.bom-preview'));
        const simulateUrl = @json(route('production.simulate'));
        const BOM_CATALOG = @json($bomCatalog);
        let previewTimer = null;
        let plannedUnitId = document.getElementById('plannedUnitId')?.value || null;
        let simSelectedUnitId = null;
        let lastSimData = null;

        function currentBomMeta() {
            const bomId = document.getElementById('bomSelect')?.value;
            return BOM_CATALOG.find(b => b.id === bomId) || null;
        }

        function setPlannedUnit(unitId) {
            plannedUnitId = unitId;
            const hidden = document.getElementById('plannedUnitId');
            if (hidden) hidden.value = unitId || '';
            simSelectedUnitId = unitId;
        }

        function renderPlannedUnitOptions(units, selectedId, targetId, onSelect) {
            const container = document.getElementById(targetId);
            if (!container) return;

            if (!units || !units.length) {
                container.innerHTML = '<span class="text-muted small">Pilih BOM...</span>';
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
                    if (onSelect) onSelect(id);
                });
            });

            const meta = currentBomMeta();
            const hint = document.getElementById('plannedUnitHint');
            if (hint && meta) {
                hint.textContent = `Resep BOM: ${meta.output_quantity} ${meta.output_unit} per batch`;
            }
        }

        function syncBomUnits() {
            const meta = currentBomMeta();
            if (!meta) {
                renderPlannedUnitOptions([], null, 'plannedUnitOptions', () => renderBom());
                renderSimUnitOptions([], null);
                return;
            }
            const defaultId = plannedUnitId && meta.units.some(u => u.id === plannedUnitId)
                ? plannedUnitId
                : meta.output_unit_id;
            renderPlannedUnitOptions(meta.units, defaultId, 'plannedUnitOptions', () => renderBom());
            renderSimUnitOptions(meta.units, defaultId);
        }

        function setBomStockWarn(visible) {
            document.getElementById('bomStockWarn')?.classList.toggle('d-none', !visible);
        }

        function formatQty(n) {
            return (+n).toLocaleString('id-ID', { maximumFractionDigits: 4 });
        }

        function formatBreakdown(rows) {
            if (!rows || !rows.length) return '<span class="text-muted">-</span>';
            return rows.map(r => `<span class="badge bg-label-secondary me-1">${formatQty(r.qty)} ${r.label}</span>`).join('');
        }

        function getSimFormValues() {
            return {
                bomId: document.getElementById('bomSelect')?.value,
                sourceWarehouseId: document.getElementById('sourceWarehouseId')?.value,
                plannedQty: parseFloat(document.getElementById('simPlannedQty')?.value || document.getElementById('plannedQty')?.value || '0'),
                productionUnitId: plannedUnitId || simSelectedUnitId,
            };
        }

        function showSimError(message) {
            const el = document.getElementById('simFormError');
            if (!el) return;
            el.textContent = message;
            el.classList.toggle('d-none', !message);
        }

        function renderSimUnitOptions(units, selectedId) {
            const container = document.getElementById('simUnitOptions');
            if (!container) return;

            if (!units || !units.length) {
                container.innerHTML = '<span class="text-muted small">Tidak ada satuan tersedia.</span>';
                return;
            }

            container.innerHTML = units.map(u => `
                <label class="btn btn-sm ${u.id === selectedId ? 'btn-primary' : 'btn-outline-primary'} sim-unit-btn">
                    <input type="radio" name="sim_production_unit" value="${u.id}" class="d-none" ${u.id === selectedId ? 'checked' : ''}>
                    ${u.label}${u.hint ? ` <small class="opacity-75">(${u.hint})</small>` : ''}
                </label>
            `).join('');

            container.querySelectorAll('.sim-unit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const input = this.querySelector('input');
                    setPlannedUnit(input.value);
                    container.querySelectorAll('.sim-unit-btn').forEach(b => {
                        b.classList.remove('btn-primary');
                        b.classList.add('btn-outline-primary');
                    });
                    document.querySelectorAll('#plannedUnitOptions .planned-unit-btn').forEach(b => {
                        const match = b.querySelector('input')?.value === input.value;
                        b.classList.toggle('btn-primary', match);
                        b.classList.toggle('btn-outline-primary', !match);
                    });
                    this.classList.remove('btn-outline-primary');
                    this.classList.add('btn-primary');
                    renderBom();
                    runSimulation();
                });
            });
        }

        function renderSimulation(data) {
            lastSimData = data;
            document.getElementById('simResults')?.classList.remove('d-none');
            document.getElementById('simOutputProduct').textContent = data.output_product || '-';
            document.getElementById('simWarehouseName').textContent = data.warehouse_name || '-';

            document.getElementById('simOutputQty').textContent =
                `${formatQty(data.planned_qty)} ${data.production_unit}`;
            document.getElementById('simOutputInBomUnit').textContent =
                `${formatQty(data.planned_qty_in_output_unit)} ${data.output_unit}`;
            document.getElementById('simOutputBreakdown').innerHTML =
                formatBreakdown(data.output_breakdown);

            const chainEl = document.getElementById('simConversionChain');
            if (chainEl) {
                chainEl.innerHTML = (data.conversion_chain || []).map(c =>
                    `<span class="badge bg-label-info">${c.label}${c.hint ? ': ' + c.hint : ''}</span>`
                ).join('');
            }

            const badge = document.getElementById('simStockBadge');
            if (badge) {
                badge.innerHTML = data.all_sufficient
                    ? '<span class="badge bg-label-success">Stok Cukup</span>'
                    : '<span class="badge bg-label-danger">Stok Kurang</span>';
            }

            const tbody = document.getElementById('simMaterialRows');
            if (tbody) {
                tbody.innerHTML = (data.materials || []).map(m => `
                    <tr class="${m.sufficient ? '' : 'table-danger'}">
                        <td>${m.label}</td>
                        <td class="text-end">${formatQty(m.available)} ${m.unit}<br>
                            <small class="text-muted">${formatQty(m.available_smallest)} ${m.smallest_unit_label}</small></td>
                        <td class="text-end">${formatQty(m.needed)} ${m.unit}<br>
                            <small class="text-muted">${formatQty(m.needed_smallest)} ${m.smallest_unit_label}</small></td>
                        <td class="text-end fw-semibold">${formatQty(m.after)} ${m.unit}<br>
                            <small class="text-muted">${formatQty(m.after_smallest)} ${m.smallest_unit_label}</small></td>
                        <td>${formatBreakdown(m.remainder_breakdown)}</td>
                    </tr>
                `).join('');
            }
        }

        function runSimulation() {
            const { bomId, sourceWarehouseId, plannedQty, productionUnitId } = getSimFormValues();

            if (!bomId) {
                showSimError('Pilih resep (BOM) terlebih dahulu.');
                return;
            }
            if (!sourceWarehouseId) {
                showSimError('Pilih gudang bahan baku terlebih dahulu.');
                return;
            }
            if (!plannedQty || plannedQty <= 0) {
                showSimError('Qty produksi harus lebih dari 0.');
                return;
            }
            if (!productionUnitId) {
                showSimError('Pilih satuan produksi.');
                return;
            }

            showSimError('');
            document.getElementById('simLoading')?.classList.remove('d-none');
            document.getElementById('simResults')?.classList.add('d-none');

            const params = new URLSearchParams({
                bom_id: bomId,
                source_warehouse_id: sourceWarehouseId,
                planned_qty: plannedQty,
                production_unit_id: productionUnitId,
            });

            fetch(simulateUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(async r => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok) {
                    throw new Error(data.message || `Gagal menghitung simulasi (${r.status})`);
                }
                return data;
            })
            .then(data => {
                document.getElementById('simLoading')?.classList.add('d-none');
                const unitId = data.production_unit_id || plannedUnitId || data.output_units?.[0]?.id;
                setPlannedUnit(unitId);
                renderSimUnitOptions(data.output_units || [], unitId);
                document.querySelectorAll('#plannedUnitOptions .planned-unit-btn').forEach(b => {
                    const match = b.querySelector('input')?.value === unitId;
                    b.classList.toggle('btn-primary', match);
                    b.classList.toggle('btn-outline-primary', !match);
                });
                renderSimulation(data);
            })
            .catch(err => {
                document.getElementById('simLoading')?.classList.add('d-none');
                showSimError(err.message || 'Gagal menghitung simulasi.');
            });
        }

        function openSimulationModal() {
            const plannedQty = document.getElementById('plannedQty')?.value;
            const simQty = document.getElementById('simPlannedQty');
            if (simQty && plannedQty) {
                simQty.value = plannedQty;
            }

            const { bomId, sourceWarehouseId } = getSimFormValues();
            if (!bomId || !sourceWarehouseId) {
                showSimError('Lengkapi BOM dan gudang bahan baku di form utama terlebih dahulu.');
                document.getElementById('simResults')?.classList.add('d-none');
                document.getElementById('simLoading')?.classList.add('d-none');
                return;
            }

            const meta = currentBomMeta();
            if (meta) {
                renderSimUnitOptions(meta.units, plannedUnitId || meta.output_unit_id);
            }

            runSimulation();
        }

        function renderBom() {
            const bomId = document.getElementById('bomSelect').value;
            const qty = parseFloat(document.getElementById('plannedQty').value || '0');
            const sourceWarehouseId = document.getElementById('sourceWarehouseId').value;
            const box = document.getElementById('bomPreview');
            const rows = document.getElementById('bomRows');

            if (!bomId || !sourceWarehouseId || qty <= 0) {
                box.style.display = 'none';
                setBomStockWarn(false);
                document.getElementById('completeChk').disabled = false;
                return;
            }

            clearTimeout(previewTimer);
            previewTimer = setTimeout(function() {
                const params = new URLSearchParams({
                    bom_id: bomId,
                    source_warehouse_id: sourceWarehouseId,
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
                    document.getElementById('previewWarehouseName').textContent = data.warehouse_name || '-';
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
                    document.getElementById('completeChk').disabled = !allOk;
                    setBomStockWarn(!allOk);
                    box.style.display = '';
                })
                .catch(() => {
                    box.style.display = 'none';
                    setBomStockWarn(false);
                });
            }, 250);
        }

        document.getElementById('bomSelect')?.addEventListener('change', function() {
            syncBomUnits();
            renderBom();
        });
        document.getElementById('plannedQty')?.addEventListener('change', renderBom);
        document.getElementById('plannedQty')?.addEventListener('input', renderBom);
        document.getElementById('sourceWarehouseId')?.addEventListener('change', renderBom);
        document.getElementById('productionSimModal')?.addEventListener('show.bs.modal', openSimulationModal);
        document.getElementById('btnRunSimulation')?.addEventListener('click', runSimulation);
        document.getElementById('simPlannedQty')?.addEventListener('change', runSimulation);
        document.getElementById('btnApplySimQty')?.addEventListener('click', function() {
            if (!lastSimData) return;
            document.getElementById('plannedQty').value = lastSimData.planned_qty;
            setPlannedUnit(lastSimData.production_unit_id);
            syncBomUnits();
            renderBom();
        });
        syncBomUnits();
        renderBom();
    </script>
    @endpush
</x-app-layout>
