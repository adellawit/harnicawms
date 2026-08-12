@auth('customer')
    @php
        $navCustomer = auth('customer')->user();
        $navOrdersActive = request()->routeIs('agent-order.orders') || request()->routeIs('agent-order.orders.show');
        $navPosActive = request()->routeIs('agent-order.pos*');
    @endphp
    <div class="d-flex align-items-center gap-1 gap-sm-2 ms-auto shop-nav-actions">
        <a href="{{ route('agent-order.dashboard') }}"
            class="btn btn-sm btn-light {{ request()->routeIs('agent-order.dashboard') ? 'active' : '' }}"
            title="Beranda" aria-label="Beranda">
            <i class="ti ti-home"></i>
            <span class="d-none d-md-inline ms-1">Beranda</span>
        </a>
        <a href="{{ route('agent-order.index') }}" class="btn btn-sm btn-light {{ request()->routeIs('agent-order.index') ? 'active' : '' }}"
            title="Katalog" aria-label="Katalog">
            <i class="ti ti-layout-grid"></i>
            <span class="d-none d-md-inline ms-1">Katalog</span>
        </a>
        <a href="{{ route('agent-order.pos') }}"
            class="shop-nav-circle {{ $navPosActive ? 'active' : '' }}"
            title="POS" aria-label="POS">
            <i class="ti ti-cash"></i>
        </a>
        <button type="button" class="shop-nav-circle shop-nav-cart"
            id="navCartBtn" title="Keranjang" aria-label="Keranjang"
            data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
            <i class="ti ti-shopping-cart"></i>
            @if (($navCartSummary['item_count'] ?? 0) > 0)
                <span class="badge bg-danger rounded-pill cart-badge" id="navCartBadge">{{ $navCartSummary['item_count'] }}</span>
            @endif
        </button>
        <a href="{{ route('agent-order.orders') }}"
            class="shop-nav-circle {{ $navOrdersActive ? 'active' : '' }}"
            title="Pesanan" aria-label="Pesanan">
            <i class="ti ti-receipt"></i>
        </a>
        <form method="POST" action="{{ route('agent-order.logout') }}" class="d-inline">
            @csrf
            <button type="submit" class="shop-nav-circle border-0 bg-transparent" title="Logout" aria-label="Logout">
                <i class="ti ti-logout"></i>
            </button>
        </form>
    </div>
@endauth
