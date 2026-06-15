<x-app-layout>
    @section('title', 'Bill of Materials | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Produksi'],
                ['label' => 'Bill of Materials', 'active' => true],
            ]"
        />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Resep Produksi (BOM) per Produk Jadi</h5>
                <small class="text-muted">Pilih produk jadi di bawah, lalu klik <strong>Input BOM</strong> untuk menyusun resepnya.</small>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Produk Jadi</th>
                            <th>Satuan</th>
                            <th class="text-center">Status Resep</th>
                            <th class="text-center">Jml Bahan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($variants as $variant)
                            @php $bom = $boms->get($variant->id); @endphp
                            <tr>
                                <td>{{ $variant->sku ?: $variant->product?->code ?: '-' }}</td>
                                <td>{{ $variant->display_name ?? $variant->product?->name }}</td>
                                <td>{{ $variant->product?->defaultUnit?->name ?? '-' }}</td>
                                <td class="text-center">
                                    @if ($bom)
                                        <span class="badge bg-label-success"><i class="ti ti-check me-1"></i>Sudah ada</span>
                                    @else
                                        <span class="badge bg-label-secondary">Belum ada</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $bom ? $bom->items->count() : '-' }}</td>
                                <td class="text-end">
                                    @if ($bom)
                                        <a href="{{ route('bom.show', $bom->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ti ti-eye me-1"></i> Lihat
                                        </a>
                                        <form method="POST" action="{{ route('bom.destroy', $bom->id) }}" class="d-inline" onsubmit="return confirm('Hapus resep produk ini?')">
                                            @csrf
                                            <button class="btn btn-sm btn-icon btn-outline-danger"><i class="ti ti-trash"></i></button>
                                        </form>
                                    @else
                                        <a href="{{ route('bom.create', ['product_variant_id' => $variant->id]) }}" class="btn btn-sm btn-primary">
                                            <i class="ti ti-plus me-1"></i> Input BOM
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada produk jadi (FINISHED_GOOD). Tambahkan produk dulu di menu Produk.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
