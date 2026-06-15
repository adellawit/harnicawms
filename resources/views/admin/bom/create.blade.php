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
                        @if ($selected)
                            {{-- Produk dipilih dari daftar: terkunci --}}
                            <input type="hidden" name="product_variant_id" value="{{ $selected->id }}">
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
                                <select name="product_variant_id" class="form-select" required>
                                    <option value="">-- Pilih --</option>
                                    @foreach ($outputs as $v)
                                        <option value="{{ $v['id'] }}">{{ $v['label'] }} @if($v['nature'])[{{ $v['nature'] }}]@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                    <p class="text-muted small mb-0 mt-2"><i class="ti ti-info-circle me-1"></i> Resep disusun <strong>per 1 produk jadi</strong>. Jumlah produksi diinput nanti di Production Order.</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Komponen / Bahan Baku</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()"><i class="ti ti-plus me-1"></i> Tambah Bahan</button>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th style="width:50%">Bahan</th><th style="width:20%">Qty (per 1 produk)</th><th style="width:25%">Satuan</th><th></th></tr></thead>
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
        // Satuan hanya yang di-set pada bahan tsb (default + konversi); default terpilih
        function unitOptionsHtml(comp) {
            let h = '<option value="">-- Satuan --</option>';
            if (comp) {
                comp.units.forEach(u => {
                    const sel = u.id === comp.default_unit_id ? 'selected' : '';
                    h += `<option value="${u.id}" ${sel}>${u.label}</option>`;
                });
            }
            return h;
        }
        // Saat bahan dipilih, isi ulang dropdown satuan sesuai bahan itu
        function syncUnit(sel, i) {
            const comp = COMPONENTS.find(c => c.id === sel.value);
            const unitSel = document.getElementById('unit-' + i);
            if (unitSel) {
                unitSel.innerHTML = unitOptionsHtml(comp);
            }
        }
        function addRow() {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><select name="components[${idx}][variant_id]" class="form-select" required onchange="syncUnit(this, ${idx})">${optionsHtml()}</select></td>
                <td><input type="number" step="any" min="0.000001" name="components[${idx}][quantity]" class="form-control" required></td>
                <td><select name="components[${idx}][unit_id]" id="unit-${idx}" class="form-select" required>${unitOptionsHtml(null)}</select></td>
                <td><button type="button" class="btn btn-sm btn-icon btn-outline-danger" onclick="this.closest('tr').remove()"><i class="ti ti-x"></i></button></td>`;
            document.getElementById('rows').appendChild(tr);
            idx++;
        }
        addRow();
    </script>
    @endpush
</x-app-layout>
