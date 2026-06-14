<x-app-layout>
    @section('title', 'Detail BOM | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Produksi'],
                ['label' => 'Bill of Materials', 'url' => route('bom.index')],
                ['label' => $bom->name, 'active' => true],
            ]"
        />

        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4"><small class="text-muted">Nama Resep</small><div class="fw-medium">{{ $bom->name }}</div></div>
                    <div class="col-md-4"><small class="text-muted">Produk Jadi</small><div class="fw-medium">{{ $bom->variant?->display_name ?? $bom->product?->name }}</div></div>
                    <div class="col-md-4"><small class="text-muted">Qty Output</small><div class="fw-medium">{{ rtrim(rtrim(number_format($bom->output_quantity, 2), '0'), '.') }} {{ $bom->outputUnit?->code }}</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Komponen / Bahan Baku</h5></div>
            <div class="table-responsive text-nowrap">
                <table class="table">
                    <thead><tr><th>Bahan</th><th class="text-end">Qty per Output</th><th>Satuan</th></tr></thead>
                    <tbody>
                        @foreach ($bom->items as $item)
                            <tr>
                                <td>{{ $item->componentVariant?->display_name ?? $item->componentProduct?->name }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($item->quantity, 4), '0'), '.') }}</td>
                                <td>{{ $item->unit?->code }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            <a href="{{ route('production.create') }}" class="btn btn-primary"><i class="ti ti-tool me-1"></i> Buat Production Order</a>
            <a href="{{ route('bom.index') }}" class="btn btn-outline-secondary">Kembali</a>
        </div>
    </div>
</x-app-layout>
