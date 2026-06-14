<x-app-layout>
    @section('title', 'Stok Masuk | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Inventory'],
                ['label' => 'Stok Masuk', 'url' => route('inbound.index')],
                ['label' => 'Tambah', 'active' => true],
            ]"
        />

        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Form Stok Masuk (membuat layer HPP / FIFO)</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('inbound.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Produk / Varian <span class="text-danger">*</span></label>
                            <select name="product_variant_id" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                @foreach ($variants as $v)
                                    <option value="{{ $v['id'] }}">{{ $v['label'] }} @if($v['nature'])[{{ $v['nature'] }}]@endif</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lokasi (Gudang) <span class="text-danger">*</span></label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b['id'] }}">{{ $b['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Qty <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="quantity" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">HPP / Unit (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0" name="unit_cost" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal</label>
                            <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan</label>
                            <input type="text" name="notes" class="form-control" placeholder="opsional">
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan</button>
                        <a href="{{ route('inbound.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
