@foreach ($cart['items'] as $item)
    @php
        $lineTotal = (float) $item['unit_price'] * (float) $item['quantity'];
        $maxQty = $item['is_stock_item'] ? (int) $item['stock'] : 999;
    @endphp
    <li class="list-group-item checkout-cart-row" data-cart-key="{{ $item['cart_key'] }}">
        <div class="checkout-item-layout">
            @if (!empty($item['image']))
                <img src="{{ $item['image'] }}" class="checkout-item-img flex-shrink-0" alt="">
            @else
                <div class="checkout-item-img flex-shrink-0 d-flex align-items-center justify-content-center text-muted">
                    <i class="ti ti-package"></i>
                </div>
            @endif
            <div class="checkout-item-body min-w-0">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                    <div class="min-w-0">
                        <div class="checkout-item-name fw-semibold">{{ $item['product_name'] }}</div>
                        @if (!empty($item['variant_name']))
                            <div class="text-muted small">{{ $item['variant_name'] }}</div>
                        @endif
                    </div>
                    <div class="fw-bold checkout-line-total flex-shrink-0">
                        Rp {{ number_format($lineTotal, 0, ',', '.') }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="text-primary small fw-bold">
                        Rp {{ number_format($item['unit_price'], 0, ',', '.') }}
                        @if (!empty($item['unit_label']))
                            <span class="text-muted fw-normal">/ {{ $item['unit_label'] }}</span>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <div class="shop-qty-control">
                            <button type="button" class="btn btn-outline-secondary btn-checkout-qty" data-delta="-1" aria-label="Kurangi">−</button>
                            <input type="number" class="form-control form-control-sm text-center checkout-qty-input"
                                min="1" max="{{ $maxQty }}" value="{{ (int) $item['quantity'] }}"
                                aria-label="Jumlah">
                            <button type="button" class="btn btn-outline-secondary btn-checkout-qty" data-delta="1" aria-label="Tambah">+</button>
                        </div>
                        @if (!empty($item['unit_label']))
                            <span class="small text-muted">{{ $item['unit_label'] }}</span>
                        @endif
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 btn-checkout-remove" aria-label="Hapus">
                            <i class="ti ti-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </li>
@endforeach
