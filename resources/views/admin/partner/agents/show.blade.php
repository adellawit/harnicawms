<x-app-layout>
    @section('title', 'Detail Agent | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Partner Network', 'url' => route('partner.reports.index')],
            ['label' => 'Agents', 'url' => route('partner.agents.index')],
            ['label' => $agent->name, 'active' => true],
        ]" />

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><small class="text-muted">Kode</small><div><code>{{ $agent->code }}</code></div></div>
                    <div class="col-md-3"><small class="text-muted">Nama</small><div class="fw-medium">{{ $agent->name }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Status</small><div><span class="badge bg-label-success">{{ $agent->status }}</span></div></div>
                    <div class="col-md-3"><small class="text-muted">Approval</small><div>{{ $agent->approval_status }}</div></div>
                    <div class="col-md-6"><small class="text-muted">Kontak</small><div>{{ $agent->email ?: '-' }} · {{ $agent->phone ?: '-' }}</div></div>
                    <div class="col-md-6"><small class="text-muted">Customer</small><div>{{ $agent->customer?->code }} {{ $agent->customer?->name }}</div></div>
                    <div class="col-md-6"><small class="text-muted">Username Login</small><div>{{ $agent->user?->username ?: '-' }}</div></div>
                    <div class="col-md-6"><small class="text-muted">Password Awal</small><div>{{ $agent->user ? 'agent12345 (wajib diganti saat login pertama)' : '-' }}</div></div>
                    <div class="col-12"><small class="text-muted">Alamat</small><div>{{ $agent->address ?: '-' }}</div></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h6 class="card-title mb-0">Gudang Agent</h6></div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Kode</th><th>Nama</th><th>Default</th><th>Aktif</th></tr></thead>
                            <tbody>
                                @forelse($agent->warehouses as $warehouse)
                                    <tr>
                                        <td><code>{{ $warehouse->code }}</code></td>
                                        <td>{{ $warehouse->name }}</td>
                                        <td>{{ $warehouse->is_default ? 'Ya' : '-' }}</td>
                                        <td>{{ $warehouse->is_active ? 'Ya' : 'Tidak' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada gudang.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h6 class="card-title mb-0">Reseller Aktif</h6></div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Kode</th><th>Nama</th><th>Kontak</th></tr></thead>
                            <tbody>
                                @forelse($agent->resellers as $reseller)
                                    <tr>
                                        <td><code>{{ $reseller->code }}</code></td>
                                        <td><a href="{{ route('partner.resellers.show', $reseller->id) }}">{{ $reseller->name }}</a></td>
                                        <td>{{ $reseller->phone ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted">Belum ada reseller.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
