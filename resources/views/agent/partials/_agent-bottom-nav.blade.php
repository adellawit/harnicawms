@auth('customer')
    @php
        $bnOrdersActive = request()->routeIs('agent-order.orders') || request()->routeIs('agent-order.orders.show');
        $bnPosActive = request()->routeIs('agent-order.pos') || request()->routeIs('agent-order.pos.history*');
        $bnMoreRoutes = ['agent-order.materials', 'agent-order.training', 'agent-order.stock', 'agent-order.resellers'];
        $bnMoreActive = request()->routeIs('agent-order.materials')
            || request()->routeIs('agent-order.training*')
            || request()->routeIs('agent-order.stock')
            || request()->routeIs('agent-order.resellers');
    @endphp

    <nav class="agent-bottom-nav d-lg-none" aria-label="Menu utama">
        <a href="{{ route('agent-order.dashboard') }}"
            class="agent-bottom-nav-item {{ request()->routeIs('agent-order.dashboard') ? 'active' : '' }}">
            <i class="ti ti-home"></i>
            <span>Beranda</span>
        </a>
        <a href="{{ route('agent-order.index') }}"
            class="agent-bottom-nav-item {{ request()->routeIs('agent-order.index') ? 'active' : '' }}">
            <i class="ti ti-layout-grid"></i>
            <span>Katalog</span>
        </a>
        <a href="{{ route('agent-order.pos') }}"
            class="agent-bottom-nav-item {{ $bnPosActive ? 'active' : '' }}">
            <i class="ti ti-cash"></i>
            <span>POS</span>
        </a>
        <a href="{{ route('agent-order.orders') }}"
            class="agent-bottom-nav-item {{ $bnOrdersActive ? 'active' : '' }}">
            <i class="ti ti-receipt"></i>
            <span>Pesanan</span>
        </a>
        <button type="button" class="agent-bottom-nav-item {{ $bnMoreActive ? 'active' : '' }}"
            data-bs-toggle="offcanvas" data-bs-target="#agentMoreMenu" aria-controls="agentMoreMenu">
            <i class="ti ti-dots"></i>
            <span>Lainnya</span>
            @include('agent.partials._nav-alert-badge', ['count' => $unpaidResellerOrderCount ?? 0])
        </button>
    </nav>

    <div class="offcanvas offcanvas-bottom agent-more-menu" tabindex="-1" id="agentMoreMenu"
        aria-labelledby="agentMoreMenuLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="agentMoreMenuLabel">Menu lainnya</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
        </div>
        <div class="offcanvas-body pt-0">
            <div class="agent-more-grid">
                <a href="{{ route('agent-order.materials') }}" class="agent-more-item">
                    <span class="agent-more-icon agent-dashboard-nav-icon-info"><i class="ti ti-photo"></i></span>
                    <span>Materi Pemasaran</span>
                </a>
                <a href="{{ route('agent-order.training') }}" class="agent-more-item">
                    <span class="agent-more-icon agent-dashboard-nav-icon-secondary"><i class="ti ti-school"></i></span>
                    <span>Pelatihan</span>
                </a>
                <a href="{{ route('agent-order.stock') }}" class="agent-more-item">
                    <span class="agent-more-icon agent-dashboard-nav-icon-success"><i class="ti ti-building-warehouse"></i></span>
                    <span>Stok Gudang</span>
                </a>
                <a href="{{ route('agent-order.resellers') }}" class="agent-more-item">
                    <span class="agent-more-icon agent-dashboard-nav-icon-primary"><i class="ti ti-users"></i></span>
                    <span>Reseller Saya</span>
                    @include('agent.partials._nav-alert-badge', ['count' => $unpaidResellerOrderCount ?? 0])
                </a>
                <a href="{{ route('agent-order.pos.history', ($unpaidResellerOrderCount ?? 0) > 0 ? ['status' => 'unpaid'] : []) }}" class="agent-more-item">
                    <span class="agent-more-icon agent-dashboard-nav-icon-primary"><i class="ti ti-history"></i></span>
                    <span>Riwayat POS</span>
                    @include('agent.partials._nav-alert-badge', ['count' => $unpaidResellerOrderCount ?? 0])
                </a>
                <form method="POST" action="{{ route('agent-order.logout') }}" class="agent-more-item-form">
                    @csrf
                    <button type="submit" class="agent-more-item w-100 border-0 bg-transparent">
                        <span class="agent-more-icon agent-more-icon-danger"><i class="ti ti-logout"></i></span>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
@endauth
