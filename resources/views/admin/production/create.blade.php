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
            <x-alert type="warning" class="mb-3" id="bomStockWarn" style="display:none">
                Stok bahan baku di gudang sumber tidak mencukupi. Terima barang atau kurangi qty produksi sebelum melanjutkan.
            </x-alert>

            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Proses Produksi</button>
            <a href="{{ route('production.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
        @endif
    </div>

    @push('page-js')
    <script>
        const previewUrl = @json(route('production.bom-preview'));
        let previewTimer = null;

        function renderBom() {
            const bomId = document.getElementById('bomSelect').value;
            const qty = parseFloat(document.getElementById('plannedQty').value || '0');
            const sourceWarehouseId = document.getElementById('sourceWarehouseId').value;
            const box = document.getElementById('bomPreview');
            const rows = document.getElementById('bomRows');

            if (!bomId || !sourceWarehouseId || qty <= 0) {
                box.style.display = 'none';
                return;
            }

            clearTimeout(previewTimer);
            previewTimer = setTimeout(function() {
                const params = new URLSearchParams({
                    bom_id: bomId,
                    source_warehouse_id: sourceWarehouseId,
                    planned_qty: qty,
                });

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
                    document.getElementById('bomStockWarn').style.display = allOk ? 'none' : 'block';
                    box.style.display = '';
                })
                .catch(() => {
                    box.style.display = 'none';
                });
            }, 250);
        }

        document.getElementById('bomSelect')?.addEventListener('change', renderBom);
        document.getElementById('plannedQty')?.addEventListener('change', renderBom);
        document.getElementById('plannedQty')?.addEventListener('input', renderBom);
        document.getElementById('sourceWarehouseId')?.addEventListener('change', renderBom);
        renderBom();
    </script>
    @endpush
</x-app-layout>
