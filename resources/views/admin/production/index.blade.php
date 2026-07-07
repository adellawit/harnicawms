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
                            <th class="text-end">Grand Total</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
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
                                $map = ['draft'=>'secondary','in_progress'=>'info','pending_receiving'=>'warning','completed'=>'success','cancelled'=>'danger'];
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
                                <td class="text-end">
                                    @if ($o->output_unit_cost > 0)
                                        Rp {{ number_format($o->output_unit_cost * $qty, 2) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @php $statusLabels = ['draft'=>'Draft','in_progress'=>'Sedang Dikerjakan','pending_receiving'=>'Menunggu Receiving','completed'=>'Selesai','cancelled'=>'Dibatalkan']; @endphp
                                    <span class="badge bg-label-{{ $map[$o->status] ?? 'secondary' }}">{{ $statusLabels[$o->status] ?? ucfirst($o->status) }}</span>
                                </td>
                                <td class="text-end">
                                    @if ($o->status === 'draft')
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                <i class="ti ti-dots-vertical text-primary"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('production.show', $o->id) }}">
                                                        <i class="ti ti-eye me-2 text-primary"></i>Lihat
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('production.edit', $o->id) }}">
                                                        <i class="ti ti-pencil me-2 text-warning"></i>Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <form method="POST" action="{{ route('production.destroy', $o->id) }}" onsubmit="return confirm('Hapus production order ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="ti ti-trash me-2 text-danger"></i>Hapus
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    @else
                                        <a href="{{ route('production.show', $o->id) }}" class="btn btn-sm btn-icon btn-outline-primary"><i class="ti ti-eye"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada produksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('page-js')
    <script>
        document.querySelectorAll('.table-responsive .dropdown-toggle[data-bs-toggle="dropdown"]').forEach(function (toggle) {
            bootstrap.Dropdown.getOrCreateInstance(toggle, {
                popperConfig: function (defaultConfig) {
                    return Object.assign({}, defaultConfig, { strategy: 'fixed' });
                },
            });
        });
    </script>
    @endpush
</x-app-layout>
