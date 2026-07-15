<x-app-layout>
    @section('title', 'Kategori Aset | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Marketing Center', 'url' => route('marketing.assets.index')],
            ['label' => 'Kategori', 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Kategori Aset</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#catModal" onclick="fillCatForm({})"><i class="ti ti-plus me-1"></i> Tambah Kategori</button>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Urutan</th><th>Nama</th><th>Warna</th><th>Ikon</th><th>Aset</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($categories as $cat)
                            <tr>
                                <td>{{ $cat->sort_order }}</td>
                                <td>{{ $cat->name }}</td>
                                <td><span class="badge" style="background: {{ $cat->color ?: '#e7e7e7' }}">{{ $cat->color ?: '-' }}</span></td>
                                <td>@if($cat->icon)<i class="ti {{ $cat->icon }}"></i> <code>{{ $cat->icon }}</code>@else - @endif</td>
                                <td>{{ $cat->assets_count }}</td>
                                <td><span class="badge bg-label-{{ $cat->is_active ? 'success' : 'secondary' }}">{{ $cat->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#catModal" onclick='fillCatForm(@json($cat))'>Edit</button>
                                    <form action="{{ route('marketing.categories.destroy', $cat->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus kategori ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">Belum ada kategori.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="catModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="catForm" method="POST" action="{{ route('marketing.categories.store') }}">
                @csrf
                <input type="hidden" name="_method" id="catMethod" value="POST">
                <div class="modal-header"><h5 class="modal-title" id="catModalTitle">Tambah Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="catName" class="form-control" required></div>
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">Warna</label>
                            <input type="color" name="color" id="catColor" class="form-control form-control-color" value="#5C9E84"></div>
                        <div class="col-6"><label class="form-label">Ikon (Tabler)</label>
                            <input type="text" name="icon" id="catIcon" class="form-control" placeholder="ti-photo"></div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-6"><label class="form-label">Urutan</label>
                            <input type="number" name="sort_order" id="catSort" class="form-control" value="0" min="0"></div>
                        <div class="col-6 d-flex align-items-end"><div class="form-check">
                            <input type="checkbox" name="is_active" id="catActive" class="form-check-input" value="1" checked>
                            <label class="form-check-label" for="catActive">Aktif</label></div></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    @push('page-js')
    <script>
        const catStoreUrl = "{{ route('marketing.categories.store') }}";
        const catUpdateBase = "{{ url('marketing/categories') }}";
        function fillCatForm(c) {
            const isEdit = !!c.id;
            document.getElementById('catModalTitle').textContent = isEdit ? 'Edit Kategori' : 'Tambah Kategori';
            document.getElementById('catForm').action = isEdit ? (catUpdateBase + '/' + c.id) : catStoreUrl;
            document.getElementById('catMethod').value = isEdit ? 'PUT' : 'POST';
            document.getElementById('catName').value = c.name || '';
            document.getElementById('catColor').value = c.color || '#5C9E84';
            document.getElementById('catIcon').value = c.icon || '';
            document.getElementById('catSort').value = c.sort_order ?? 0;
            document.getElementById('catActive').checked = c.id ? !!c.is_active : true;
        }
    </script>
    @endpush
</x-app-layout>
