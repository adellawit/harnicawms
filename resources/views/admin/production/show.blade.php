<x-app-layout>
    @section('title', 'Detail Produksi | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production', 'url' => route('production.index')],
                ['label' => 'Production In-House', 'url' => route('production.index')],
                ['label' => $order->order_number, 'active' => true],
            ]"
        />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        @php
            $outputUnit = $order->outputUnit?->symbol ?? $order->outputUnit?->name ?? '';
            $orderQty = (float) ($order->produced_qty ?: $order->planned_qty);
            $orderConversionHint = $order->product && $order->output_unit_id
                ? \App\Support\ProductionQuantityDisplay::conversionSummary($order->product, $orderQty, $order->output_unit_id)
                : null;
        @endphp

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><small class="text-muted">No. Produksi</small><div class="fw-medium">{{ $order->order_number }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Produk Jadi</small><div class="fw-medium">{{ $order->variant?->display_name ?? $order->product?->name }}</div></div>
                    <div class="col-md-2">
                        <small class="text-muted">Qty{{ $outputUnit ? ' (' . $outputUnit . ')' : '' }}</small>
                        <div class="fw-medium">
                            {{ rtrim(rtrim(number_format($orderQty, 2), '0'), '.') }}
                            @if ($outputUnit)<span class="text-muted">{{ $outputUnit }}</span>@endif
                        </div>
                        @if ($orderConversionHint)
                            <small class="text-muted">{{ $orderConversionHint }}</small>
                        @endif
                    </div>
                    <div class="col-md-2"><small class="text-muted">Status</small><div>
                        @php $map = ['draft'=>'secondary','in_progress'=>'info','pending_receiving'=>'warning','completed'=>'success','cancelled'=>'danger']; @endphp
                        @php $statusLabels = ['draft'=>'Draft','in_progress'=>'Sedang Dikerjakan','pending_receiving'=>'Menunggu Receiving','completed'=>'Selesai','cancelled'=>'Dibatalkan']; @endphp
                        <span class="badge bg-label-{{ $map[$order->status] ?? 'secondary' }}">{{ $statusLabels[$order->status] ?? ucfirst($order->status) }}</span>
                    </div></div>
                    <div class="col-md-2">
                        <small class="text-muted">HPP / Unit{{ $outputUnit ? ' (' . $outputUnit . ')' : '' }}</small>
                        <div class="fw-medium text-primary">
                            @if ($order->output_unit_cost > 0)
                                Rp {{ number_format($order->output_unit_cost, 2) }}
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div class="col-md-3"><small class="text-muted">Gudang Bahan Baku</small><div class="fw-medium">{{ $order->sourceWarehouse?->name ?? '-' }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Gudang Produk Jadi</small><div class="fw-medium">{{ $order->outputWarehouse?->name ?? '-' }}</div></div>
                </div>
                @if ($order->status === 'draft')
                    <form method="POST" action="{{ route('production.start', $order->id) }}" class="mt-3">
                        @csrf
                        <button class="btn btn-primary"><i class="ti ti-player-play me-1"></i> Mulai Produksi</button>
                    </form>
                @elseif ($order->status === 'in_progress')
                    <form method="POST" action="{{ route('production.finish', $order->id) }}" class="mt-3">
                        @csrf
                        <button class="btn btn-success"><i class="ti ti-check me-1"></i> Selesaikan Produksi</button>
                    </form>
                @elseif ($order->status === 'pending_receiving')
                    <a href="{{ route('production.receive', $order->id) }}" class="btn btn-warning mt-3"><i class="ti ti-package-import me-1"></i> Terima Hasil Produksi</a>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h6 class="card-title mb-0">Bahan Baku Dikonsumsi</h6></div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Bahan</th><th class="text-end">Rencana</th><th class="text-end">Aktual Terpakai</th><th class="text-end">Sisa</th><th class="text-end">HPP/Unit</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                                @forelse ($order->materials as $m)
                                    @php
                                        $materialUnit = $m->unit?->symbol ?? $m->unit?->name ?? '';
                                        $materialProduct = $m->componentVariant?->product ?? $m->componentProduct;
                                        $materialQty = (float) $m->qty_consumed;
                                        $materialConversionHint = $materialProduct && $m->unit_id
                                            ? \App\Support\ProductionQuantityDisplay::conversionSummary($materialProduct, $materialQty, $m->unit_id)
                                            : null;
                                    @endphp
                                    <tr>
                                        <td>{{ $m->componentVariant?->display_name ?? $m->componentProduct?->name }}</td>
                                        @php $sisa = (float) $m->expected_qty - $materialQty; @endphp
                                        <td class="text-end">{{ rtrim(rtrim(number_format((float) $m->expected_qty, 4), '0'), '.') }} {{ $materialUnit }}</td>
                                        <td class="text-end">
                                            <div>
                                                {{ rtrim(rtrim(number_format($materialQty, 4), '0'), '.') }}
                                                @if ($materialUnit)<span class="text-muted ms-1">{{ $materialUnit }}</span>@endif
                                            </div>
                                            @if ($materialConversionHint)
                                                <small class="text-muted">{{ $materialConversionHint }}</small>
                                            @endif
                                        </td>
                                        <td class="text-end {{ $sisa > 0 ? 'text-success' : ($sisa < 0 ? 'text-danger' : '') }}">{{ rtrim(rtrim(number_format($sisa, 4), '0'), '.') }} {{ $materialUnit }}</td>
                                        <td class="text-end">Rp {{ number_format($m->unit_cost, 2) }}</td>
                                        <td class="text-end">Rp {{ number_format($m->total_cost, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">Belum diproses.</td></tr>
                                @endforelse
                            </tbody>
                            @if ($order->materials->count())
                            <tfoot><tr class="fw-bold"><td colspan="5" class="text-end">Total Biaya Bahan</td><td class="text-end">Rp {{ number_format($order->total_material_cost, 2) }}</td></tr></tfoot>
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
                                    @php
                                        $producedUnit = $o->unit?->symbol ?? $o->unit?->name ?? $outputUnit;
                                        $outputProduct = $o->variant?->product ?? $order->product;
                                        $producedQty = (float) $o->qty_produced;
                                        $outputUnitId = $o->unit_id ?: $order->output_unit_id;
                                        $outputConversionHint = $outputProduct && $outputUnitId
                                            ? \App\Support\ProductionQuantityDisplay::conversionSummary($outputProduct, $producedQty, $outputUnitId)
                                            : null;
                                    @endphp
                                    <tr>
                                        <td>{{ $o->variant?->display_name ?? $o->product?->name }}</td>
                                        <td class="text-end">
                                            <div>
                                                {{ rtrim(rtrim(number_format($producedQty, 2), '0'), '.') }}
                                                @if ($producedUnit)<span class="text-muted ms-1">{{ $producedUnit }}</span>@endif
                                            </div>
                                            @if ($outputConversionHint)
                                                <small class="text-muted">{{ $outputConversionHint }}</small>
                                            @endif
                                        </td>
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
