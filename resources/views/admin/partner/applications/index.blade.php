<x-app-layout>
    @section('title', 'Partner Applications | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Partner Network', 'url' => route('partner.reports.index')],
            ['label' => 'Applications', 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Partner Applications</h5>
                @permission('Partner Application', 'is_create')
                <a href="{{ route('partner.applications.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus me-1"></i> Tambah</a>
                @endpermission
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>No</th><th>Nomor</th><th>Tipe</th><th>Nama</th><th>Kontak</th><th>Status</th><th>Assigned Agent</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($applications as $app)
                            <tr>
                                <td>{{ $loop->iteration + ($applications->firstItem() - 1) }}</td>
                                <td><code>{{ $app->application_number }}</code></td>
                                <td><span class="badge bg-label-primary">{{ $app->partner_type }}</span></td>
                                <td>{{ $app->name }}</td>
                                <td>{{ $app->email ?: '-' }}<br><small>{{ $app->phone }}</small></td>
                                <td><span class="badge bg-label-secondary">{{ str_replace('_', ' ', $app->status) }}</span></td>
                                <td>{{ $app->assignedAgent?->name ?? '-' }}</td>
                                <td class="text-end"><a href="{{ route('partner.applications.show', $app->id) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">Belum ada pendaftaran partner.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $applications->links() }}</div>
        </div>
    </div>
</x-app-layout>
