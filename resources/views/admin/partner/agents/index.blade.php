<x-app-layout>
    @section('title', 'Partner Agents | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Customer'],
            ['label' => 'Network', 'url' => route('partner.reports.index')],
            ['label' => 'Agents', 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="card-title mb-0">Partner Agents</h5>
                <div class="d-flex flex-wrap gap-1">
                    <a href="{{ route('partner.agents.index') }}" class="btn btn-sm {{ ! request('pks_status') ? 'btn-primary' : 'btn-outline-secondary' }}">Semua</a>
                    <a href="{{ route('partner.agents.index', ['pks_status' => 'missing']) }}" class="btn btn-sm {{ request('pks_status') === 'missing' ? 'btn-warning' : 'btn-outline-warning' }}">Belum PKS</a>
                    <a href="{{ route('partner.agents.index', ['pks_status' => 'expiring']) }}" class="btn btn-sm {{ request('pks_status') === 'expiring' ? 'btn-danger' : 'btn-outline-danger' }}">Segera berakhir</a>
                    <a href="{{ route('partner.agents.index', ['pks_status' => 'expired']) }}" class="btn btn-sm {{ request('pks_status') === 'expired' ? 'btn-secondary' : 'btn-outline-secondary' }}">Expired</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Kontak</th>
                            <th>Gudang Default</th>
                            <th>Status</th>
                            <th>PKS</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($agents as $agent)
                            @php
                                $badge = $agent->pks_badge ?? 'none';
                                $badgeMap = [
                                    'missing' => ['Belum PKS', 'bg-label-warning'],
                                    'active' => ['Aktif' . ($agent->activePks?->end_date ? ' · ' . $agent->activePks->end_date->format('d/m/Y') : ''), 'bg-label-success'],
                                    'expiring' => ['Segera berakhir' . ($agent->activePks?->end_date ? ' · ' . $agent->activePks->end_date->format('d/m/Y') : ''), 'bg-label-danger'],
                                    'expired' => ['Expired', 'bg-label-secondary'],
                                    'none' => ['—', 'bg-label-secondary'],
                                ];
                                [$badgeLabel, $badgeClass] = $badgeMap[$badge] ?? $badgeMap['none'];
                            @endphp
                            <tr>
                                <td><code>{{ $agent->code }}</code></td>
                                <td>{{ $agent->name }}</td>
                                <td>{{ $agent->email ?: '-' }}<br><small>{{ $agent->phone }}</small></td>
                                <td>{{ $agent->defaultWarehouse?->name ?? '-' }}</td>
                                <td><span class="badge bg-label-success">{{ $agent->status }}</span></td>
                                <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                                <td class="text-end"><a href="{{ route('partner.agents.show', $agent->id) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">Belum ada Agent.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $agents->links() }}</div>
        </div>
    </div>
</x-app-layout>
