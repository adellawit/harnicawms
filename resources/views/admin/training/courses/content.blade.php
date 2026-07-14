<x-app-layout>
    @section('title', 'Kelola Isi Course | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Training Academy', 'url' => route('training.courses.index')],
            ['label' => $course->title, 'url' => route('training.courses.edit', $course->id)],
            ['label' => 'Kelola Isi', 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <div class="card mb-4"><div class="card-body d-flex justify-content-between align-items-center">
            <div><h5 class="mb-1">{{ $course->title }}</h5>
                <span class="badge bg-label-{{ $course->status === 'published' ? 'success' : 'secondary' }}">{{ ucfirst($course->status) }}</span></div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#moduleModal" onclick="fillModule({})"><i class="ti ti-plus me-1"></i>Tambah Modul</button>
        </div></div>

        @forelse ($course->modules as $module)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><span class="text-muted small">#{{ $module->sort_order }}</span> <strong>{{ $module->title }}</strong></div>
                    <div>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#materialModal"
                            onclick='fillMaterial({ module_id: "{{ $module->id }}" })'>+ Materi</button>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#moduleModal"
                            onclick='fillModule(@json($module))'>Edit</button>
                        <form action="{{ route('training.modules.destroy', [$course->id, $module->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus modul beserta materinya?')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Hapus</button></form>
                    </div>
                </div>
                <div class="table-responsive"><table class="table mb-0">
                    <thead><tr><th>#</th><th>Materi</th><th>Tipe</th><th>Estimasi</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($module->materials as $mat)
                            <tr>
                                <td>{{ $mat->sort_order }}</td>
                                <td>{{ $mat->title }}</td>
                                <td><span class="badge bg-label-info">{{ $mat->type }}</span></td>
                                <td>{{ $mat->estimated_minutes ? $mat->estimated_minutes.' mnt' : '—' }}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#materialModal"
                                        onclick='fillMaterial(@json($mat))'>Edit</button>
                                    <form action="{{ route('training.modules.materials.destroy', [$course->id, $module->id, $mat->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus materi ini?')">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Hapus</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Belum ada materi.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
        @empty
            <div class="alert alert-info">Belum ada modul. Klik "Tambah Modul" untuk mulai.</div>
        @endforelse
    </div>

    {{-- Module modal --}}
    <div class="modal fade" id="moduleModal" tabindex="-1"><div class="modal-dialog">
        <form class="modal-content" id="moduleForm" method="POST">@csrf
            <input type="hidden" name="_method" id="moduleMethod" value="POST">
            <div class="modal-header"><h5 class="modal-title" id="moduleTitle">Tambah Modul</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Judul Modul <span class="text-danger">*</span></label><input type="text" name="title" id="moduleTitleInput" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" id="moduleDesc" class="form-control" rows="2"></textarea></div>
                <div class="mb-3"><label class="form-label">Urutan</label><input type="number" name="sort_order" id="moduleSort" class="form-control" value="0" min="0"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
        </form>
    </div></div>

    {{-- Material modal --}}
    <div class="modal fade" id="materialModal" tabindex="-1"><div class="modal-dialog">
        <form class="modal-content" id="materialForm" method="POST" enctype="multipart/form-data">@csrf
            <input type="hidden" name="_method" id="materialMethod" value="POST">
            <input type="hidden" name="module_id_hidden" id="materialModuleId">
            <div class="modal-header"><h5 class="modal-title" id="materialModalTitle">Tambah Materi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Judul <span class="text-danger">*</span></label><input type="text" name="title" id="matTitle" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Tipe <span class="text-danger">*</span></label>
                    <select name="type" id="matType" class="form-select" onchange="toggleMatFields()">
                        <option value="pdf">PDF</option><option value="image">Gambar</option><option value="youtube">YouTube</option>
                    </select></div>
                <div class="mb-3" id="matFileWrap"><label class="form-label">File</label>
                    <input type="file" name="file" id="matFile" class="form-control">
                    <small class="text-muted" id="matFileHint"></small></div>
                <div class="mb-3 d-none" id="matYoutubeWrap"><label class="form-label">URL YouTube</label>
                    <input type="text" name="youtube_url" id="matYoutube" class="form-control" placeholder="https://youtu.be/..."></div>
                <div class="mb-3">
                    <label class="form-label d-block">Sumber</label>
                    <div class="form-check form-check-inline">
                        <input type="radio" name="mat_source" id="srcUpload" class="form-check-input" value="upload" checked onchange="onMatSourceChange()">
                        <label class="form-check-label" for="srcUpload">Upload / Link</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="radio" name="mat_source" id="srcLibrary" class="form-check-input" value="library" onchange="onMatSourceChange()">
                        <label class="form-check-label" for="srcLibrary">Pilih dari Pustaka</label>
                    </div>
                </div>
                <div class="mb-3 d-none" id="matLibraryWrap">
                    <label class="form-label">Aset Pustaka</label>
                    <div class="row g-2">
                        <div class="col-5">
                            <select id="matLibType" class="form-select" onchange="loadLibraryAssets()">
                                <option value="image">Gambar</option>
                                <option value="pdf">PDF</option>
                                <option value="video">Video</option>
                            </select>
                        </div>
                        <div class="col-7">
                            <select name="marketing_asset_id" id="matLibAsset" class="form-select" disabled></select>
                        </div>
                    </div>
                    <small class="text-muted">Hanya aset ber-scope Training & berstatus aktif.</small>
                </div>
                <div class="mb-3"><label class="form-label">Estimasi menit <span class="text-muted small">(opsional)</span></label>
                    <input type="number" name="estimated_minutes" id="matMinutes" class="form-control" min="0" placeholder="mis. 5"></div>
                <div class="mb-3"><label class="form-label">Urutan</label><input type="number" name="sort_order" id="matSort" class="form-control" value="0" min="0"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
        </form>
    </div></div>

    @push('page-js')
    <script>
        const courseId = "{{ $course->id }}";
        const modulesBase = "{{ url('training/courses/'.$course->id.'/modules') }}";
        const assetPickerUrl = "{{ route('marketing.assets.picker') }}";

        function fillModule(m) {
            const isEdit = !!m.id;
            document.getElementById('moduleTitle').textContent = isEdit ? 'Edit Modul' : 'Tambah Modul';
            document.getElementById('moduleForm').action = isEdit ? (modulesBase + '/' + m.id) : modulesBase;
            document.getElementById('moduleMethod').value = isEdit ? 'PUT' : 'POST';
            document.getElementById('moduleTitleInput').value = m.title || '';
            document.getElementById('moduleDesc').value = m.description || '';
            document.getElementById('moduleSort').value = m.sort_order ?? 0;
        }

        function toggleMatFields() {
            const t = document.getElementById('matType').value;
            document.getElementById('matFileWrap').classList.toggle('d-none', t === 'youtube');
            document.getElementById('matYoutubeWrap').classList.toggle('d-none', t !== 'youtube');
            document.getElementById('matFileHint').textContent = t === 'pdf' ? 'Format .pdf' : (t === 'image' ? 'Format .jpg/.png/.webp' : '');
        }

        function toggleMatSource() {
            const lib = document.getElementById('srcLibrary').checked;
            document.getElementById('matLibraryWrap').classList.toggle('d-none', !lib);
            // Hide/disable upload-mode fields when using the library.
            document.getElementById('matType').closest('.mb-3').classList.toggle('d-none', lib);
            document.getElementById('matFileWrap').classList.toggle('d-none', lib || document.getElementById('matType').value === 'youtube');
            document.getElementById('matYoutubeWrap').classList.toggle('d-none', lib || document.getElementById('matType').value !== 'youtube');
            document.getElementById('matType').disabled = lib;
            document.getElementById('matFile').disabled = lib;
            document.getElementById('matYoutube').disabled = lib;
            const libSel = document.getElementById('matLibAsset');
            libSel.disabled = !lib;
        }

        function onMatSourceChange() {
            toggleMatSource();
            if (document.getElementById('srcLibrary').checked) loadLibraryAssets();
        }

        function loadLibraryAssets(preselectId) {
            const type = document.getElementById('matLibType').value;
            const sel = document.getElementById('matLibAsset');
            sel.innerHTML = '<option value="">memuat…</option>';
            fetch(assetPickerUrl + '?asset_type=' + encodeURIComponent(type), { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(d => {
                    sel.innerHTML = '';
                    if (!d.assets || d.assets.length === 0) { sel.innerHTML = '<option value="">(tidak ada aset)</option>'; return; }
                    d.assets.forEach(a => {
                        const o = document.createElement('option');
                        o.value = a.id; o.textContent = a.title;
                        sel.appendChild(o);
                    });
                    if (preselectId) sel.value = preselectId;
                });
        }

        function fillMaterial(mat) {
            const isEdit = !!mat.id;
            const moduleId = mat.module_id;
            document.getElementById('materialModalTitle').textContent = isEdit ? 'Edit Materi' : 'Tambah Materi';
            document.getElementById('materialForm').action = isEdit
                ? (modulesBase + '/' + moduleId + '/materials/' + mat.id)
                : (modulesBase + '/' + moduleId + '/materials');
            document.getElementById('materialMethod').value = isEdit ? 'PUT' : 'POST';
            document.getElementById('materialModuleId').value = moduleId || '';
            document.getElementById('matTitle').value = mat.title || '';
            document.getElementById('matType').value = mat.type || 'pdf';
            document.getElementById('matYoutube').value = mat.youtube_url || '';
            document.getElementById('matMinutes').value = mat.estimated_minutes ?? '';
            document.getElementById('matSort').value = mat.sort_order ?? 0;
            document.getElementById('matFile').value = '';
            toggleMatFields();
            if (mat.marketing_asset_id) {
                document.getElementById('srcLibrary').checked = true;
                document.getElementById('matLibType').value = mat.type;
                toggleMatSource();
                loadLibraryAssets(mat.marketing_asset_id);
            } else {
                document.getElementById('srcUpload').checked = true;
                document.getElementById('matLibAsset').innerHTML = '';
                toggleMatSource();
            }
        }
    </script>
    @endpush
</x-app-layout>
