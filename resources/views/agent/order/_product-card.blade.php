<div class="col-6 col-md-4 col-xl-3">
    <div class="card shop-product-card h-100" data-product-id="{{ $product['id'] }}"
        data-product-name="{{ product_print_name($product['name']) }}">
        @if (($showPromo ?? false) || ($product['is_promo'] ?? false))
            <span class="badge bg-danger shop-promo-badge">PROMO</span>
        @endif
        <div class="thumb">
            <x-thumb :url="$product['image']" type="product" :alt="product_print_name($product['name'])" style="width:100%;height:100%" />
        </div>
        <div class="card-body">
            <div class="product-name">{{ product_print_name($product['name']) }}</div>
            <div class="product-price">
                Rp {{ number_format($product['min_price'], 0, ',', '.') }}
                @if ($product['variants_count'] > 1)
                    <span class="text-muted fw-normal">+</span>
                @endif
            </div>
        </div>
    </div>
</div>
