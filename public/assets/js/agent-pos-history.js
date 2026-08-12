(function ($) {
    'use strict';

    var cfg = window.agentPosHistoryConfig || {};
    var payModal = null;
    var pendingOrderId = null;
    var pendingOrderTotal = 0;

    function formatRp(n) {
        return 'Rp ' + (parseFloat(n) || 0).toLocaleString('id-ID');
    }

    function setPayMobilePanel(panel) {
        var $body = $('#historyPayModalBody');
        var view = panel === 'other' ? 'other' : 'cash';
        if (view === 'other') {
            $body.addClass('pay-mobile-view-other');
        } else {
            $body.removeClass('pay-mobile-view-other');
        }
        $('#historyPayMobileTabs .pay-mobile-tab').removeClass('active').attr('aria-selected', 'false');
        $('#historyPayMobileTabs .pay-mobile-tab[data-pay-panel="' + view + '"]').addClass('active').attr('aria-selected', 'true');
    }

    function openPayModal(orderId, salesNumber, total) {
        pendingOrderId = orderId;
        pendingOrderTotal = parseFloat(total) || 0;
        $('#historyPayGrandTotal').text(formatRp(pendingOrderTotal));
        $('#historyPayOrderNumber').text(salesNumber || '');
        $('#historyPayCashInput').val('');
        setPayMobilePanel('cash');
        if (payModal) {
            payModal.show();
        }
    }

    function setPayButtonsDisabled(disabled) {
        $('#historyPayModal .pay-denom-cash-pay, #historyPayModal .history-pay-other-btn, #historyPayModal .history-pay-channel-btn')
            .prop('disabled', disabled);
        if (disabled) {
            $('#historyPayCashPay').html('<span class="spinner-border spinner-border-sm me-1"></span>Memproses...');
        } else {
            $('#historyPayCashPay').text('Tunai');
        }
    }

    function redirectWithSuccess() {
        var url = new URL(window.location.href);
        url.searchParams.set('paid', '1');
        window.location.href = url.toString();
    }

    function submitPayPending(methodId, methodName, amountPaid, xenditChannel) {
        if (!pendingOrderId) {
            return;
        }

        var payload = {
            payment_method_id: methodId,
            amount_paid: amountPaid,
        };
        if (xenditChannel) {
            payload.xendit_channel = xenditChannel;
        }

        setPayButtonsDisabled(true);

        $.ajax({
            url: cfg.payPendingBase + '/' + pendingOrderId + '/pay-pending',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                Accept: 'application/json',
            },
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function (res) {
                if (res.success && res.xendit && res.data && res.data.invoice_url) {
                    if (payModal) {
                        payModal.hide();
                    }
                    window.location.href = res.data.invoice_url;
                    return;
                }

                if (payModal) {
                    payModal.hide();
                }

                if (res.success) {
                    var change = res.data && res.data.change_amount ? res.data.change_amount : 0;
                    var html = 'Transaksi <strong>' + (res.data?.sales_number || '') + '</strong> lunas.';
                    if (change > 0) {
                        html += '<br>Kembalian: <strong>' + formatRp(change) + '</strong>';
                    }
                    Swal.fire({
                        title: 'Pembayaran Berhasil',
                        html: html,
                        icon: 'success',
                        confirmButtonText: 'OK',
                        customClass: { confirmButton: 'btn btn-success' },
                        buttonsStyling: false,
                    }).then(function () {
                        redirectWithSuccess();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: res.message || 'Terjadi kesalahan.',
                        icon: 'error',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false,
                    });
                }
            },
            error: function (xhr) {
                if (payModal) {
                    payModal.hide();
                }
                var msg = xhr.responseJSON?.message || 'Pembayaran gagal.';
                Swal.fire({
                    title: 'Error',
                    text: msg,
                    icon: 'error',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
            },
            complete: function () {
                setPayButtonsDisabled(false);
            },
        });
    }

    $(function () {
        var modalEl = document.getElementById('historyPayModal');
        if (modalEl) {
            payModal = new bootstrap.Modal(modalEl);
        }

        $(document).on('click', '.btn-history-pay', function () {
            openPayModal(
                $(this).data('order-id'),
                $(this).data('sales-number'),
                $(this).data('total')
            );
        });

        $('#historyPayMobileTabs').on('click', '.pay-mobile-tab', function () {
            setPayMobilePanel($(this).data('pay-panel'));
        });

        $(document).on('click', '.history-pay-denom[data-val]', function () {
            var val = parseInt($(this).data('val'), 10);
            var cur = parseInt($('#historyPayCashInput').val().replace(/\D/g, ''), 10) || 0;
            $('#historyPayCashInput').val((cur + val).toLocaleString('id-ID'));
        });

        $('#historyPayCashClear').on('click', function () {
            $('#historyPayCashInput').val('');
        });

        $('#historyPayCashExact').on('click', function () {
            $('#historyPayCashInput').val(Math.round(pendingOrderTotal).toLocaleString('id-ID'));
        });

        $('#historyPayCashInput').on('input', function () {
            var raw = this.value.replace(/\D/g, '');
            this.value = raw === '' ? '' : parseInt(raw, 10).toLocaleString('id-ID');
        });

        $('#historyPayCashPay').on('click', function () {
            var cashAmount = parseInt($('#historyPayCashInput').val().replace(/\D/g, ''), 10) || 0;
            if (cashAmount < pendingOrderTotal) {
                Swal.fire({
                    icon: 'warning',
                    text: 'Nominal tunai kurang dari total (' + formatRp(pendingOrderTotal) + ')',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
                return;
            }
            if (!cfg.cashMethodId) {
                Swal.fire({
                    icon: 'error',
                    text: 'Metode tunai tidak tersedia.',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
                return;
            }
            submitPayPending(cfg.cashMethodId, 'Tunai', cashAmount, null);
        });

        $(document).on('click', '.history-pay-channel-btn', function () {
            submitPayPending(
                $(this).data('payment-id'),
                $(this).data('payment-name'),
                pendingOrderTotal,
                $(this).data('xendit-channel') || null
            );
        });

        $(document).on('click', '.history-pay-other-btn', function () {
            submitPayPending(
                $(this).data('payment-id'),
                $(this).data('payment-name'),
                pendingOrderTotal,
                null
            );
        });
    });
})(jQuery);
