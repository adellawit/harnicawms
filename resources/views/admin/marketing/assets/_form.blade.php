@php($t = old('type', $asset->type ?? 'image'))
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Judul <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $asset->title ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Kategori <span class="text-danger">*</span></label>
        <select name="category_id" class="form-select" required>
            <option value="">— pilih —</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(old('category_id', $asset->category_id ?? '') === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control" rows="2">{{ old('description', $asset->description ?? '') }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label">Tipe <span class="text-danger">*</span></label>
        <select name="type" id="assetType" class="form-select" onchange="toggleAssetFields()">
            <option value="image" @selected($t==='image')>Gambar</option>
            <option value="video" @selected($t==='video')>Video (link)</option>
            <option value="pdf" @selected($t==='pdf')>PDF</option>
            <option value="text" @selected($t==='text')>Teks WhatsApp</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="draft" @selected(old('status', $asset->status ?? 'draft')==='draft')>Draft</option>
            <option value="active" @selected(old('status', $asset->status ?? '')==='active')>Aktif</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Urutan</label>
        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $asset->sort_order ?? 0) }}" min="0">
    </div>

    <div class="col-12 asset-file" id="assetFileWrap">
        <label class="form-label">File</label>
        <input type="file" name="file" class="form-control">
        <small class="text-muted" id="assetFileHint"></small>
        @if (!empty($asset?->file_url))
            <div class="mt-2">
                @if(($asset->type ?? '')==='image')<img src="{{ $asset->file_url }}" style="max-height:90px" class="rounded">
                @else <a href="{{ $asset->file_url }}" target="_blank" class="btn btn-sm btn-outline-secondary">Lihat file</a>@endif
            </div>
        @endif
    </div>
    <div class="col-12 asset-link d-none" id="assetLinkWrap">
        <label class="form-label">URL Video (IG/TikTok/YouTube/dll)</label>
        <input type="text" name="link_url" class="form-control" value="{{ old('link_url', $asset->link_url ?? '') }}" placeholder="https://...">
    </div>
    <div class="col-12 asset-text d-none" id="assetTextWrap">
        <label class="form-label">Teks WhatsApp</label>
        <textarea name="body_text" class="form-control" rows="4">{{ old('body_text', $asset->body_text ?? '') }}</textarea>
    </div>

    <div class="col-12">
        <label class="form-label d-block">Scope pemakaian <span class="text-danger">*</span></label>
        <div class="form-check form-check-inline">
            <input type="checkbox" name="usable_in_marketing" id="scopeMarketing" class="form-check-input" value="1"
                @checked(old('usable_in_marketing', $asset->usable_in_marketing ?? true))>
            <label class="form-check-label" for="scopeMarketing">Marketing (reseller)</label>
        </div>
        <div class="form-check form-check-inline">
            <input type="checkbox" name="usable_in_training" id="scopeTraining" class="form-check-input" value="1"
                @checked(old('usable_in_training', $asset->usable_in_training ?? false))>
            <label class="form-check-label" for="scopeTraining">Training (course)</label>
        </div>
    </div>
    <div class="col-12 asset-thumb" id="assetThumbWrap">
        <div class="form-check">
            <input type="checkbox" name="can_be_thumbnail" id="canThumb" class="form-check-input" value="1"
                @checked(old('can_be_thumbnail', $asset->can_be_thumbnail ?? false))>
            <label class="form-check-label" for="canThumb">Boleh dipakai sebagai thumbnail course</label>
        </div>
    </div>
</div>

@push('page-js')
<script>
    function toggleAssetFields() {
        const t = document.getElementById('assetType').value;
        document.getElementById('assetFileWrap').classList.toggle('d-none', !(t === 'image' || t === 'pdf'));
        document.getElementById('assetLinkWrap').classList.toggle('d-none', t !== 'video');
        document.getElementById('assetTextWrap').classList.toggle('d-none', t !== 'text');
        document.getElementById('assetThumbWrap').classList.toggle('d-none', t !== 'image');
        document.getElementById('assetFileHint').textContent = t === 'image' ? 'Format .jpg/.png/.webp' : (t === 'pdf' ? 'Format .pdf' : '');
        // Teks WA tidak boleh Training-scoped.
        const train = document.getElementById('scopeTraining');
        if (t === 'text') { train.checked = false; train.disabled = true; } else { train.disabled = false; }
    }
    document.addEventListener('DOMContentLoaded', toggleAssetFields);
</script>
@endpush
