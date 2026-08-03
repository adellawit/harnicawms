@php
    /** @var \App\Models\Partner\CuttingPriceConfig|null $config */
    $config = $config ?? null;
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="product_id">Produk <span class="text-danger">*</span></label>
        <select id="product_id" name="product_id" class="form-select select2" required>
            <option value="">Pilih produk</option>
            @foreach($products as $product)
                <option value="{{ $product->id }}"
                    data-category="{{ $product->category_id }}"
                    @selected(old('product_id', $config?->product_id) === $product->id)>
                    {{ $product->code }} · {{ $product->name }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Konfigurasi terpisah dari price list / REGULER.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="category_id">Kategori</label>
        <select id="category_id" name="category_id" class="form-select select2" data-allow-clear="true">
            <option value="">Ikuti kategori produk</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $config?->category_id) === $category->id)>
                    {{ $category->code }} · {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="unit_code">Unit <span class="text-danger">*</span></label>
        <input type="text" id="unit_code" name="unit_code" class="form-control text-uppercase"
               value="{{ old('unit_code', $config?->unit_code ?? 'BOX') }}" required maxlength="20">
        <div class="form-text">Harus cocok dengan unit penjualan (code/symbol/name), mis. BOX.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="official_price">Harga Resmi (Rp) <span class="text-danger">*</span></label>
        <input type="text" id="official_price" name="official_price" class="form-control number-format" inputmode="decimal"
               value="{{ format_number(old('official_price', $config?->official_price ?? 0), 0, true) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="map_price">MAP Floor (Rp) <span class="text-danger">*</span></label>
        <input type="text" id="map_price" name="map_price" class="form-control number-format" inputmode="decimal"
               value="{{ format_number(old('map_price', $config?->map_price ?? 0), 0, true) }}" required>
        <div class="form-text">Floor report: jual di bawah MAP = melanggar.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="sort_order">Sort Order</label>
        <input type="number" id="sort_order" name="sort_order" class="form-control" min="0"
               value="{{ old('sort_order', $config?->sort_order ?? 10) }}">
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                   @checked(old('is_active', $config?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Aktif</label>
        </div>
    </div>
</div>
