<x-app-layout>
    @section('title', 'Production Order | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Produksi'],
                ['label' => 'Production Order', 'active' => true],
            ]"
        />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Perintah Produksi</h5>
                <a href="{{ route('production.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus me-1"></i> Buat Produksi</a>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No. Produksi</th>
                            <th>Tanggal</th>
                            <th>Produk Jadi</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">HPP/Unit</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $o)
                            @php
                                $outputUnit = $o->outputUnit?->symbol ?? $o->outputUnit?->name ?? '';
                                $qty = (float) ($o->produced_qty ?: $o->planned_qty);
                                $conversionHint = $o->product && $o->output_unit_id
                                    ? \App\Support\ProductionQuantityDisplay::conversionSummary($o->product, $qty, $o->output_unit_id)
                                    : null;
                            @endphp
                            <tr>
                                <td class="fw-medium">{{ $o->order_number }}</td>
                                <td>{{ optional($o->production_date)->format('d/m/Y') }}</td>
                                <td>{{ $o->variant?->display_name ?? $o->product?->name }}</td>
                                <td class="text-end">
                                    <div>{{ rtrim(rtrim(number_format($qty, 2), '0'), '.') }}@if ($outputUnit)<span class="text-muted ms-1">{{ $outputUnit }}</span>@endif</div>
                                    @if ($conversionHint)
                                        <small class="text-muted">{{ $conversionHint }}</small>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($o->output_unit_cost > 0)
                                        Rp {{ number_format($o->output_unit_cost, 2) }}
                                        @if ($outputUnit)<small class="text-muted">/ {{ $outputUnit }}</small>@endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @php $map = ['draft'=>'secondary','in_progress'=>'info','completed'=>'success','cancelled'=>'danger']; @endphp
                                    <span class="badge bg-label-{{ $map[$o->status] ?? 'secondary' }}">{{ ucfirst($o->status) }}</span>
                                </td>
                                <td class="text-end"><a href="{{ route('production.show', $o->id) }}" class="btn btn-sm btn-icon btn-outline-primary"><i class="ti ti-eye"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada produksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
