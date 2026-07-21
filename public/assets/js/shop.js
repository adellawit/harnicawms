(function ($) {
    'use strict';

    const fmtRp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

    function updateCartBadge(count) {
        $('#navCartBadge').text(count || 0);
    }

    function refreshCartUi(data) {
        if (data.summary) {
            $('#cartFooter').html(renderFooter(data.summary));
        }
        if (typeof data.cart_count !== 'undefined') {
            updateCartBadge(data.cart_count);
        }
    }

    function renderFooter(summary) {
        if (!summary || !summary.item_count) {
            return '<p class="text-muted small mb-0">Keranjang kosong.</p>';
        }
        let html = '<div class="d-flex justify-content-between small mb-1"><span>Subtotal</span><span>' + fmtRp(summary.subtotal) + '</span></div>';
        if (summary.tax_enabled) {
            html += '<div class="d-flex justify-content-between small text-muted mb-2"><span>PPN (' + summary.tax_rate + '%)</span><span>' + fmtRp(summary.tax_amount) + '</span></div>';
        }
        html += '<div class="d-flex justify-content-between fw-bold mb-3"><span>Total</span><span class="text-primary">' + fmtRp(summary.total) + '</span></div>';
        html += '<a href="' + (window.shopCheckoutUrl || '/shop/checkout') + '" class="btn btn-primary w-100">Checkout</a>';
        return html;
    }

    $(document).on('click', '.shop-product-card', function () {
        const productId = $(this).data('product-id');
        const productName = $(this).data('product-name');
        $('#variantModalTitle').text(productName);
        $('#variantModalBody').html('<div class="text-center py-4 text-muted">Memuat...</div>');
        const modal = new bootstrap.Modal('#variantModal');
        modal.show();

        $.get(window.shopRoutes.variants, { product_id: productId })
            .done(function (res) {
                if (!res.variants || !res.variants.length) {
                    $('#variantModalBody').html('<p class="text-muted mb-0">Varian tidak tersedia.</p>');
                    return;
                }
                let html = '<div class="list-group list-group-flush">';
                res.variants.forEach(function (v) {
                    html += '<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center btn-add-variant" data-variant-id="' + v.id + '">';
                    html += '<div><div class="fw-semibold">' + (v.display_name || v.sku) + '</div>';
                    html += '<small class="text-muted">' + (v.sku || '') + (v.is_stock_item ? ' · Stok ' + v.stock : '') + (v.unit_label ? ' · ' + v.unit_label : '') + '</small></div>';
                    html += '<span class="fw-bold text-primary text-end">' + fmtRp(v.selling_price) + (v.unit_label ? '<small class="d-block text-muted fw-normal">/ ' + v.unit_label + '</small>' : '') + '</span></button>';
                });
                html += '</div>';
                $('#variantModalBody').html(html);
            })
            .fail(function (xhr) {
                $('#variantModalBody').html('<p class="text-danger">' + (xhr.responseJSON?.message || 'Gagal memuat') + '</p>');
            });
    });

    $(document).on('click', '.btn-add-variant', function () {
        const variantId = $(this).data('variant-id');
        const $btn = $(this).prop('disabled', true);
        $.post(window.shopRoutes.cartAdd, { variant_id: variantId, quantity: 1 })
            .done(function (res) {
                bootstrap.Modal.getInstance('#variantModal')?.hide();
                updateCartBadge(res.cart_count);
                if (res.summary) {
                    $('#cartFooter').html(renderFooter(res.summary));
                }
                location.reload();
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON?.message || 'Gagal menambah ke keranjang');
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    });

    function cartUpdate(cartKey, quantity) {
        return $.post(window.shopRoutes.cartUpdate, { cart_key: cartKey, quantity: quantity });
    }

    $(document).on('click', '.btn-cart-qty', function () {
        const $row = $(this).closest('.cart-row');
        const key = $row.data('cart-key');
        const $input = $row.find('.cart-qty-input');
        let qty = parseInt($input.val(), 10) + parseInt($(this).data('delta'), 10);
        if (qty < 1) qty = 0;
        cartUpdate(key, qty).done(function () { location.reload(); }).fail(function (xhr) {
            alert(xhr.responseJSON?.message || 'Error');
        });
    });

    $(document).on('change', '.cart-qty-input', function () {
        const $row = $(this).closest('.cart-row');
        const key = $row.data('cart-key');
        let qty = parseInt($(this).val(), 10) || 0;
        cartUpdate(key, qty).done(function () { location.reload(); }).fail(function (xhr) {
            alert(xhr.responseJSON?.message || 'Error');
        });
    });

    $(document).on('click', '.btn-cart-remove', function () {
        const key = $(this).closest('.cart-row').data('cart-key');
        $.post(window.shopRoutes.cartRemove, { cart_key: key })
            .done(function () { location.reload(); });
    });
})(jQuery);
