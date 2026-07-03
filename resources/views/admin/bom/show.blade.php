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
                    <div class="col-md-3"><small class="text-muted">Nama Resep</small><div class="fw-medium">{{ $bom->name }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Produk Jadi</small><div class="fw-medium">{{ $bom->variant?->display_name ?? $bom->product?->name }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Output Resep</small>
                        <div class="fw-medium">
                            {{ rtrim(rtrim(number_format($bom->output_quantity, 4), '0'), '.') }}
                            {{ $bom->outputUnit?->symbol ?? $bom->outputUnit?->name ?? '-' }}
                        </div>
                    </div>
                    <div class="col-md-3"><small class="text-muted">Jumlah Bahan</small><div class="fw-medium">{{ $bom->items->count() }} bahan</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Komponen / Bahan Baku</h5></div>
            <div class="table-responsive text-nowrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bahan</th>
                            <th class="text-end">Qty per Output Resep</th>
                            <th>Satuan</th>
                            <th class="text-end">HPP Satuan</th>
                            <th class="text-end">Total Biaya</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bom->items as $item)
                            @php $cost = $itemCosts[$item->id] ?? ['unit_cost' => 0, 'line_total' => 0]; @endphp
                            <tr>
                                <td>{{ $item->componentVariant?->display_name ?? $item->componentProduct?->name }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($item->quantity, 4), '0'), '.') }}</td>
                                <td>{{ $item->unit?->name ?? $item->unit?->code }}</td>
                                <td class="text-end">
                                    @if ($cost['unit_cost'] > 0)
                                        Rp {{ number_format($cost['unit_cost'], 2, ',', '.') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end fw-medium">
                                    @if ($cost['line_total'] > 0)
                                        Rp {{ number_format($cost['line_total'], 2, ',', '.') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="4" class="text-end">Total HPP per {{ rtrim(rtrim(number_format($bom->output_quantity, 4), '0'), '.') }} {{ $bom->outputUnit?->symbol ?? $bom->outputUnit?->name ?? 'output' }}</td>
                            <td class="text-end text-primary">Rp {{ number_format($totalCost, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if ($totalCost == 0)
                <div class="card-footer text-muted small"><i class="ti ti-info-circle me-1"></i> HPP belum tersedia — pastikan bahan baku sudah ada stok masuk di Gudang WIP.</div>
            @endif
        </div>

        <div class="mt-3">
            <a href="{{ route('production.create') }}" class="btn btn-primary"><i class="ti ti-tool me-1"></i> Buat Production Order</a>
            <a href="{{ route('bom.index') }}" class="btn btn-outline-secondary">Kembali</a>
        </div>
    </div>
</x-app-layout>
