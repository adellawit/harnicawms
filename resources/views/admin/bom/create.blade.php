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

        <form method="POST" action="{{ route('bom.store') }}">
            @csrf
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Produk Jadi (Output)</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Nama Resep <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="mis. Es Kopi Susu - Resep Standar">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Produk Jadi <span class="text-danger">*</span></label>
                            <select name="product_variant_id" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                @foreach ($outputs as $v)
                                    <option value="{{ $v['id'] }}">{{ $v['label'] }} @if($v['nature'])[{{ $v['nature'] }}]@endif</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Qty Output <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="output_quantity" class="form-control" value="1" required>
                        </div>
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
                        <thead><tr><th style="width:70%">Bahan</th><th>Qty (per output)</th><th></th></tr></thead>
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
        let idx = 0;
        function optionsHtml() {
            let h = '<option value="">-- Pilih bahan --</option>';
            COMPONENTS.forEach(c => {
                const nat = c.nature ? ' [' + c.nature + ']' : '';
                h += `<option value="${c.id}">${c.label}${nat}</option>`;
            });
            return h;
        }
        function addRow() {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><select name="components[${idx}][variant_id]" class="form-select" required>${optionsHtml()}</select></td>
                <td><input type="number" step="any" min="0.000001" name="components[${idx}][quantity]" class="form-control" required></td>
                <td><button type="button" class="btn btn-sm btn-icon btn-outline-danger" onclick="this.closest('tr').remove()"><i class="ti ti-x"></i></button></td>`;
            document.getElementById('rows').appendChild(tr);
            idx++;
        }
        addRow();
    </script>
    @endpush
</x-app-layout>
