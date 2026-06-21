<x-app-layout>
    @section('title', 'Partner Agents | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Partner Network', 'url' => route('partner.reports.index')],
            ['label' => 'Agents', 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Partner Agents</h5></div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Kode</th><th>Nama</th><th>Kontak</th><th>Gudang Default</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($agents as $agent)
                            <tr>
                                <td><code>{{ $agent->code }}</code></td>
                                <td>{{ $agent->name }}</td>
                                <td>{{ $agent->email ?: '-' }}<br><small>{{ $agent->phone }}</small></td>
                                <td>{{ $agent->defaultWarehouse?->name ?? '-' }}</td>
                                <td><span class="badge bg-label-success">{{ $agent->status }}</span></td>
                                <td class="text-end"><a href="{{ route('partner.agents.show', $agent->id) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Belum ada Agent.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $agents->links() }}</div>
        </div>
    </div>
</x-app-layout>
