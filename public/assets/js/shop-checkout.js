(function ($) {
    'use strict';

    const fmtRp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

    function cartUpdate(cartKey, quantity) {
        return $.post(window.shopRoutes.cartUpdate, { cart_key: cartKey, quantity: quantity });
    }

    function cartRemove(cartKey) {
        return $.post(window.shopRoutes.cartRemove, { cart_key: cartKey });
    }

    function reloadCheckout() {
        window.location.reload();
    }

    $(document).on('click', '.btn-checkout-qty', function () {
        const $row = $(this).closest('.checkout-cart-row');
        const key = $row.data('cart-key');
        const $input = $row.find('.checkout-qty-input');
        let qty = parseInt($input.val(), 10) + parseInt($(this).data('delta'), 10);
        if (qty < 1) {
            qty = 0;
        }
        $row.addClass('opacity-50');
        cartUpdate(key, qty)
            .done(function (res) {
                if (qty === 0 || !res.cart?.items?.length) {
                    window.location.href = window.shopRoutes.shop || '/shop';
                    return;
                }
                reloadCheckout();
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON?.message || 'Gagal mengubah jumlah');
                $row.removeClass('opacity-50');
            });
    });

    $(document).on('change', '.checkout-qty-input', function () {
        const $row = $(this).closest('.checkout-cart-row');
        const key = $row.data('cart-key');
        let qty = parseInt($(this).val(), 10) || 0;
        $row.addClass('opacity-50');
        cartUpdate(key, qty)
            .done(function (res) {
                if (qty === 0 || !res.cart?.items?.length) {
                    window.location.href = window.shopRoutes.shop || '/shop';
                    return;
                }
                reloadCheckout();
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON?.message || 'Gagal mengubah jumlah');
                $row.removeClass('opacity-50');
            });
    });

    $(document).on('click', '.btn-checkout-remove', function () {
        if (!confirm('Hapus item dari keranjang?')) {
            return;
        }
        const key = $(this).closest('.checkout-cart-row').data('cart-key');
        cartRemove(key).done(function (res) {
            if (!res.cart?.items?.length) {
                window.location.href = window.shopRoutes.shop || '/shop';
                return;
            }
            reloadCheckout();
        });
    });
})(jQuery);
