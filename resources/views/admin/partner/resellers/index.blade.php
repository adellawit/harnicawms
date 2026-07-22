<x-app-layout>
    @section('title', 'Partner Resellers | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Customer'],
            ['label' => 'Network', 'url' => route('partner.reports.index')],
            ['label' => 'Resellers', 'active' => true],
        ]" />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Partner Resellers</h5>
                <a href="{{ route('partner.resellers.mapping.index') }}" class="btn btn-sm btn-primary">
                    <i class="ti ti-link me-1"></i>Reseller Mapping
                </a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Kode</th><th>Nama</th><th>Agent</th><th>Kontak</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($resellers as $reseller)
                            <tr>
                                <td><code>{{ $reseller->code }}</code></td>
                                <td>{{ $reseller->name }}</td>
                                <td>{{ $reseller->agent?->name }}</td>
                                <td>{{ $reseller->email ?: '-' }}<br><small>{{ $reseller->phone }}</small></td>
                                <td><span class="badge bg-label-success">{{ $reseller->status }}</span></td>
                                <td class="text-end"><a href="{{ route('partner.resellers.show', $reseller->id) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Belum ada Reseller.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $resellers->links() }}</div>
        </div>
    </div>
</x-app-layout>
