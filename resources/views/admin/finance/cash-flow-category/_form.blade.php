@php $isEdit = isset($category); @endphp

<input type="hidden" name="id" value="{{ $isEdit ? $category->id : '' }}">

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label" for="code">Kode <span class="text-danger">*</span></label>
        <input type="text" name="code" id="code" class="form-control" maxlength="50" required
            value="{{ old('code', $isEdit ? $category->code : '') }}"
            placeholder="contoh: operating">
        <div class="form-text">Huruf kecil, angka, underscore, dash.</div>
    </div>
    <div class="col-md-5">
        <label class="form-label" for="name">Nama <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name" class="form-control" maxlength="255" required
            value="{{ old('name', $isEdit ? $category->name : '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="sort_order">Urutan</label>
        <input type="number" name="sort_order" id="sort_order" class="form-control" min="0" max="9999"
            value="{{ old('sort_order', $isEdit ? $category->sort_order : 0) }}">
    </div>
    <div class="col-md-9">
        <label class="form-label" for="description">Deskripsi</label>
        <textarea name="description" id="description" class="form-control" rows="2" maxlength="1000">{{ old('description', $isEdit ? $category->description : '') }}</textarea>
    </div>
    <div class="col-md-3 d-flex align-items-center">
        <div class="form-check mt-4">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                @checked(old('is_active', $isEdit ? $category->is_active : true))>
            <label class="form-check-label" for="is_active">Aktif</label>
        </div>
    </div>
</div>
