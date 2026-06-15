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
                    <div class="alert alert-info d-flex align-items-center py-2" role="alert">
                        <i class="ti ti-arrow-right me-2"></i>
                        <span>Bahan baku diambil dari <strong>{{ optional($wip)->name ?? 'Gudang WIP' }}</strong>, produk jadi masuk ke <strong>{{ optional($fg)->name ?? 'Gudang Barang Jadi' }}</strong>.</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Resep (BOM) <span class="text-danger">*</span></label>
                            <select name="bom_id" id="bomSelect" class="form-select" required onchange="renderBom()">
                                <option value="">-- Pilih --</option>
                                @foreach ($boms as $bom)
                                    <option value="{{ $bom->id }}">{{ $bom->name }} → {{ $bom->variant?->display_name ?? $bom->product?->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Qty Produksi <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="planned_qty" id="plannedQty" class="form-control" value="1" required onchange="renderBom()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Overhead (Rp)</label>
                            <input type="number" step="any" min="0" name="overhead_cost" class="form-control" value="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tgl Produksi</label>
                            <input type="date" name="production_date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Expired Produk Jadi <span class="text-muted small">(FEFO)</span></label>
                            <input type="date" name="output_expiry_date" class="form-control">
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Catatan</label>
                            <input type="text" name="notes" class="form-control" placeholder="opsional">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4" id="bomPreview" style="display:none">
                <div class="card-header"><h5 class="card-title mb-0">Kebutuhan Bahan Baku</h5></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Bahan</th><th class="text-end">Qty Dibutuhkan</th></tr></thead>
                        <tbody id="bomRows"></tbody>
                    </table>
                </div>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="complete" value="1" id="completeChk" checked>
                <label class="form-check-label" for="completeChk">Langsung selesaikan produksi (konsumsi bahan baku FIFO & hitung HPP produk jadi)</label>
            </div>

            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Proses Produksi</button>
            <a href="{{ route('production.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
        @endif
    </div>

    @push('page-js')
    <script>
        const BOMS = @json($bomData);
        function renderBom() {
            const id = document.getElementById('bomSelect').value;
            const qty = parseFloat(document.getElementById('plannedQty').value || '0');
            const bom = BOMS.find(b => b.id === id);
            const box = document.getElementById('bomPreview');
            const rows = document.getElementById('bomRows');
            if (!bom) { box.style.display = 'none'; return; }
            const scale = bom.output_quantity > 0 ? (qty / bom.output_quantity) : qty;
            rows.innerHTML = '';
            bom.items.forEach(it => {
                const need = (it.qty * scale);
                rows.innerHTML += `<tr><td>${it.label}</td><td class="text-end">${(+need.toFixed(4))}</td></tr>`;
            });
            box.style.display = '';
        }
    </script>
    @endpush
</x-app-layout>
