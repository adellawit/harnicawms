@php
    /** @var \App\Models\Partner\CuttingPriceConfig|null $config */
    $config = $config ?? null;
    $unit = old('unit_code', $config?->unit_code ?? 'BOX');
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
    </div>
    <div class="col-md-4">
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
    <div class="col-md-2">
        <label class="form-label" for="unit_code">Unit <span class="text-danger">*</span></label>
        <input type="text" id="unit_code" name="unit_code" class="form-control text-uppercase"
               value="{{ $unit }}" required maxlength="20">
    </div>
</div>

<hr class="my-4">

{{-- Mirror struktur catatan operasional --}}
<div class="cutting-price-sheet">
    <div class="row g-3 align-items-end mb-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold text-uppercase" for="official_price">
                H.K. Resmi <span class="text-danger">*</span>
            </label>
            <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="text" id="official_price" name="official_price" class="form-control number-format" inputmode="decimal"
                       value="{{ format_number(old('official_price', $config?->official_price ?? 249000), 0, true) }}" required>
                <span class="input-group-text">/ <span class="js-unit-label">{{ $unit }}</span></span>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold text-uppercase" for="map_price">
                H. Minimum Advertised <span class="text-danger">*</span>
            </label>
            <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="text" id="map_price" name="map_price" class="form-control number-format" inputmode="decimal"
                       value="{{ format_number(old('map_price', $config?->map_price ?? 229000), 0, true) }}" required>
                <span class="input-group-text">/ <span class="js-unit-label">{{ $unit }}</span></span>
            </div>
            <div class="form-text">Floor report cutting price (jual di bawah MAP = melanggar).</div>
        </div>
    </div>

    <div class="border rounded p-3 mb-3">
        <div class="fw-semibold text-uppercase mb-3">H. Reseller</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="reseller_price_30">30 <span class="js-unit-label">{{ $unit }}</span></label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="text" id="reseller_price_30" name="reseller_price_30" class="form-control number-format" inputmode="decimal"
                           value="{{ format_number(old('reseller_price_30', $config?->reseller_price_30 ?? 180000), 0, true) }}" required>
                    <span class="input-group-text">/ <span class="js-unit-label">{{ $unit }}</span></span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="reseller_price_60">60 <span class="js-unit-label">{{ $unit }}</span></label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="text" id="reseller_price_60" name="reseller_price_60" class="form-control number-format" inputmode="decimal"
                           value="{{ format_number(old('reseller_price_60', $config?->reseller_price_60 ?? 175000), 0, true) }}" required>
                    <span class="input-group-text">/ <span class="js-unit-label">{{ $unit }}</span></span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="reseller_price_120">120 <span class="js-unit-label">{{ $unit }}</span></label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="text" id="reseller_price_120" name="reseller_price_120" class="form-control number-format" inputmode="decimal"
                           value="{{ format_number(old('reseller_price_120', $config?->reseller_price_120 ?? 170000), 0, true) }}" required>
                    <span class="input-group-text">/ <span class="js-unit-label">{{ $unit }}</span></span>
                </div>
            </div>
        </div>
    </div>

    <div class="border rounded p-3 mb-3">
        <div class="fw-semibold text-uppercase mb-3">H. Agen</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="agent_price_600">600 <span class="js-unit-label">{{ $unit }}</span></label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="text" id="agent_price_600" name="agent_price_600" class="form-control number-format" inputmode="decimal"
                           value="{{ format_number(old('agent_price_600', $config?->agent_price_600 ?? 160000), 0, true) }}" required>
                    <span class="input-group-text">/ <span class="js-unit-label">{{ $unit }}</span></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                   @checked(old('is_active', $config?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Aktif</label>
        </div>
    </div>
    <input type="hidden" name="sort_order" value="{{ old('sort_order', $config?->sort_order ?? 10) }}">
</div>

@push('page-js')
<script>
    (function () {
        var unitInput = document.getElementById('unit_code');
        if (!unitInput) return;
        function syncUnitLabels() {
            var u = (unitInput.value || 'BOX').toUpperCase();
            document.querySelectorAll('.js-unit-label').forEach(function (el) { el.textContent = u; });
        }
        unitInput.addEventListener('input', syncUnitLabels);
        syncUnitLabels();
    })();
</script>
@endpush
