@extends('layouts.agent-pos')

@section('title', 'POS Agen | ')

@php
    $cashMethodId = ($methodPayments ?? collect())->first(fn ($mp) => strtoupper($mp->code) === 'CASH')?->id;
    $fallbackMethodId = ($methodPayments ?? collect())->first()?->id;
@endphp

@section('content')
    <div class="pos-content-wrapper" id="posWrapper">

        @if (request('payment'))
            <div class="alert alert-{{ request('payment') === 'success' ? 'success' : (request('payment') === 'pending' ? 'warning' : 'danger') }} m-2 mb-0 rounded-0 border-0">
                Pembayaran: {{ request('payment') }}
                @if (request('order_id'))
                    · Order {{ request('order_id') }}
                @endif
            </div>
        @endif

        <div class="pos-top-bar">
            <div class="pos-title-line">
                <span class="pos-title">POS Agen</span>
                <span class="pos-meta-sep">·</span>
                <span>{{ date('d M Y') }}</span>
                <span class="pos-meta-sep">·</span>
                <span id="posClock">{{ date('H:i') }}</span>
                <span class="pos-meta-sep">·</span>
                <span><span id="cartItemCount" class="meta-val">0</span> item</span>
                <span id="posTrxNumberWrap" class="ms-2" style="display:none"><span class="meta-id" id="posTrxNumber"></span></span>
            </div>
        </div>

        <div class="pos-mobile-tabs" id="posMobileTabs" role="tablist" aria-label="POS views">
            <button type="button" class="pos-mobile-tab active" data-pos-view="catalog" role="tab" aria-selected="true">
                <i class="ti ti-layout-grid"></i> Produk
            </button>
            <button type="button" class="pos-mobile-tab" data-pos-view="cart" role="tab" aria-selected="false">
                <i class="ti ti-shopping-cart"></i> Keranjang
                <span class="pos-mobile-tab-badge" id="cartMobileTabBadge">0</span>
            </button>
        </div>

        <div class="pos-main" id="posMain">

            {{-- Panel kiri: katalog --}}
            <div class="pos-catalog">
                <div class="pos-catalog-header">
                    <div class="pos-search">
                        <i class="ti ti-search icon"></i>
                        <input type="text" class="form-control" placeholder="Scan barcode / Cari produk..." id="searchProduct" autocomplete="off">
                    </div>
                    <div class="pos-category-filter">
                        <select id="categoryFilterSelect" class="form-select form-select-sm">
                            <option value="all">Semua Kategori</option>
                            @foreach($productTypes ?? collect() as $productType)
                                <option value="{{ $productType->id }}">{{ $productType->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary pos-filter-btn" id="btnCategoryFilter">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    <div class="pos-categories" id="productTypeTabs" aria-label="Kategori Produk">
                        <span class="pos-category-pill active" data-product-type="all">
                            Semua <span class="pill-count">{{ $products->count() ?? 0 }}</span>
                        </span>
                        @foreach($productTypes ?? collect() as $productType)
                            <span class="pos-category-pill" data-product-type="{{ $productType->id }}">
                                {{ $productType->name }} <span class="pill-count">{{ $productType->products_count ?? 0 }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>

                <div class="pos-product-grid" id="productGrid">
                    @forelse($products ?? collect() as $product)
                        <div class="pos-product-card" data-product-id="{{ $product->id }}" data-product-type-id="{{ $product->nature_id ?? '' }}" data-variants-count="{{ $product->variants_count ?? 0 }}">
                            <div class="p-img-wrap">
                                <img src="{{ $product->image ?: 'https://placehold.co/300x225/f8f9fa/d1d5db?text=+' }}"
                                     alt="{{ $product->name }}"
                                     class="p-img"
                                     loading="lazy"
                                     onerror="this.onerror=null;this.src='https://placehold.co/300x225/f8f9fa/d1d5db?text=+';">
                            </div>
                            <div class="p-body">
                                <div class="p-name" title="{{ $product->name }}">{{ $product->name }}</div>
                                @if($product->code)
                                    <div class="small text-muted">{{ $product->code }}</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="d-flex flex-column align-items-center justify-content-center py-5 w-100" style="grid-column: 1 / -1;">
                            <i class="ti ti-shopping-cart text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-0">Tidak ada produk tersedia</p>
                        </div>
                    @endforelse
                </div>

                @if (($campaigns ?? collect())->isNotEmpty())
                    <div id="posCampaignStrip" class="row g-2 mt-2 px-2 pb-2">
                        @foreach ($campaigns as $c)
                            <div class="col-6 col-xl-4">
                                <div class="card border-0 shadow-sm h-100 bg-label-primary pos-campaign-card" role="button" tabindex="0"
                                     data-promo-id="{{ $c['id'] }}"
                                     data-discount-type="{{ $c['discount_type'] }}"
                                     data-discount-value="{{ $c['discount_value'] }}"
                                     data-min-type="{{ $c['min_type'] }}"
                                     data-min-value="{{ $c['min_value'] }}"
                                     data-target-type="{{ $c['target_type'] }}"
                                     data-target-agent="{{ $c['target_agent_id'] }}"
                                     data-target-reseller="{{ $c['target_reseller_id'] }}"
                                     data-target-reseller-customer="{{ $c['target_reseller_customer_id'] }}"
                                     data-reactivates="{{ $c['reactivates'] ? 1 : 0 }}">
                                    <div class="card-body p-3">
                                        <span class="badge bg-primary mb-1">PROMO</span>
                                        <div class="fw-semibold small text-truncate">{{ $c['name'] }}</div>
                                        <div class="text-muted small">
                                            Diskon {{ $c['discount_label'] }}
                                            · min {{ $c['min_type'] === 'qty' ? rtrim(rtrim(number_format($c['min_value'], 2, '.', ''), '0'), '.').' item' : 'Rp '.number_format($c['min_value'], 0, ',', '.') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Panel kanan: keranjang --}}
            <div class="pos-cart">
                <div class="pos-cart-top">
                    <div class="pos-cart-top-reseller flex-grow-1 min-w-0">
                        <select id="customerSelect" style="width:100%">
                            <option value="">Pelanggan Umum (Walk-in)</option>
                        </select>
                    </div>
                    <div id="priceListWrapper" class="pos-cart-top-price-list flex-shrink-0" data-selected-id="{{ $defaultPriceListId ?? '' }}">
                        <select id="priceListSelect" style="width:160px">
                            <option value="">Daftar Harga</option>
                            @forelse($priceLists ?? collect() as $pl)
                                <option value="{{ $pl->id }}" {{ ($defaultPriceListId ?? '') == $pl->id ? 'selected' : '' }}>{{ $pl->name }}</option>
                            @empty
                            @endforelse
                        </select>
                    </div>
                    <span class="cart-badge"><span id="cartItemCountBadge">0</span></span>
                </div>

                <div class="pos-cart-items" id="cartItems">
                    <div class="pos-empty-cart" id="emptyCart">
                        <i class="ti ti-shopping-cart empty-icon"></i>
                        <p class="mb-1">Keranjang kosong</p>
                        <small>Klik produk untuk menambahkan</small>
                    </div>

                    <div class="pos-cart-item" style="display:none" id="sampleCartItem">
                        <img src="" class="ci-img" alt="" onerror="this.src='https://placehold.co/44x44/f8f9fa/b0b7c3?text=?'">
                        <div class="ci-info">
                            <div class="ci-name">Produk</div>
                            <div class="ci-price">Rp 0</div>
                        </div>
                        <div class="ci-right">
                            <div class="ci-qty">
                                <button type="button" class="btn-minus">-</button>
                                <input type="text" value="1" class="quantity-input" inputmode="numeric" pattern="[0-9]*" readonly tabindex="-1" aria-label="Quantity">
                                <button type="button" class="btn-plus">+</button>
                            </div>
                            <span class="ci-qty-unit"></span>
                            <button type="button" class="ci-disc-btn btn-item-disc" title="Diskon Item">
                                <i class="ti ti-discount-2" style="font-size:0.7rem"></i>
                            </button>
                            <button type="button" class="ci-delete btn-delete" title="Hapus">
                                <i class="ti ti-trash" style="font-size:0.7rem"></i>
                            </button>
                        </div>
                        <div class="ci-bottom">
                            <div class="ci-disc-group">
                                <input type="text" class="item-disc-input" value="0" placeholder="0" autocomplete="off">
                                <div class="ci-disc-toggle">
                                    <button type="button" class="item-disc-type active" data-type="percent">%</button>
                                    <button type="button" class="item-disc-type" data-type="nominal">Rp</button>
                                </div>
                            </div>
                            <span class="ci-disc-label">- <span class="item-disc-display">Rp 0</span></span>
                        </div>
                    </div>
                </div>

                <div id="promoHintRow">
                    <i class="ti ti-gift me-1"></i>
                    <span id="promoHintText">Promo diterapkan</span>
                </div>

                <div id="marketingPromoAlert" class="pos-marketing-promo-alert" style="display:none"></div>

                <div class="pos-toolbar-row">
                    <button type="button" class="pos-tool-btn" id="btnDiscToolbar">Diskon</button>
                    <button type="button" class="pos-tool-btn" id="btnPromoToolbar">Promo</button>
                    <button type="button" class="pos-tool-btn" id="btnNotesToolbar">Catatan</button>
                    <button type="button" class="pos-tool-btn" id="btnClearAll">Hapus Semua</button>
                </div>

                <div class="pos-cart-footer">
                    <div class="pos-summary-row">
                        <span>Subtotal (<span id="subtotalItemCount">0</span> item)</span>
                        <span id="subtotal">Rp 0</span>
                    </div>
                    <div class="pos-summary-row" id="itemDiscRow" style="display:none; color:#cf222e;">
                        <span><i class="ti ti-discount-2" style="font-size:0.7rem"></i> Diskon Item</span>
                        <span>- <span id="itemDiscTotal">Rp 0</span></span>
                    </div>
                    <div class="pos-discount-block">
                        <label>Diskon Transaksi</label>
                        <div class="pos-discount-input-group">
                            <input type="text" id="discountInput" value="0" placeholder="0" autocomplete="off">
                            <div class="pos-disc-toggle">
                                <button type="button" class="disc-type active" data-type="percent">%</button>
                                <button type="button" class="disc-type" data-type="nominal">Rp</button>
                            </div>
                        </div>
                        <div class="pos-discount-display">- <span id="discountDisplay">Rp 0</span></div>
                    </div>
                    <div class="pos-summary-row pos-shipping-row">
                        <span>Ongkir</span>
                        <span class="shipping-val">Rp 0</span>
                    </div>
                    <div class="pos-summary-row total-row">
                        <span>TOTAL</span>
                        <span class="total-val" id="total">Rp 0</span>
                    </div>

                    <div class="pos-action-row">
                        <button type="button" class="btn btn-primary btn-sm pos-btn-bayar w-100" id="btnPayment">
                            <i class="ti ti-cash me-1"></i> BAYAR
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal varian --}}
    <div class="modal fade" id="variantModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pilih Varian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="variantModalBody">
                    <div class="text-center py-4 text-muted" id="variantLoading">
                        <div class="spinner-border" role="status"></div>
                        <p class="mt-2 mb-0">Memuat varian...</p>
                    </div>
                    <div id="variantList" style="display:none"></div>
                    <div class="text-center py-4 text-muted" id="variantEmpty" style="display:none">
                        Tidak ada varian dengan harga untuk daftar harga ini
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal tambah item (unit + harga + qty) --}}
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah ke Keranjang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="addItemVariantName" class="fw-semibold mb-3"></div>
                    <div class="mb-3" id="addItemUnitWrap">
                        <label class="form-label" for="addItemUnitSelect">Unit</label>
                        <select id="addItemUnitSelect" class="form-select"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="addItemPriceInput">Harga</label>
                        <input type="number" id="addItemPriceInput" class="form-control" min="0" step="any" placeholder="0">
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="addItemQtyInput">Qty</label>
                        <input type="number" id="addItemQtyInput" class="form-control" min="1" step="1" value="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnAddItemConfirm">
                        <i class="ti ti-shopping-cart-plus me-1"></i> Tambah ke Keranjang
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal pembayaran --}}
    <div class="modal fade" id="paymentModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down modal-dialog-scrollable">
            <div class="modal-content pay-modal">
                <div class="pay-modal-header">
                    <div>
                        <div class="pay-grand-label">Total Bayar</div>
                        <div class="pay-grand-total" id="payGrandTotal">0</div>
                    </div>
                    <button type="button" class="pay-close-btn" data-bs-dismiss="modal" aria-label="Tutup pembayaran">
                        <span class="d-none d-sm-inline">Keluar [ESC]</span>
                        <span class="d-sm-none"><i class="ti ti-x"></i></span>
                    </button>
                </div>
                <div class="pay-mobile-tabs" id="payMobileTabs" role="tablist" aria-label="Metode pembayaran">
                    <button type="button" class="pay-mobile-tab active" data-pay-panel="cash" role="tab" aria-selected="true">Tunai</button>
                    <button type="button" class="pay-mobile-tab" data-pay-panel="other" role="tab" aria-selected="false">Lainnya</button>
                </div>
                <div class="pay-modal-body" id="payModalBody">
                    <div class="pay-col-cash">
                        <div class="pay-col-title">TUNAI</div>
                        <input type="text" class="pay-cash-input" id="payCashInput" placeholder="0" autocomplete="off">
                        <div class="pay-denom-grid">
                            <button type="button" class="pay-denom" data-val="100000">100rb</button>
                            <button type="button" class="pay-denom" data-val="50000">50rb</button>
                            <button type="button" class="pay-denom" data-val="20000">20rb</button>
                            <button type="button" class="pay-denom" data-val="10000">10rb</button>
                            <button type="button" class="pay-denom" data-val="5000">5rb</button>
                            <button type="button" class="pay-denom" data-val="2000">2rb</button>
                            <button type="button" class="pay-denom" data-val="1000">1rb</button>
                            <button type="button" class="pay-denom" data-val="500">500</button>
                        </div>
                        <div class="pay-denom-actions">
                            <button type="button" class="pay-denom pay-denom-clear" id="payCashClear">Clear</button>
                            <button type="button" class="pay-denom pay-denom-exact" id="payCashExact">Uang Pas</button>
                        </div>
                        <div class="pay-denom-pay-wrap">
                            <button type="button" class="pay-denom pay-denom-cash-pay" id="payCashPay">Tunai</button>
                        </div>
                    </div>
                    <div class="pay-col-other">
                        <div class="pay-col-title">METODE LAIN</div>
                        <div class="pay-other-list">
                            @if(!empty($xenditChannelGroups))
                                <div class="pay-channel-groups" id="payChannelGroups">
                                    @foreach($xenditChannelGroups as $group)
                                        <div class="pay-channel-group">
                                            <div class="pay-channel-group-title">
                                                <i class="ti {{ $group['group_icon'] ?? 'ti-wallet' }}"></i>
                                                {{ $group['title'] }}
                                            </div>
                                            <div class="pay-channel-grid">
                                                @foreach($group['channels'] as $channel)
                                                    <button type="button"
                                                        class="pay-channel-btn"
                                                        data-payment-id="{{ $channel['method_id'] ?? $group['method_id'] }}"
                                                        data-payment-name="{{ $group['title'] }} — {{ $channel['label'] }}"
                                                        data-xendit-channel="{{ $channel['code'] }}">
                                                        <img src="{{ $channel['icon'] }}"
                                                             alt="{{ $channel['label'] }}"
                                                             class="pay-channel-icon"
                                                             loading="lazy"
                                                             onerror="this.onerror=null;this.src='{{ asset('assets/img/payments/default.svg') }}';">
                                                        <span class="pay-channel-label">{{ $channel['label'] }}</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @foreach($nonXenditMethods ?? collect() as $mp)
                                <button type="button" class="pay-other-btn mt-2"
                                    data-payment-id="{{ $mp->id }}"
                                    data-payment-name="{{ $mp->name }}"
                                    data-xendit="0">
                                    <img src="{{ $mp->icon }}"
                                         alt="{{ $mp->name }}"
                                         class="pay-channel-icon"
                                         loading="lazy"
                                         onerror="this.onerror=null;this.src='{{ asset('assets/img/payments/default.svg') }}';">
                                    <span class="pay-channel-label">{{ $mp->name }}</span>
                                </button>
                            @endforeach

                            @if(empty($xenditChannelGroups) && ($nonXenditMethods ?? collect())->isEmpty())
                                <div class="text-muted small text-center py-3">Tidak ada metode pembayaran lain</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Xendit checkout inline --}}
    <div id="xenditCheckoutOverlay" class="xendit-checkout-overlay" aria-hidden="true">
        <div class="xendit-checkout-bar">
            <div>
                <span class="xendit-checkout-label">Pembayaran Xendit</span>
                <strong id="xenditCheckoutOrder">-</strong>
                <span class="text-success fw-bold ms-2" id="xenditCheckoutTotal"></span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small d-none d-md-inline" id="xenditCheckoutStatus">
                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>Menunggu pembayaran...
                </span>
                <button type="button" class="btn btn-sm btn-label-secondary" id="btnXenditClose">
                    <i class="ti ti-x me-1"></i> Tutup
                </button>
            </div>
        </div>
        <iframe id="xenditCheckoutFrame" class="xendit-checkout-frame" title="Pembayaran Xendit"></iframe>
        <div class="xendit-checkout-fallback" id="xenditCheckoutFallback">
            <i class="ti ti-credit-card text-primary mb-3" style="font-size:3rem"></i>
            <p class="mb-3 text-muted">Memuat halaman pembayaran...</p>
            <button type="button" class="btn btn-primary" id="btnXenditContinue">Lanjutkan ke Pembayaran</button>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.agentPosRoutes = {
            productVariants: @json(route('agent-order.pos.product-variants')),
            previewPromo: @json(route('agent-order.pos.preview-promo')),
            payment: @json(route('agent-order.pos.payment')),
            paymentStatus: @json(url('/agent-order/pos/payment')),
            resellerSearch: @json(route('agent-order.pos.resellers-search')),
        };
        window.agentPosConfig = {
            routes: {
                productVariants: window.agentPosRoutes.productVariants,
                previewPromo: window.agentPosRoutes.previewPromo,
                payment: window.agentPosRoutes.payment,
                paymentStatusBase: window.agentPosRoutes.paymentStatus,
                resellerSearch: window.agentPosRoutes.resellerSearch,
            },
            taxRate: {{ (int) ($taxRate ?? 0) }},
            cashMethodId: @json($cashMethodId),
            fallbackMethodId: @json($fallbackMethodId),
        };
        window.agentPosCtx = {
            agentId: @json($agentId ?? null),
        };
    </script>
    <script src="{{ asset('assets/js/agent-pos.js') }}"></script>
@endpush
