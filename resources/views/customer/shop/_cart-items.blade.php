@if (empty($cart['items']))
    <p class="text-muted text-center mb-0">Keranjang kosong.</p>
@else
    @foreach ($cart['items'] as $item)
        <div class="d-flex gap-2 align-items-start mb-3 cart-row" data-cart-key="{{ $item['cart_key'] }}">
            @if (!empty($item['image']))
                <img src="{{ $item['image'] }}" class="cart-item-img" alt="">
            @else
                <div class="cart-item-img d-flex align-items-center justify-content-center text-muted">
                    <i class="ti ti-package"></i>
                </div>
            @endif
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold small text-truncate">{{ $item['product_name'] }}</div>
                @if (!empty($item['variant_name']))
                    <div class="text-muted small">{{ $item['variant_name'] }}</div>
                @endif
                <div class="text-primary small fw-bold mt-1">
                    Rp {{ number_format($item['unit_price'], 0, ',', '.') }}
                </div>
                <div class="d-flex align-items-center gap-1 mt-2">
                    <button type="button" class="btn btn-xs btn-outline-secondary btn-cart-qty" data-delta="-1">−</button>
                    <input type="number" class="form-control form-control-sm text-center cart-qty-input"
                        style="width: 3rem;" min="1" max="{{ $item['stock'] ?: 999 }}"
                        value="{{ (int) $item['quantity'] }}">
                    <button type="button" class="btn btn-xs btn-outline-secondary btn-cart-qty" data-delta="1">+</button>
                    <button type="button" class="btn btn-sm btn-link text-danger ms-auto btn-cart-remove p-0">
                        <i class="ti ti-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    @endforeach
@endif
