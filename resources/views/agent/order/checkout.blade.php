@extends('layouts.agent-order')

@section('title', 'Checkout | ')

@section('shop_body_class')
    shop-checkout-page
@endsection

@section('content')
    <header class="shop-page-header shop-checkout-header">
        <h1 class="shop-page-title mb-0">Checkout</h1>
    </header>

    <div class="row g-3 g-md-4 shop-checkout-layout">
        <div class="col-md-7">
            <div class="card border-0 shadow-sm shop-checkout-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h2 class="h6 mb-0 fw-semibold">Ringkasan pesanan</h2>
                    <span class="badge bg-label-secondary">{{ $summary['item_count'] }} item</span>
                </div>
                <ul class="list-group list-group-flush" id="checkoutItemsList">
                    @include('agent.order._checkout-items', ['cart' => $cart])
                </ul>
                <div class="card-body border-top shop-checkout-summary d-none d-md-block" id="checkoutSummary">
                    @include('agent.order._checkout-summary', ['summary' => $summary, 'shippingAmount' => $shipping['amount']])
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card border-0 shadow-sm shop-checkout-card mb-3">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 mb-0 fw-semibold"><i class="ti ti-truck-delivery me-1"></i>Pengiriman</h2>
                </div>
                <div class="card-body pt-0">
                    <div class="py-3 border-bottom mb-3">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="flex-grow-1">
                                <div class="small text-muted">Dikirim dari</div>
                                <div class="fw-semibold">{{ $shipping['origin_city'] ?: '-' }}</div>
                                @if ($shipping['origin_province'])
                                    <div class="small text-muted">{{ $shipping['origin_province'] }}</div>
                                @endif
                            </div>
                            <i class="ti ti-arrow-right text-muted flex-shrink-0"></i>
                            <div class="flex-grow-1 text-end">
                                <div class="small text-muted">Agen penerima</div>
                                <div class="fw-semibold">{{ $shipping['destination_name'] ?: '-' }}</div>
                                @if ($shipping['destination_city'])
                                    <div class="small text-muted">{{ $shipping['destination_city'] }}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    @unless ($shipping['destination_city'])
                        <div class="alert alert-warning small py-2 mb-3">
                            Kota agen belum diatur pada data master. Hubungi admin untuk melengkapi.
                        </div>
                    @endunless

                    <div class="small text-muted mb-1">Alamat pengiriman</div>
                    <div>{{ $shipping['address'] ?: '-' }}</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm shop-checkout-card shop-checkout-payment-card">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 mb-0 fw-semibold">Pembayaran</h2>
                </div>
                <div class="card-body pt-0 pb-3 pb-lg-4">
                    <form method="POST" action="{{ route('agent-order.checkout.process') }}" id="checkoutForm">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1">Catatan (opsional)</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" maxlength="1000"
                                placeholder="Contoh: titip di pos satpam"></textarea>
                        </div>

                        @if ($hasPaymentOptions)
                            <label class="form-label small text-muted d-block mb-2">Pilih metode pembayaran</label>
                            @include('customer.shop._checkout-payment', [
                                'codMethod' => $codMethod,
                                'codIcon' => $codIcon,
                                'xenditChannelGroups' => $xenditChannelGroups,
                                'standardMethods' => $standardMethods ?? collect(),
                            ])
                        @else
                            <div class="alert alert-warning small mb-0">Tidak ada metode pembayaran aktif untuk cabang ini.</div>
                        @endif

                        <input type="hidden" name="xendit_channel" id="xenditChannel" value="">

                        <button type="submit" class="btn btn-primary w-100 mt-3 d-none d-md-flex align-items-center justify-content-center"
                            id="btnPlaceOrderDesktop" @disabled(! $hasPaymentOptions)>
                            <i class="ti ti-shopping-cart-check me-1"></i> Checkout
                        </button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm shop-checkout-card d-md-none mt-3">
                <div class="card-body py-3" id="checkoutSummaryMobile">
                    @include('agent.order._checkout-summary', ['summary' => $summary, 'shippingAmount' => $shipping['amount']])
                </div>
            </div>

            <a href="{{ route('agent-order.index') }}" class="btn btn-link btn-sm mt-2 ps-0 shop-checkout-back">
                <i class="ti ti-arrow-left"></i> Kembali ke katalog
            </a>
        </div>
    </div>

    <div class="shop-checkout-bar d-md-none" id="shopCheckoutBar">
        <div class="shop-checkout-bar-inner">
            <div class="shop-checkout-bar-total min-w-0">
                <div class="small text-muted">Total</div>
                <div class="fw-bold text-primary text-truncate" id="checkoutBarTotal">
                    Rp {{ number_format($summary['total'], 0, ',', '.') }}
                </div>
            </div>
            <button type="submit" form="checkoutForm" class="btn btn-primary shop-checkout-bar-btn"
                id="btnPlaceOrder" @disabled(! $hasPaymentOptions)>
                Checkout
            </button>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/shop-checkout.js') }}"></script>
    <script>
        function syncXenditChannel() {
            const $sel = $('input[name="payment_method_id"]:checked');
            $('#xenditChannel').val($sel.data('xendit-channel') || '');
        }
        syncXenditChannel();
        $(document).on('change', 'input[name="payment_method_id"]', syncXenditChannel);
        $('#checkoutForm').on('submit', function () {
            syncXenditChannel();
            $('#btnPlaceOrder, #btnPlaceOrderDesktop').prop('disabled', true);
            const loading = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...';
            $('#btnPlaceOrder').html(loading);
            $('#btnPlaceOrderDesktop').html(loading);
        });
    </script>
@endpush
