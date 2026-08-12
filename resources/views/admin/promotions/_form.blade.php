@php
    $isEdit = isset($promo);
    $p = $promo ?? null;
    $codeDisplay = $isEdit ? $p->code : ($previewCode ?? 'PRM-YYYYMM-XXXX');
@endphp

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">{{ $isEdit ? 'Edit Promotion' : 'Promotion Rule' }}</h5>
        <p class="text-muted small mb-0 mt-1">Product = buy X get Y per line. Marketing = diskon berdasarkan target agent/reseller (evaluasi POS = fase berikutnya).</p>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-1">
            <div class="col-md-12">
                <label class="form-label d-block">
                    Promotion type <span class="text-danger">*</span>
                </label>
                @php $promoType = old('promotion_type', $p?->promotion_type ?? 'product'); @endphp
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="promotion_type" id="promotion_type_product"
                               value="product" @checked($promoType === 'product')>
                        <label class="form-check-label" for="promotion_type_product">Product (Buy X Get Y)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="promotion_type" id="promotion_type_marketing"
                               value="marketing" @checked($promoType === 'marketing')>
                        <label class="form-check-label" for="promotion_type_marketing">Marketing (Target &amp; diskon)</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">
                    Code
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Generated automatically by the system (PRM-YYYYMM-XXXX). Not editable."></i>
                </label>
                <input type="text" class="form-control" value="{{ $codeDisplay }}" readonly>
            </div>
            <div class="col-md-5">
                <label class="form-label">
                    Name <span class="text-danger">*</span>
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Display name for this promotion, e.g. Buy 100 Get 1 Marketing Sample."></i>
                </label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $p?->name) }}" required maxlength="150"
                       placeholder="Buy 100 Get 1">
            </div>
            <div class="col-md-2">
                <label class="form-label">
                    Priority
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Lower number = higher priority. If multiple promos match the same line, the lowest priority wins."></i>
                </label>
                <input type="number" name="priority" class="form-control" value="{{ old('priority', $p?->priority ?? 100) }}" min="1">
            </div>
            <div class="col-md-2">
                <label class="form-label d-block">
                    Active
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Only active promotions within the start/end dates are applied at checkout."></i>
                </label>
                <div class="form-check form-switch mt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                           @checked(old('is_active', $p?->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Enabled</label>
                </div>
            </div>
            <div class="col-md-12">
                <label class="form-label">
                    Description
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Optional internal notes about this campaign."></i>
                </label>
                <textarea name="description" class="form-control" rows="2" placeholder="Optional notes">{{ old('description', $p?->description) }}</textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label">
                    Starts at
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Leave empty to start immediately. Format: dd/mm/yyyy."></i>
                </label>
                <input type="text" name="starts_at" id="starts_at" class="form-control flatpickr-date"
                       value="{{ old('starts_at', optional($p?->starts_at)->format('d/m/Y')) }}"
                       placeholder="dd/mm/yyyy" autocomplete="off">
            </div>
            <div class="col-md-3">
                <label class="form-label">
                    Ends at
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Leave empty for no end date. Format: dd/mm/yyyy."></i>
                </label>
                <input type="text" name="ends_at" id="ends_at" class="form-control flatpickr-date"
                       value="{{ old('ends_at', optional($p?->ends_at)->format('d/m/Y')) }}"
                       placeholder="dd/mm/yyyy" autocomplete="off">
            </div>
            <div class="col-md-3">
                <label class="form-label">
                    Trigger
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Phase 1 applies rules per cart line (buy qty on that product line)."></i>
                </label>
                <input type="text" class="form-control" value="Per line" readonly>
                <input type="hidden" name="trigger_level" value="line">
            </div>
            <div class="col-md-3">
                <label class="form-label">
                    Max apps / line
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Optional cap. Example: buy 500 with X=100 Y=1 normally gives 5 free; set max=2 to limit free to 2."></i>
                </label>
                <input type="number" name="max_applications_per_line" class="form-control" min="1"
                       value="{{ old('max_applications_per_line', $p?->max_applications_per_line) }}" placeholder="Unlimited">
            </div>
        </div>
    </div>
</div>

