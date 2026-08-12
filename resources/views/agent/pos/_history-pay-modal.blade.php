{{-- Modal pembayaran pending order (History POS) --}}
<div class="modal fade" id="historyPayModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down modal-dialog-scrollable">
        <div class="modal-content pay-modal">
            <div class="pay-modal-header">
                <div>
                    <div class="pay-grand-label">Total Bayar</div>
                    <div class="pay-grand-total" id="historyPayGrandTotal">0</div>
                    <div class="small text-muted mt-1" id="historyPayOrderNumber"></div>
                </div>
                <button type="button" class="pay-close-btn" data-bs-dismiss="modal" aria-label="Tutup pembayaran">
                    <span class="d-none d-sm-inline">Keluar [ESC]</span>
                    <span class="d-sm-none"><i class="ti ti-x"></i></span>
                </button>
            </div>
            <div class="pay-mobile-tabs" id="historyPayMobileTabs" role="tablist" aria-label="Metode pembayaran">
                <button type="button" class="pay-mobile-tab active" data-pay-panel="cash" role="tab" aria-selected="true">Tunai</button>
                <button type="button" class="pay-mobile-tab" data-pay-panel="other" role="tab" aria-selected="false">Lainnya</button>
            </div>
            <div class="pay-modal-body" id="historyPayModalBody">
                <div class="pay-col-cash">
                    <div class="pay-col-title">TUNAI</div>
                    <input type="text" class="pay-cash-input" id="historyPayCashInput" placeholder="0" autocomplete="off">
                    <div class="pay-denom-grid">
                        <button type="button" class="pay-denom history-pay-denom" data-val="100000">100rb</button>
                        <button type="button" class="pay-denom history-pay-denom" data-val="50000">50rb</button>
                        <button type="button" class="pay-denom history-pay-denom" data-val="20000">20rb</button>
                        <button type="button" class="pay-denom history-pay-denom" data-val="10000">10rb</button>
                        <button type="button" class="pay-denom history-pay-denom" data-val="5000">5rb</button>
                        <button type="button" class="pay-denom history-pay-denom" data-val="2000">2rb</button>
                        <button type="button" class="pay-denom history-pay-denom" data-val="1000">1rb</button>
                        <button type="button" class="pay-denom history-pay-denom" data-val="500">500</button>
                    </div>
                    <div class="pay-denom-actions">
                        <button type="button" class="pay-denom pay-denom-clear" id="historyPayCashClear">Clear</button>
                        <button type="button" class="pay-denom pay-denom-exact" id="historyPayCashExact">Uang Pas</button>
                    </div>
                    <div class="pay-denom-pay-wrap">
                        <button type="button" class="pay-denom pay-denom-cash-pay" id="historyPayCashPay">Tunai</button>
                    </div>
                </div>
                <div class="pay-col-other">
                    <div class="pay-col-title">METODE LAIN</div>
                    <div class="pay-other-list">
                        @if(!empty($xenditChannelGroups))
                            <div class="pay-channel-groups">
                                @foreach($xenditChannelGroups as $group)
                                    <div class="pay-channel-group">
                                        <div class="pay-channel-group-title">
                                            <i class="ti {{ $group['group_icon'] ?? 'ti-wallet' }}"></i>
                                            {{ $group['title'] }}
                                        </div>
                                        <div class="pay-channel-grid">
                                            @foreach($group['channels'] as $channel)
                                                <button type="button"
                                                    class="pay-channel-btn history-pay-channel-btn"
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
                            <button type="button" class="pay-other-btn history-pay-other-btn mt-2"
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
