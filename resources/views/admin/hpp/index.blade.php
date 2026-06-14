<x-app-layout>
    @section('title', 'Laporan HPP | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Laporan'],
                ['label' => 'HPP (FIFO)', 'active' => true],
            ]"
        />

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">Total Nilai Persediaan (FIFO)</small>
                        <h4 class="mb-0 text-primary">Rp {{ number_format($totalInventoryValue, 2) }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h6 class="card-title mb-0">Layer Biaya Berjalan (sisa stok per layer)</h6></div>
            <div class="table-responsive text-nowrap">
                <table class="table">
                    <thead><tr><th>Tanggal</th><th>Produk / Varian</th><th>Lokasi</th><th class="text-end">Sisa Qty</th><th class="text-end">HPP/Unit</th><th class="text-end">Nilai</th><th>Sumber</th></tr></thead>
                    <tbody>
                        @forelse ($layers as $l)
                            <tr>
                                <td>{{ optional($l->effective_date)->format('d/m/Y') }}</td>
                                <td>{{ $l->variant?->display_name ?? $l->product?->name }}</td>
                                <td>{{ $l->branch?->name }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($l->quantity_remaining,4),'0'),'.') }}</td>
                                <td class="text-end">Rp {{ number_format($l->unit_cost,2) }}</td>
                                <td class="text-end">Rp {{ number_format($l->quantity_remaining * $l->unit_cost,2) }}</td>
                                <td><span class="badge bg-label-secondary">{{ $l->source_type }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada layer biaya.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="card-title mb-0">Histori Perubahan HPP</h6></div>
            <div class="table-responsive text-nowrap">
                <table class="table">
                    <thead><tr><th>Tanggal</th><th>Produk</th><th>Lokasi</th><th class="text-end">HPP</th><th>Metode</th><th>Referensi</th></tr></thead>
                    <tbody>
                        @forelse ($history as $h)
                            <tr>
                                <td>{{ optional($h->effective_date)->format('d/m/Y') }}</td>
                                <td>{{ $h->product?->name }}</td>
                                <td>{{ $h->branch?->name }}</td>
                                <td class="text-end">Rp {{ number_format($h->cost,2) }}</td>
                                <td><span class="badge bg-label-info">{{ strtoupper($h->cost_type) }}</span></td>
                                <td><span class="badge bg-label-secondary">{{ $h->reference_type }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada histori.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
