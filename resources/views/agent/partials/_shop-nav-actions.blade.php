@auth('customer')
    @php
        $navCustomer = auth('customer')->user();
        $navOrdersActive = request()->routeIs('agent-order.orders') || request()->routeIs('agent-order.orders.show');
        $navPosActive = request()->routeIs('agent-order.pos') || request()->routeIs('agent-order.pos.history*');

        // Desktop menu — every agent destination, so no page is a dead end.
        $navLinks = [
            ['route' => 'agent-order.dashboard', 'label' => 'Beranda',  'icon' => 'ti-home',              'active' => request()->routeIs('agent-order.dashboard')],
            ['route' => 'agent-order.index',     'label' => 'Katalog',  'icon' => 'ti-layout-grid',       'active' => request()->routeIs('agent-order.index')],
            ['route' => 'agent-order.pos',       'label' => 'POS',      'icon' => 'ti-cash',              'active' => $navPosActive],
            ['route' => 'agent-order.materials', 'label' => 'Materi',   'icon' => 'ti-photo',             'active' => request()->routeIs('agent-order.materials')],
            ['route' => 'agent-order.training',  'label' => 'Pelatihan','icon' => 'ti-school',            'active' => request()->routeIs('agent-order.training*')],
            ['route' => 'agent-order.stock',     'label' => 'Stok',     'icon' => 'ti-building-warehouse','active' => request()->routeIs('agent-order.stock')],
            ['route' => 'agent-order.resellers', 'label' => 'Reseller', 'icon' => 'ti-users',             'active' => request()->routeIs('agent-order.resellers')],
        ];

        $unpaidResellerOrderCount = (int) ($unpaidResellerOrderCount ?? 0);

        $navInitials = collect(explode(' ', trim((string) $navCustomer->name)))
            ->filter()
            ->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');
    @endphp

    {{-- Menu utama (desktop). Disembunyikan di POS ($showMenu=false) karena
         header POS sempit dan sudah memuat strip identitas kasir. --}}
    <nav @class([
        'shop-nav-menu align-items-center gap-1 ms-3',
        'd-none d-lg-flex' => ($showMenu ?? true),
        'd-none' => ! ($showMenu ?? true),
    ]) aria-label="Menu agen">
        @foreach ($navLinks as $link)
            <a href="{{ route($link['route']) }}"
                class="shop-nav-link {{ $link['active'] ? 'active' : '' }}"
                @if ($link['active']) aria-current="page" @endif>
                <i class="ti {{ $link['icon'] }}"></i>
                <span>{{ $link['label'] }}</span>
                @if ($link['route'] === 'agent-order.resellers')
                    @include('agent.partials._nav-alert-badge', ['count' => $unpaidResellerOrderCount])
                @endif
            </a>
        @endforeach
    </nav>

    <div class="d-flex align-items-center gap-1 gap-sm-2 ms-auto shop-nav-actions">
        <button type="button" class="shop-nav-circle shop-nav-cart"
            id="navCartBtn" title="Keranjang" aria-label="Keranjang"
            data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
            <i class="ti ti-shopping-cart"></i>
            @if (($navCartSummary['item_count'] ?? 0) > 0)
                <span class="badge bg-danger rounded-pill cart-badge" id="navCartBadge">{{ $navCartSummary['item_count'] }}</span>
            @endif
        </button>

        <a href="{{ route('agent-order.orders') }}"
            class="shop-nav-circle d-none d-lg-inline-flex {{ $navOrdersActive ? 'active' : '' }}"
            title="Pesanan" aria-label="Pesanan">
            <i class="ti ti-receipt"></i>
        </a>

        {{-- Dropdown akun (memakai style .shop-avatar-menu* yang sudah ada di shop.css) --}}
        <div class="dropdown shop-nav-avatar-dropdown">
            <button type="button" class="shop-nav-circle shop-nav-avatar" data-bs-toggle="dropdown"
                data-bs-display="static" aria-expanded="false" title="Akun" aria-label="Akun">
                {{ $navInitials ?: '?' }}
            </button>
            <div class="dropdown-menu dropdown-menu-end shop-avatar-menu p-0">
                <div class="shop-avatar-menu-card">
                    <div class="shop-avatar-menu-header">
                        <span class="shop-avatar-menu-avatar">{{ $navInitials ?: '?' }}</span>
                        <div class="min-w-0">
                            <div class="shop-avatar-menu-name text-truncate">{{ $navCustomer->name }}</div>
                            <div class="shop-avatar-menu-email text-truncate">{{ $navCustomer->email }}</div>
                        </div>
                    </div>
                    <div class="shop-avatar-menu-links">
                        <a href="{{ route('agent-order.orders') }}"
                            class="shop-avatar-menu-item {{ $navOrdersActive ? 'active' : '' }}">
                            <i class="ti ti-receipt"></i> Pesanan Saya
                        </a>
                        <a href="{{ route('agent-order.pos.history', $unpaidResellerOrderCount > 0 ? ['status' => 'unpaid'] : []) }}"
                            class="shop-avatar-menu-item {{ request()->routeIs('agent-order.pos.history*') ? 'active' : '' }}">
                            <i class="ti ti-history"></i> Riwayat POS
                            @include('agent.partials._nav-alert-badge', ['count' => $unpaidResellerOrderCount])
                        </a>
                        <a href="{{ route('agent-order.resellers') }}"
                            class="shop-avatar-menu-item {{ request()->routeIs('agent-order.resellers') ? 'active' : '' }}">
                            <i class="ti ti-users"></i> Reseller Saya
                            @include('agent.partials._nav-alert-badge', ['count' => $unpaidResellerOrderCount])
                        </a>
                        <form method="POST" action="{{ route('agent-order.logout') }}">
                            @csrf
                            <button type="submit" class="shop-avatar-menu-item w-100 border-0 bg-transparent text-start">
                                <i class="ti ti-logout"></i> Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endauth
