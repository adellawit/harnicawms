@extends('layouts.agent-order')

@section('title', 'Checkout | ')

@section('shop_body_class')
    shop-checkout-page
@endsection

@push('body-top')
    <div class="bg-shapes" aria-hidden="true">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
    </div>
@endpush

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
                    <div class="mb-3">{{ $shipping['address'] ?: '-' }}</div>

                    <div class="small text-muted mb-2">Pilih kurir @if($weightKg > 0)<span class="text-muted">(est. {{ number_format($weightKg, 2, ',', '.') }} kg)</span>@endif</div>
                    @if (! $shippingAvailable)
                        <div class="alert alert-warning small mb-0">
                            Ongkir belum tersedia untuk kota tujuan Anda. Hubungi admin untuk menambahkan tarif.
                        </div>
                    @else
                        @foreach ($shippingOptions as $opt)
                            <label class="d-flex justify-content-between align-items-start border rounded p-2 mb-2 shop-shipping-option">
                                <span class="me-2">
                                    <input type="radio" name="shipping_rate_id" value="{{ $opt['rate_id'] }}" required
                                           form="checkoutForm"
                                           data-amount="{{ $opt['amount'] }}"
                                           @checked($loop->first)>
                                    {{ $opt['courier_label'] }} · {{ $opt['service_name'] ?: $opt['service_code'] }}
                                    @if ($opt['etd'])
                                        <small class="text-muted d-block">({{ $opt['etd'] }})</small>
                                    @endif
                                </span>
                                <strong class="text-nowrap">Rp {{ number_format($opt['amount'], 0, ',', '.') }}</strong>
                            </label>
                        @endforeach
                    @endif
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
                                'manualTransferMethod' => $manualTransferMethod ?? null,
                            ])
                        @else
                            <div class="alert alert-warning small mb-0">Tidak ada metode pembayaran aktif untuk cabang ini.</div>
                        @endif

                        <input type="hidden" name="xendit_channel" id="xenditChannel" value="">

                        <button type="submit" class="btn btn-primary w-100 mt-3 d-none d-md-flex align-items-center justify-content-center"
                            id="btnPlaceOrderDesktop" @disabled(! $hasPaymentOptions || ! $shippingAvailable)>
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
                id="btnPlaceOrder" @disabled(! $hasPaymentOptions || ! $shippingAvailable)>
                Checkout
            </button>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/shop-checkout.js') }}"></script>
    <script>
        const checkoutBaseTotal = {{ $summary['total'] }};
        const checkoutShippingAvailable = @json($shippingAvailable);
        const checkoutHasPayment = @json($hasPaymentOptions);

        function formatCheckoutRp(amount) {
            return 'Rp ' + Number(amount || 0).toLocaleString('id-ID');
        }

        function selectedShippingAmount() {
            const $selected = $('input[name="shipping_rate_id"]:checked');
            return $selected.length ? parseFloat($selected.data('amount')) || 0 : 0;
        }

        function updateCheckoutTotals() {
            const shipping = checkoutShippingAvailable ? selectedShippingAmount() : 0;
            const total = checkoutBaseTotal + shipping;
            $('#checkoutShipping').text(formatCheckoutRp(shipping));
            $('#checkoutTotal, #checkoutBarTotal').text(formatCheckoutRp(total));
        }

        function updateCheckoutPayButtons() {
            const canPay = checkoutHasPayment
                && checkoutShippingAvailable
                && $('input[name="shipping_rate_id"]:checked').length > 0;
            $('#btnPlaceOrder, #btnPlaceOrderDesktop').prop('disabled', !canPay);
        }

        function syncXenditChannel() {
            const $sel = $('input[name="payment_method_id"]:checked');
            $('#xenditChannel').val($sel.data('xendit-channel') || '');
        }

        syncXenditChannel();
        updateCheckoutTotals();
        updateCheckoutPayButtons();

        $(document).on('change', 'input[name="payment_method_id"]', syncXenditChannel);
        $(document).on('change', 'input[name="shipping_rate_id"]', function () {
            updateCheckoutTotals();
            updateCheckoutPayButtons();
        });

        $('#checkoutForm').on('submit', function () {
            syncXenditChannel();
            $('#btnPlaceOrder, #btnPlaceOrderDesktop').prop('disabled', true);
            const loading = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...';
            $('#btnPlaceOrder').html(loading);
            $('#btnPlaceOrderDesktop').html(loading);
        });
    </script>
@endpush
