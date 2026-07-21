@extends('layouts.agent-order')

@section('title', 'Checkout | ')

@section('shop_body_class')
    shop-checkout-page
@endsection

@section('content')
    <header class="shop-page-header shop-checkout-header">
        <h1 class="shop-page-title mb-0">Checkout</h1>
    </header>

    <div class="row g-3 g-lg-4 shop-checkout-layout">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm shop-checkout-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h2 class="h6 mb-0 fw-semibold">Ringkasan pesanan</h2>
                    <span class="badge bg-label-secondary">{{ $summary['item_count'] }} item</span>
                </div>
                <ul class="list-group list-group-flush" id="checkoutItemsList">
                    @include('customer.shop._checkout-items', ['cart' => $cart])
                </ul>
                <div class="card-body border-top shop-checkout-summary d-none d-lg-block" id="checkoutSummary">
                    @include('customer.shop._checkout-summary', ['summary' => $summary])
                </div>
            </div>
        </div>

        <div class="col-lg-5">
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

                        <button type="submit" class="btn btn-primary w-100 mt-3 d-none d-lg-flex align-items-center justify-content-center"
                            id="btnPlaceOrderDesktop" @disabled(! $hasPaymentOptions)>
                            <i class="ti ti-shopping-cart-check me-1"></i> Checkout
                        </button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm shop-checkout-card d-lg-none mt-3">
                <div class="card-body py-3" id="checkoutSummaryMobile">
                    @include('customer.shop._checkout-summary', ['summary' => $summary])
                </div>
            </div>

            <a href="{{ route('agent-order.index') }}" class="btn btn-link btn-sm mt-2 ps-0 shop-checkout-back">
                <i class="ti ti-arrow-left"></i> Kembali ke katalog
            </a>
        </div>
    </div>

    <div class="shop-checkout-bar d-lg-none" id="shopCheckoutBar">
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
