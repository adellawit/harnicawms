<x-app-layout>
    @section('title', 'Buat Pesanan | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Distribusi'],
                ['label' => 'Replenishment Order', 'url' => route('replenishment.index')],
                ['label' => 'Buat', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        @if ($agents->isEmpty())
            <x-alert type="warning">Belum ada Agen (branch) di bawah Distributor ini.</x-alert>
        @else
        <form method="POST" action="{{ route('replenishment.store') }}">
            @csrf
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Pesanan @if($distributor)<small class="text-muted">— Distributor: {{ $distributor->name }}</small>@endif</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Agen (Pemesan) <span class="text-danger">*</span></label>
                            <select name="agent_id" class="form-select" required>
                                <option value="">-- Pilih Agen --</option>
                                @foreach ($agents as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Catatan</label>
                            <input type="text" name="notes" class="form-control" placeholder="opsional">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Item Pesanan</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()"><i class="ti ti-plus me-1"></i> Tambah Item</button>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th style="width:55%">Produk</th><th>Qty</th><th>Harga Transfer (Rp)</th><th></th></tr></thead>
                        <tbody id="rows"></tbody>
                    </table>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan Pesanan</button>
            <a href="{{ route('replenishment.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
        @endif
    </div>

    @push('page-js')
    <script>
        const VARIANTS = @json($variants);
        let idx = 0;
        function optionsHtml() {
            let h = '<option value="">-- Pilih produk --</option>';
            VARIANTS.forEach(c => { const nat = c.nature ? ' ['+c.nature+']' : ''; h += `<option value="${c.id}">${c.label}${nat}</option>`; });
            return h;
        }
        function addRow() {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><select name="lines[${idx}][variant_id]" class="form-select" required>${optionsHtml()}</select></td>
                <td><input type="number" step="any" min="0.000001" name="lines[${idx}][qty]" class="form-control" required></td>
                <td><input type="number" step="any" min="0" name="lines[${idx}][unit_price]" class="form-control" required></td>
                <td><button type="button" class="btn btn-sm btn-icon btn-outline-danger" onclick="this.closest('tr').remove()"><i class="ti ti-x"></i></button></td>`;
            document.getElementById('rows').appendChild(tr);
            idx++;
        }
        addRow();
    </script>
    @endpush
</x-app-layout>
