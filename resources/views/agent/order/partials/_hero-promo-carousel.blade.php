{{--
    Shared hero promo carousel — used on both the catalog (agent.order.index) and
    dashboard (agent.order.dashboard) pages so the two share the same "storefront"
    first impression.

    Uses real marketing-asset photos (already uploaded/managed by admin under
    Materi Pemasaran) when available, so the banner shows genuine imagery instead
    of a placeholder. Falls back to the original static gradient slides when no
    image assets exist yet, so the hero never looks empty/broken.
--}}
@php
    $heroAssets = $heroAssets ?? collect();
    $heroSlideCount = $heroAssets->isNotEmpty() ? $heroAssets->count() : 3;
@endphp

<div id="heroPromoCarousel" class="carousel slide shop-hero mb-3" data-bs-ride="carousel">
    <div class="carousel-inner rounded-3 overflow-hidden">
        @if ($heroAssets->isNotEmpty())
            @foreach ($heroAssets as $i => $asset)
                @php($heroImage = $asset->thumbnail_url ?: $asset->file_url)
                <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                    {{-- Real <img> (not CSS background-image) so a broken/missing file can be
                         detected via onerror and gracefully fall back to a brand gradient
                         slide, instead of showing up as an empty/broken image. --}}
                    <div class="shop-hero-slide shop-hero-slide-photo">
                        <img src="{{ $heroImage }}" alt="" class="shop-hero-slide-img"
                            onerror="this.closest('.shop-hero-slide').classList.add('shop-hero-slide-{{ ($i % 3) + 1 }}'); this.remove();">
                        <div class="shop-hero-overlay">
                            <span class="badge bg-light text-dark mb-2">MATERI PEMASARAN</span>
                            <h2 class="h4 text-white fw-bold mb-1">{{ $asset->title }}</h2>
                            <a href="{{ route('agent-order.materials') }}" class="small text-white text-decoration-underline">
                                Lihat materi pemasaran →
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="carousel-item active">
                <div class="shop-hero-slide shop-hero-slide-1">
                    <div class="shop-hero-overlay">
                        <span class="badge bg-light text-dark mb-2">CAMPAIGN</span>
                        <h2 class="h4 text-white fw-bold mb-1">Bundling Hemat Juli</h2>
                        <p class="text-white-50 mb-0 small">Order paket family &amp; dapatkan harga spesial distributor.</p>
                    </div>
                </div>
            </div>
            <div class="carousel-item">
                <div class="shop-hero-slide shop-hero-slide-2">
                    <div class="shop-hero-overlay">
                        <span class="badge bg-light text-dark mb-2">PRODUK JADI</span>
                        <h2 class="h4 text-white fw-bold mb-1">Katalog Terbaru</h2>
                        <p class="text-white-50 mb-0 small">Pilih varian, order online, kirim ke alamat agen Anda.</p>
                    </div>
                </div>
            </div>
            <div class="carousel-item">
                <div class="shop-hero-slide shop-hero-slide-3">
                    <div class="shop-hero-overlay">
                        <span class="badge bg-light text-dark mb-2">AGEN</span>
                        <h2 class="h4 text-white fw-bold mb-1">Order ke Distributor</h2>
                        <p class="text-white-50 mb-0 small">Pesan produk jadi langsung dari portal agen.</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($heroSlideCount > 1)
        <button class="carousel-control-prev" type="button" data-bs-target="#heroPromoCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroPromoCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    @endif
</div>
