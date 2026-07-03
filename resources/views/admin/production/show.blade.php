<x-app-layout>
    @section('title', 'Detail Produksi | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Produksi'],
                ['label' => 'Production Order', 'url' => route('production.index')],
                ['label' => $order->order_number, 'active' => true],
            ]"
        />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><small class="text-muted">No. Produksi</small><div class="fw-medium">{{ $order->order_number }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Produk Jadi</small><div class="fw-medium">{{ $order->variant?->display_name ?? $order->product?->name }}</div></div>
                    <div class="col-md-2"><small class="text-muted">Qty</small><div class="fw-medium">{{ rtrim(rtrim(number_format($order->produced_qty ?: $order->planned_qty, 2), '0'), '.') }}</div></div>
                    <div class="col-md-2"><small class="text-muted">Status</small><div>
                        @php $map = ['draft'=>'secondary','in_progress'=>'info','completed'=>'success','cancelled'=>'danger']; @endphp
                        <span class="badge bg-label-{{ $map[$order->status] ?? 'secondary' }}">{{ ucfirst($order->status) }}</span>
                    </div></div>
                    <div class="col-md-2"><small class="text-muted">HPP / Unit</small><div class="fw-medium text-primary">@if($order->output_unit_cost > 0)Rp {{ number_format($order->output_unit_cost, 2) }}@else-@endif</div></div>
                    <div class="col-md-3"><small class="text-muted">Gudang Bahan Baku</small><div class="fw-medium">{{ $order->sourceWarehouse?->name ?? '-' }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Gudang Produk Jadi</small><div class="fw-medium">{{ $order->outputWarehouse?->name ?? '-' }}</div></div>
                </div>
                @if ($order->status !== 'completed')
                    <form method="POST" action="{{ route('production.complete', $order->id) }}" class="mt-3" onsubmit="return confirm('Selesaikan produksi? Bahan baku akan dikonsumsi (FIFO).')">
                        @csrf
                        <button class="btn btn-success"><i class="ti ti-check me-1"></i> Selesaikan Produksi</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h6 class="card-title mb-0">Bahan Baku Dikonsumsi</h6></div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Bahan</th><th class="text-end">Qty</th><th class="text-end">HPP/Unit</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                                @forelse ($order->materials as $m)
                                    <tr>
                                        <td>{{ $m->componentVariant?->display_name ?? $m->componentProduct?->name }}</td>
                                        <td class="text-end">{{ rtrim(rtrim(number_format($m->qty_consumed, 4), '0'), '.') }}</td>
                                        <td class="text-end">Rp {{ number_format($m->unit_cost, 2) }}</td>
                                        <td class="text-end">Rp {{ number_format($m->total_cost, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-3">Belum diproses.</td></tr>
                                @endforelse
                            </tbody>
                            @if ($order->materials->count())
                            <tfoot><tr class="fw-bold"><td colspan="3" class="text-end">Total Biaya Bahan</td><td class="text-end">Rp {{ number_format($order->total_material_cost, 2) }}</td></tr></tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h6 class="card-title mb-0">Hasil Produksi</h6></div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Produk</th><th class="text-end">Qty</th><th class="text-end">HPP/Unit</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                                @forelse ($order->outputs as $o)
                                    <tr>
                                        <td>{{ $o->variant?->display_name ?? $o->product?->name }}</td>
                                        <td class="text-end">{{ rtrim(rtrim(number_format($o->qty_produced, 2), '0'), '.') }}</td>
                                        <td class="text-end text-primary fw-medium">Rp {{ number_format($o->unit_cost, 2) }}</td>
                                        <td class="text-end">Rp {{ number_format($o->total_cost, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-3">Belum diproses.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('production.index') }}" class="btn btn-outline-secondary">Kembali</a>
    </div>
</x-app-layout>
