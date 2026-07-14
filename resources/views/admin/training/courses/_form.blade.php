@php($cat = old('category_id', $course->category_id ?? ''))
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Judul Course <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $course->title ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Kategori <span class="text-danger">*</span></label>
        <select name="category_id" class="form-select" required>
            <option value="">— pilih —</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected($cat === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control" rows="3">{{ old('description', $course->description ?? '') }}</textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label">Thumbnail (upload)</label>
        <input type="file" name="thumbnail" class="form-control" accept="image/*">
        @if (!empty($course?->thumbnail_url))
            <img src="{{ $course->thumbnail_url }}" alt="thumb" class="mt-2 rounded" style="max-height:80px">
        @endif
    </div>
    <div class="col-md-6">
        <label class="form-label">Atau pilih dari Pustaka</label>
        <select name="thumbnail_asset_id" class="form-select">
            <option value="">— tidak pakai pustaka —</option>
            @foreach (($thumbnailAssets ?? []) as $ta)
                <option value="{{ $ta->id }}" @selected(old('thumbnail_asset_id', $course->thumbnail_asset_id ?? '') === $ta->id)>{{ $ta->title }}</option>
            @endforeach
        </select>
        <small class="text-muted">Jika dipilih, mengalahkan upload.</small>
    </div>
    <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="draft" @selected(old('status', $course->status ?? 'draft') === 'draft')>Draft</option>
            <option value="published" @selected(old('status', $course->status ?? '') === 'published')>Published</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Urutan</label>
        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $course->sort_order ?? 0) }}" min="0">
    </div>
</div>
