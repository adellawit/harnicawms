<x-app-layout>
    @section('title', 'Marketing Campaign | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Marketing Center', 'url' => route('marketing.assets.index')],
            ['label' => 'Marketing Campaign', 'active' => true],
        ]" />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0">Marketing Campaign</h5>
                <a href="{{ route('marketing.campaigns.create') }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-plus me-1"></i> Tambah Campaign
                </a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Banner</th>
                            <th>Promotion</th>
                            <th>Periode</th>
                            <th>Reaktivasi</th>
                            <th>Aktif</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($campaigns as $campaign)
                            <tr>
                                <td class="fw-semibold">{{ $campaign->code }}</td>
                                <td>{{ $campaign->name }}</td>
                                <td>
                                    @if ($campaign->banner_url)
                                        <img src="{{ $campaign->banner_url }}" alt="" class="rounded" style="max-height:40px">
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($campaign->promotion)
                                        <span class="badge bg-label-primary">{{ $campaign->promotion->code }}</span>
                                        <div class="small text-muted">{{ $campaign->promotion->name }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small">
                                    {{ $campaign->starts_at?->format('d M Y') ?? '—' }}
                                    –
                                    {{ $campaign->ends_at?->format('d M Y') ?? '—' }}
                                </td>
                                <td>
                                    @if ($campaign->reactivates_reseller)
                                        <span class="badge bg-label-success">Ya</span>
                                    @else
                                        <span class="badge bg-label-secondary">Tidak</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($campaign->is_active)
                                        <span class="badge bg-label-success">Aktif</span>
                                    @else
                                        <span class="badge bg-label-secondary">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('marketing.campaigns.edit', $campaign->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <form action="{{ route('marketing.campaigns.destroy', $campaign->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus campaign ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Belum ada campaign.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($campaigns->hasPages())
                <div class="card-footer">{{ $campaigns->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