<div id="promoProductBlock">
<div class="card mb-4">
    <div class="card-header"><h5 class="card-title mb-0">Buy condition</h5></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">
                    Min qty (X) <span class="text-danger">*</span>
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Customer must buy at least this qty on one line. Free = floor(qty / X) × Y."></i>
                </label>
                <input type="number" step="any" min="0.000001" name="buy_min_qty" class="form-control"
                       value="{{ old('buy_min_qty', $p?->buy_min_qty ?? 1) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    Buy product
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Required unless you pick a specific variant. Matches any variant of this product."></i>
                </label>
                <select name="buy_product_id" id="buy_product_id" class="form-select select2">
                    <option value="">-- Select product --</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(old('buy_product_id', $p?->buy_product_id) === $product->id)>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">
                    Buy variant
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Optional. If set, the promo only matches this exact variant (more specific)."></i>
                </label>
                <select name="buy_variant_id" id="buy_variant_id" class="form-select select2">
                    <option value="">-- Any variant of product --</option>
                    @foreach ($variants as $v)
                        <option value="{{ $v['id'] }}" data-product="{{ $v['product_id'] }}"
                            @selected(old('buy_variant_id', $p?->buy_variant_id) === $v['id'])>
                            {{ $v['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="card-title mb-0">Get reward</h5></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">
                    Get qty (Y) <span class="text-danger">*</span>
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Free qty awarded per application. Buy 100 get 1 → X=100, Y=1."></i>
                </label>
                <input type="number" step="any" min="0.000001" name="get_qty" class="form-control"
                       value="{{ old('get_qty', $p?->get_qty ?? 1) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">
                    Reward product
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Same = free the same SKU as bought. Specific = choose another product/variant as gift."></i>
                </label>
                <select name="get_product_mode" id="get_product_mode" class="form-select select2">
                    <option value="same" @selected(old('get_product_mode', $p?->get_product_mode ?? 'same') === 'same')>Same as buy item</option>
                    <option value="specific" @selected(old('get_product_mode', $p?->get_product_mode) === 'specific')>Specific product</option>
                </select>
            </div>
            <div class="col-md-3 get-specific">
                <label class="form-label">
                    Get product
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Required when reward mode is Specific product."></i>
                </label>
                <select name="get_product_id" id="get_product_id" class="form-select select2">
                    <option value="">-- Select --</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(old('get_product_id', $p?->get_product_id) === $product->id)>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 get-specific">
                <label class="form-label">
                    Get variant
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Optional. If empty, the first active variant of the get product is used."></i>
                </label>
                <select name="get_variant_id" id="get_variant_id" class="form-select select2">
                    <option value="">-- Optional --</option>
                    @foreach ($variants as $v)
                        <option value="{{ $v['id'] }}" @selected(old('get_variant_id', $p?->get_variant_id) === $v['id'])>
                            {{ $v['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    Free item warehouse <span class="text-danger">*</span>
                    <i class="ti ti-help-circle text-muted"
                       data-bs-toggle="tooltip"
                       title="Where free stock is deducted. Marketing = Gudang Marketing, FG = Gudang Product, ORDER = same warehouse as the sales order."></i>
                </label>
                <select name="free_warehouse_type" id="free_warehouse_type" class="form-select select2">
                    @foreach ($warehouseTypes as $code => $label)
                        <option value="{{ $code }}" @selected(old('free_warehouse_type', $p?->free_warehouse_type ?? 'MARKETING') === $code)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <p class="text-muted small mt-3 mb-0">
            Example: Min qty 100, Get qty 1, Same product, Marketing warehouse → buy 100 get 1 free from Marketing.
            Buy 250 → floor(250/100)×1 = 2 free.
        </p>
    </div>
</div>
</div>

<div id="promoMarketingBlock" style="display:none;">
    <div class="card mb-4">
        <div class="card-header"><h5 class="card-title mb-0">Marketing target</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Target <span class="text-danger">*</span></label>
                    <select name="target_type" id="target_type" class="form-select select2">
                        <option value="agent" @selected(old('target_type', $p?->target_type) === 'agent')>Agent</option>
                        <option value="reseller" @selected(old('target_type', $p?->target_type) === 'reseller')>Reseller</option>
                        <option value="both" @selected(old('target_type', $p?->target_type ?? 'both') === 'both')>Keduanya</option>
                    </select>
                </div>
                <div class="col-md-4 target-agent-picker">
                    <label class="form-label">Agent spesifik</label>
                    <select name="target_agent_id" id="target_agent_id" class="form-select select2">
                        <option value="">-- Semua agent --</option>
                        @foreach ($agents ?? [] as $agent)
                            <option value="{{ $agent->id }}" @selected(old('target_agent_id', $p?->target_agent_id) === $agent->id)>
                                {{ $agent->code }} — {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 target-reseller-picker">
                    <label class="form-label">Reseller spesifik</label>
                    <select name="target_reseller_id" id="target_reseller_id" class="form-select select2">
                        <option value="">-- Semua reseller --</option>
                        @foreach ($resellers ?? [] as $reseller)
                            <option value="{{ $reseller->id }}" @selected(old('target_reseller_id', $p?->target_reseller_id) === $reseller->id)>
                                {{ $reseller->code }} — {{ $reseller->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12">
                    <input type="hidden" name="reactivates_reseller" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="reactivates_reseller" value="1" id="reactivates_reseller"
                               @checked(old('reactivates_reseller', $p?->reactivates_reseller ?? false))>
                        <label class="form-check-label" for="reactivates_reseller">Reaktivasi reseller yang memenuhi syarat</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="card-title mb-0">Syarat &amp; diskon</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Syarat belanja <span class="text-danger">*</span></label>
                    <select name="min_purchase_type" id="min_purchase_type" class="form-select select2">
                        <option value="amount" @selected(old('min_purchase_type', $p?->min_purchase_type ?? 'amount') === 'amount')>Nominal belanja</option>
                        <option value="qty" @selected(old('min_purchase_type', $p?->min_purchase_type) === 'qty')>Qty belanja</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nilai syarat <span class="text-danger">*</span></label>
                    <input type="number" step="any" min="0.000001" name="min_purchase_value" class="form-control"
                           value="{{ old('min_purchase_value', $p?->min_purchase_value) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipe diskon <span class="text-danger">*</span></label>
                    <select name="discount_type" id="discount_type" class="form-select select2">
                        <option value="percent" @selected(old('discount_type', $p?->discount_type ?? 'percent') === 'percent')>Persen (%)</option>
                        <option value="nominal" @selected(old('discount_type', $p?->discount_type) === 'nominal')>Nominal (Rp)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nilai diskon <span class="text-danger">*</span></label>
                    <input type="number" step="any" min="0.000001" name="discount_value" class="form-control"
                           value="{{ old('discount_value', $p?->discount_value) }}">
                </div>
            </div>
        </div>
    </div>
</div>
