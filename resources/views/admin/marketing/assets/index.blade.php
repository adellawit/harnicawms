<x-app-layout>
    @section('title', 'Marketing Center | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Marketing Center', 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Materi Promosi (Pustaka Aset)</h5>
                <div>
                    <a href="{{ route('marketing.categories.index') }}" class="btn btn-outline-secondary btn-sm"><i class="ti ti-tags me-1"></i>Kategori</a>
                    <a href="{{ route('marketing.assets.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus me-1"></i>Tambah Aset</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Judul</th><th>Kategori</th><th>Tipe</th><th>Scope</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($assets as $asset)
                            <tr>
                                <td>{{ $asset->title }}</td>
                                <td>{{ $asset->category?->name ?? '-' }}</td>
                                <td><span class="badge bg-label-info">{{ $asset->type }}</span></td>
                                <td>
                                    @if($asset->usable_in_marketing)<span class="badge bg-label-primary">Marketing</span>@endif
                                    @if($asset->usable_in_training)<span class="badge bg-label-success">Training</span>@endif
                                    @if($asset->can_be_thumbnail)<span class="badge bg-label-secondary">Thumbnail</span>@endif
                                </td>
                                <td><span class="badge bg-label-{{ $asset->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($asset->status) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('marketing.assets.edit', $asset->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <form action="{{ route('marketing.assets.destroy', $asset->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus aset ini?')">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Belum ada aset.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $assets->links() }}</div>
        </div>
    </div>
</x-app-layout>
