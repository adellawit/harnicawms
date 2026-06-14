<x-app-layout>
    @section('title', 'Replenishment | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Distribusi'],
                ['label' => 'Replenishment Order', 'active' => true],
            ]"
        />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Pesanan Distributor → Agen</h5>
                <a href="{{ route('replenishment.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus me-1"></i> Buat Pesanan</a>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Tanggal</th>
                            <th>Agen</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $o)
                            <tr>
                                <td class="fw-medium">{{ $o->order_number }}</td>
                                <td>{{ optional($o->order_date)->format('d/m/Y') }}</td>
                                <td>{{ $o->agent?->name }}</td>
                                <td class="text-end">Rp {{ number_format($o->total, 2) }}</td>
                                <td>
                                    @php $map = ['draft'=>'secondary','submitted'=>'info','approved'=>'primary','shipped'=>'warning','partially_received'=>'warning','received'=>'success','cancelled'=>'danger']; @endphp
                                    <span class="badge bg-label-{{ $map[$o->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$o->status)) }}</span>
                                </td>
                                <td class="text-end"><a href="{{ route('replenishment.show', $o->id) }}" class="btn btn-sm btn-icon btn-outline-primary"><i class="ti ti-eye"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pesanan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
