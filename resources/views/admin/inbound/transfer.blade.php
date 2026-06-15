<x-app-layout>
    @section('title', 'Pindah Gudang | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Inventory'],
                ['label' => 'Stok Masuk', 'url' => route('inbound.index')],
                ['label' => 'Pindah Gudang', 'active' => true],
            ]"
        />

        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Pindah Lokasi Gudang</h5></div>
            <div class="card-body">
                <p class="text-muted small mb-4">Memindahkan stok antar gudang (keluar dari gudang asal, masuk ke gudang tujuan dengan HPP yang sama).</p>
                <form method="POST" action="{{ route('inbound.transfer.store') }}" id="transferForm">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Produk / Varian <span class="text-danger">*</span></label>
                            <select name="product_variant_id" id="product_variant_id" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                @foreach ($variants as $v)
                                    <option value="{{ $v['id'] }}" @selected(old('product_variant_id', $prefillVariantId) === $v['id'])>
                                        {{ $v['label'] }} @if($v['nature'])[{{ $v['nature'] }}]@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dari Gudang <span class="text-danger">*</span></label>
                            <select name="from_branch_id" id="from_branch_id" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b['id'] }}" @selected(old('from_branch_id', $prefillFromBranchId) === $b['id'])>{{ $b['label'] }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted" id="from-stock-hint"></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ke Gudang <span class="text-danger">*</span></label>
                            <select name="to_branch_id" id="to_branch_id" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b['id'] }}" @selected(old('to_branch_id', $prefillToBranchId) === $b['id'])>{{ $b['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Qty <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="quantity" id="quantity" class="form-control" value="{{ old('quantity') }}" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Catatan</label>
                            <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="opsional">
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="ti ti-arrows-transfer-up me-1"></i> Pindahkan</button>
                        <a href="{{ route('inbound.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('page-js')
        <script>
            const stocksByVariant = @json($stocksByVariant);

            function updateFromStockHint() {
                const variantId = document.getElementById('product_variant_id').value;
                const fromBranchId = document.getElementById('from_branch_id').value;
                const hint = document.getElementById('from-stock-hint');
                const qtyInput = document.getElementById('quantity');

                if (!variantId || !fromBranchId) {
                    hint.textContent = '';
                    return;
                }

                const rows = stocksByVariant[variantId] || [];
                const row = rows.find(r => r.branch_id === fromBranchId);
                const available = row ? row.quantity : 0;
                hint.textContent = available > 0
                    ? 'Stok tersedia di gudang asal: ' + available
                    : 'Tidak ada stok di gudang asal.';
                qtyInput.max = available > 0 ? available : '';
            }

            document.getElementById('product_variant_id').addEventListener('change', updateFromStockHint);
            document.getElementById('from_branch_id').addEventListener('change', updateFromStockHint);
            updateFromStockHint();
        </script>
    @endpush
</x-app-layout>
