<x-app-layout>
    @section('title', 'Stok Masuk | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Inventory'],
                ['label' => 'Stok Masuk', 'active' => true],
            ]"
        />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0">Layer Biaya FIFO Terbaru</h5>
                <div class="d-flex gap-2">
                    <a href="{{ route('inbound.transfer.create') }}" class="btn btn-outline-primary btn-sm">
                        <i class="ti ti-arrows-transfer-up me-1"></i> Pindah Gudang
                    </a>
                    <a href="{{ route('inbound.create') }}" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus me-1"></i> Stok Masuk
                    </a>
                </div>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Produk / Varian</th>
                            <th>Lokasi</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Sisa</th>
                            <th class="text-end">HPP / Unit</th>
                            <th>Expired</th>
                            <th>Sumber</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($layers as $layer)
                            <tr>
                                <td>{{ optional($layer->effective_date)->format('d/m/Y') }}</td>
                                <td>{{ $layer->variant?->display_name ?? $layer->product?->name ?? '-' }}</td>
                                <td>{{ $layer->branch?->name ?? '-' }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($layer->quantity, 2), '0'), '.') }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($layer->quantity_remaining, 2), '0'), '.') }}</td>
                                <td class="text-end">Rp {{ number_format($layer->unit_cost, 2) }}</td>
                                <td>@if($layer->expiry_date)<span class="badge bg-label-warning">{{ $layer->expiry_date->format('d/m/Y') }}</span>@else<span class="text-muted">-</span>@endif</td>
                                <td><span class="badge bg-label-secondary">{{ $layer->source_type_label }}</span></td>
                                <td class="text-end">
                                    @if($layer->quantity_remaining > 0 && $layer->product_variant_id && $layer->branch_id)
                                        @php $wipId = optional(\App\Support\WmsContext::wipWarehouse($layer->company_id))->id; @endphp
                                        <a href="{{ route('inbound.transfer.create', [
                                            'product_variant_id' => $layer->product_variant_id,
                                            'from_branch_id' => $layer->branch_id,
                                            'to_branch_id' => $wipId,
                                        ]) }}" class="btn btn-sm btn-icon btn-text-secondary" title="Pindah ke Gudang WIP">
                                            <i class="ti ti-arrows-transfer-up"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">Belum ada data. Klik "Stok Masuk" untuk menambah.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
