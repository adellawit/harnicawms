@php
    $defaultPayIcon = asset('assets/img/payments/default.svg');
    $pickFirst = true;
@endphp

<div class="shop-pay-groups" role="radiogroup" aria-label="Metode pembayaran">
    @if ($codMethod)
        <div class="shop-pay-group">
            <div class="shop-pay-group-title">
                <i class="ti ti-truck-delivery"></i>
                <span>Bayar di tempat</span>
            </div>
            <div class="shop-pay-grid">
                <label class="shop-pay-option">
                    <input type="radio" name="payment_method_id" class="shop-pay-radio"
                        value="{{ $codMethod->id }}" data-xendit-channel=""
                        @checked($pickFirst)>
                    @php $pickFirst = false; @endphp
                    <span class="shop-pay-option-card">
                        <img src="{{ $codIcon }}" alt="{{ $codMethod->name }}" class="shop-pay-icon" loading="lazy"
                            onerror="this.onerror=null;this.src='{{ $defaultPayIcon }}';">
                        <span class="shop-pay-label">{{ $codMethod->name }}</span>
                        <span class="shop-pay-sub">Bayar saat terima</span>
                    </span>
                </label>
            </div>
        </div>
    @endif

    @if (($standardMethods ?? collect())->isNotEmpty())
        <div class="shop-pay-group">
            <div class="shop-pay-group-title">
                <i class="ti ti-cash"></i>
                <span>Metode pembayaran</span>
            </div>
            <div class="shop-pay-grid">
                @foreach ($standardMethods as $method)
                    <label class="shop-pay-option">
                        <input type="radio" name="payment_method_id" class="shop-pay-radio"
                            value="{{ $method->id }}" data-xendit-channel=""
                            @checked($pickFirst)>
                        @php $pickFirst = false; @endphp
                        <span class="shop-pay-option-card">
                            <img src="{{ asset('assets/img/payments/default.svg') }}" alt="{{ $method->name }}" class="shop-pay-icon" loading="lazy">
                            <span class="shop-pay-label">{{ $method->name }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    @foreach ($xenditChannelGroups as $group)
        @if (!empty($group['channels']))
            <div class="shop-pay-group">
                <div class="shop-pay-group-title">
                    <i class="ti {{ $group['group_icon'] ?? 'ti-wallet' }}"></i>
                    <span>{{ $group['title'] ?? 'Pembayaran online' }}</span>
                </div>
                <div class="shop-pay-grid">
                    @foreach ($group['channels'] as $channel)
                        <label class="shop-pay-option">
                            <input type="radio" name="payment_method_id" class="shop-pay-radio"
                                value="{{ $channel['method_id'] ?? $group['method_id'] }}"
                                data-xendit-channel="{{ $channel['code'] }}"
                                @checked($pickFirst)>
                            @php $pickFirst = false; @endphp
                            <span class="shop-pay-option-card">
                                <img src="{{ $channel['icon'] ?? $defaultPayIcon }}" alt="{{ $channel['label'] }}"
                                    class="shop-pay-icon" loading="lazy"
                                    onerror="this.onerror=null;this.src='{{ $defaultPayIcon }}';">
                                <span class="shop-pay-label">{{ $channel['label'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>
