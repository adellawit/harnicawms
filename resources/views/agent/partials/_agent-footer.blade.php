@php
    $footerCompany = $shopCompanyName ?? config('app.name');
    $footerPhone = $shopCompanyPhone ?? null;
    $footerEmail = $shopCompanyEmail ?? null;
    $footerAddress = $shopCompanyAddress ?? null;

    // Normalise the stored phone into a wa.me-compatible number (62…).
    $footerPhoneDigits = $footerPhone ? preg_replace('/\D+/', '', $footerPhone) : null;
    $footerWhatsapp = null;
    if ($footerPhoneDigits) {
        $footerWhatsapp = str_starts_with($footerPhoneDigits, '0')
            ? '62' . substr($footerPhoneDigits, 1)
            : $footerPhoneDigits;
    }

    $footerLinks = [
        ['route' => 'agent-order.dashboard', 'label' => 'Beranda'],
        ['route' => 'agent-order.index',     'label' => 'Order ke Distributor'],
        ['route' => 'agent-order.pos',       'label' => 'POS / Kasir'],
        ['route' => 'agent-order.materials', 'label' => 'Materi Pemasaran'],
        ['route' => 'agent-order.training',  'label' => 'Pelatihan'],
        ['route' => 'agent-order.stock',     'label' => 'Stok Gudang'],
        ['route' => 'agent-order.orders',    'label' => 'Pesanan Saya'],
        ['route' => 'agent-order.resellers', 'label' => 'Reseller Saya'],
    ];
@endphp

<footer class="shop-footer border-top">
    <div class="container shop-main py-4">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="fw-bold mb-2">{{ $footerCompany }}</div>
                <p class="text-muted small mb-3">
                    Distributor resmi produk jadi untuk jaringan agen. Order online, kirim ke alamat agen Anda.
                </p>
                @if (filled($footerAddress))
                    <div class="d-flex align-items-start gap-2 small text-muted">
                        <i class="ti ti-map-pin mt-1 flex-shrink-0"></i>
                        <span>{{ $footerAddress }}</span>
                    </div>
                    <a class="small d-inline-flex align-items-center gap-1 mt-2" target="_blank" rel="noopener"
                        href="https://www.google.com/maps/search/?api=1&query={{ urlencode($footerAddress) }}">
                        <i class="ti ti-external-link"></i> Buka di Google Maps
                    </a>
                @endif
            </div>

            @auth('customer')
                <div class="col-lg-3 col-md-6">
                    <div class="shop-footer-heading">Menu Agen</div>
                    <ul class="list-unstyled small mb-0 shop-footer-links">
                        @foreach ($footerLinks as $link)
                            <li><a href="{{ route($link['route']) }}">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endauth

            <div class="col-lg-3 col-md-6">
                <div class="shop-footer-heading">Butuh Bantuan?</div>
                <ul class="list-unstyled small mb-0 shop-footer-links">
                    @if (filled($footerPhone))
                        <li>
                            <a href="tel:{{ $footerPhoneDigits }}">
                                <i class="ti ti-phone me-1"></i>{{ $footerPhone }}
                            </a>
                        </li>
                    @endif
                    @if (filled($footerEmail))
                        <li>
                            <a href="mailto:{{ $footerEmail }}">
                                <i class="ti ti-mail me-1"></i>{{ $footerEmail }}
                            </a>
                        </li>
                    @endif
                </ul>
                @if ($footerWhatsapp)
                    <a href="https://wa.me/{{ $footerWhatsapp }}" target="_blank" rel="noopener"
                        class="btn btn-sm btn-success mt-3 d-inline-flex align-items-center gap-1">
                        <i class="ti ti-brand-whatsapp"></i> Chat WhatsApp
                    </a>
                @endif
            </div>

            <div class="col-lg-2 col-md-6">
                <div class="shop-footer-heading">Jam &amp; Pengiriman</div>
                <ul class="list-unstyled small text-muted mb-0 shop-footer-info">
                    <li class="d-flex align-items-start gap-2">
                        <i class="ti ti-clock mt-1 flex-shrink-0"></i>
                        <span>Senin&ndash;Jumat 08.00&ndash;17.00<br>Sabtu 08.00&ndash;13.00</span>
                    </li>
                    <li class="d-flex align-items-start gap-2">
                        <i class="ti ti-truck-delivery mt-1 flex-shrink-0"></i>
                        <span>Order sebelum 14.00 diproses hari yang sama.</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="text-center text-muted small mt-4">&copy; {{ date('Y') }} {{ $footerCompany }}</div>
    </div>
</footer>
