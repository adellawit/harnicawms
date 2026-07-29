/**
 * Agent POS — adapted from admin POS logic (admin pos.blade.php untouched).
 * Requires window.agentPosConfig { routes, taxRate, cashMethodId, fallbackMethodId }.
 */
(function ($) {
    'use strict';

    var cfg = window.agentPosConfig || {};
    var routes = cfg.routes || {};
    var orderNotes = '';
    var discountType = 'percent';
    var promoPreviewTimer = null;
    var promoPreviewXhr = null;
    var xenditPollTimer = null;
    var xenditPendingOrderId = null;
    var xenditPendingMethodName = null;
    var xenditPendingInvoiceUrl = null;
    var paymentModal = null;
    var variantModal = null;

    $(document).ready(function () {
        $('#priceListSelect').select2({
            dropdownParent: $('body'),
            placeholder: 'Daftar Harga',
            allowClear: false,
        });
        $('#customerSelect').select2({
            dropdownParent: $('body'),
            placeholder: 'Pilih Reseller',
            allowClear: true,
        });

        var variantModalEl = document.getElementById('variantModal');
        if (variantModalEl) {
            variantModal = new bootstrap.Modal(variantModalEl);
        }
        var paymentModalEl = document.getElementById('paymentModal');
        if (paymentModalEl) {
            paymentModal = new bootstrap.Modal(paymentModalEl);
        }

        bindDiscountControls();
        bindMobileTabs();
        bindPaymentModal();
        bindToolbar();
        bindCatalog();
        bindCartEvents();
        bindCancel();
        handleXenditReturn();

        $('#priceListSelect').on('change select2:select', function () {
            $('#priceListWrapper').attr('data-selected-id', $(this).val() || '');
        }).trigger('change');

        setInterval(function () {
            var d = new Date();
            $('#posClock').text(d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }));
        }, 30000);

        updateCartTotals();
        checkEmptyCart();
    });

    function bindDiscountControls() {
        $('.pos-disc-toggle').on('click', '.disc-type', function () {
            $('.disc-type').removeClass('active');
            $(this).addClass('active');
            discountType = $(this).data('type');
            var $inp = $('#discountInput');
            $inp.val(formatDiscInputDisplay($inp.val(), discountType));
            updateCartTotals();
        });
        $('#discountInput').on('input', function () {
            handleDiscInputInput(this, discountType);
            updateCartTotals();
        });
    }

    function bindMobileTabs() {
        function isPosMobile() {
            return window.matchMedia('(max-width: 767.98px)').matches;
        }
        window.agentPosIsMobile = isPosMobile;

        function setPosMobileView(view) {
            var $main = $('#posMain');
            var $tabs = $('#posMobileTabs .pos-mobile-tab');
            if (view === 'cart') {
                $main.addClass('pos-mobile-view-cart');
            } else {
                $main.removeClass('pos-mobile-view-cart');
                view = 'catalog';
            }
            $tabs.removeClass('active').attr('aria-selected', 'false');
            $tabs.filter('[data-pos-view="' + view + '"]').addClass('active').attr('aria-selected', 'true');
        }
        window.agentPosSetMobileView = setPosMobileView;

        $('#posMobileTabs').on('click', '.pos-mobile-tab', function () {
            setPosMobileView($(this).data('pos-view'));
        });
        $(window).on('resize', function () {
            if (!isPosMobile()) {
                $('#posMain').removeClass('pos-mobile-view-cart');
            }
            if (!window.matchMedia('(max-width: 767.98px)').matches) {
                $('#payModalBody').removeClass('pay-mobile-view-other');
            }
        });
    }

    function bindToolbar() {
        $('#btnDiscToolbar').on('click', function () {
            $('#discountInput').focus();
        });
        $('#btnPromoToolbar').on('click', function () {
            refreshPromoPreview();
        });
        $('#btnNotesToolbar').on('click', function () {
            Swal.fire({
                title: 'Catatan Transaksi',
                input: 'textarea',
                inputValue: orderNotes,
                inputPlaceholder: 'Catatan opsional...',
                showCancelButton: true,
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal',
                customClass: {
                    confirmButton: 'btn btn-primary me-2',
                    cancelButton: 'btn btn-label-secondary',
                },
                buttonsStyling: false,
            }).then(function (r) {
                if (r.isConfirmed) {
                    orderNotes = (r.value || '').trim();
                }
            });
        });
        $('#btnClearAll').on('click', function () {
            $('#btnCancel').trigger('click');
        });
        $(document).on('keydown', function (e) {
            if ($(e.target).is('input, textarea, select')) {
                return;
            }
            if (e.key === 'F1') {
                e.preventDefault();
                $('#btnPayment').trigger('click');
            }
            if (e.key === 'F4') {
                e.preventDefault();
                $('#btnDiscToolbar').trigger('click');
            }
            if (e.key === 'F5') {
                e.preventDefault();
                $('#btnPromoToolbar').trigger('click');
            }
            if (e.key === 'F7') {
                e.preventDefault();
                $('#btnNotesToolbar').trigger('click');
            }
            if (e.key === 'F8') {
                e.preventDefault();
                $('#btnClearAll').trigger('click');
            }
        });
        $('#categoryFilterSelect').on('change', function () {
            var typeId = $(this).val();
            $('#productTypeTabs .pos-category-pill').removeClass('active');
            if (typeId === 'all') {
                $('#productTypeTabs .pos-category-pill[data-product-type="all"]').addClass('active');
                $('.pos-product-card').show();
            } else {
                $('#productTypeTabs .pos-category-pill[data-product-type="' + typeId + '"]').addClass('active');
                $('.pos-product-card').hide();
                $('.pos-product-card[data-product-type-id="' + typeId + '"]').show();
            }
        });
        $('#btnCategoryFilter').on('click', function () {
            $('#searchProduct').trigger('keyup');
        });
    }

    function bindCatalog() {
        $('#productTypeTabs').on('click', '.pos-category-pill', function () {
            $('#productTypeTabs .pos-category-pill').removeClass('active');
            $(this).addClass('active');
            var typeId = $(this).data('product-type');
            $('#categoryFilterSelect').val(typeId === 'all' ? 'all' : String(typeId));
            if (typeId === 'all') {
                $('.pos-product-card').show();
            } else {
                $('.pos-product-card').hide();
                $('.pos-product-card[data-product-type-id="' + typeId + '"]').show();
            }
        });

        $('#searchProduct').on('keyup', function () {
            var v = $(this).val().toLowerCase();
            $('.pos-product-card').each(function () {
                var name = $(this).find('.p-name').text().toLowerCase();
                $(this).toggle(name.indexOf(v) > -1);
            });
        });

        $(document).on('click', '.pos-product-card', function () {
            var priceListId = $('#priceListWrapper').attr('data-selected-id') || $('#priceListSelect').val() || '';
            if (!priceListId) {
                Swal.fire({
                    icon: 'warning',
                    text: 'Pilih daftar harga terlebih dahulu.',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
                return;
            }
            var productId = $(this).data('product-id');
            var productName = $(this).find('.p-name').text();
            var productImage = $(this).find('.p-img').attr('src');

            $.get(routes.productVariants, {
                product_id: productId,
                price_list_id: priceListId,
            }).done(function (res) {
                var variants = res.variants || [];
                var withPrice = variants.filter(function (v) {
                    return v.selling_price > 0;
                });
                if (withPrice.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        text: 'Tidak ada varian dengan harga untuk daftar harga ini.',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false,
                    });
                    return;
                }
                if (withPrice.length === 1) {
                    var v = withPrice[0];
                    addToCart(v.id, v.display_name, v.selling_price, v.image || productImage, v.unit_id, v.unit_label);
                    return;
                }
                showVariantModal(withPrice, productImage, productId);
            }).fail(function () {
                Swal.fire({
                    icon: 'error',
                    text: 'Gagal memuat varian produk.',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
            });
        });

        $(document).on('click', '.variant-item', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $el = $(this);
            addToCart(
                $el.data('variant-id'),
                $el.data('name'),
                $el.data('price'),
                $el.data('image'),
                $el.data('unit-id'),
                $el.data('unit-label')
            );
            if (variantModal) {
                variantModal.hide();
            }
        });
    }

    function showVariantModal(variants, productImage, productId) {
        $('#variantLoading').hide();
        $('#variantList').hide();
        $('#variantEmpty').hide();
        if (!variants.length) {
            $('#variantEmpty').show();
        } else {
            var html = '<div class="variant-grid">';
            variants.forEach(function (v) {
                var img = v.image || productImage || 'https://placehold.co/300x225/f8f9fa/b0b7c3?text=?';
                var stockClass = v.stock > 10 ? 'stock-ok' : (v.stock > 0 ? 'stock-low' : 'stock-out');
                html += '<div class="variant-card variant-item" role="button" tabindex="0"';
                html += ' data-variant-id="' + v.id + '" data-name="' + escapeHtml(v.display_name) + '" data-price="' + v.selling_price + '"';
                html += ' data-image="' + img.replace(/"/g, '&quot;') + '" data-unit-id="' + (v.unit_id || '') + '" data-unit-label="' + (v.unit_label || '') + '">';
                html += '<img src="' + img + '" alt="' + escapeHtml(v.display_name) + '" class="v-img" onerror="this.src=\'https://placehold.co/300x225/f8f9fa/b0b7c3?text=?\'">';
                html += '<div class="v-body">';
                html += '<div class="v-name">' + escapeHtml(v.display_name) + '</div>';
                html += '<div class="v-price">Rp ' + Number(v.selling_price).toLocaleString('id-ID') + '</div>';
                html += '<span class="v-stock ' + stockClass + '">Stok: ' + v.stock + '</span>';
                html += '</div></div>';
            });
            html += '</div>';
            $('#variantList').html(html).show();
        }
        if (variantModal) {
            variantModal.show();
        }
    }

    function bindCartEvents() {
        $(document).on('click', '.btn-minus', function () {
            var $item = $(this).closest('.pos-cart-item');
            if ($item.hasClass('is-promo-free')) {
                return;
            }
            var inp = $(this).siblings('.quantity-input');
            var val = parseInt(inp.val(), 10);
            if (val > 1) {
                inp.val(val - 1);
                updateCartTotals();
            }
        });
        $(document).on('click', '.btn-plus', function () {
            var $item = $(this).closest('.pos-cart-item');
            if ($item.hasClass('is-promo-free')) {
                return;
            }
            var inp = $(this).siblings('.quantity-input');
            inp.val(parseInt(inp.val(), 10) + 1);
            updateCartTotals();
        });
        $(document).on('click', '.btn-delete', function () {
            if ($(this).closest('.pos-cart-item').hasClass('is-promo-free')) {
                return;
            }
            $(this).closest('.pos-cart-item').remove();
            updateCartTotals();
            checkEmptyCart();
        });
        $(document).on('click', '.btn-item-disc', function () {
            var item = $(this).closest('.pos-cart-item');
            item.toggleClass('has-discount');
            if (!item.hasClass('has-discount')) {
                item.find('.item-disc-input').val('0');
                updateCartTotals();
            } else {
                item.find('.item-disc-input').focus();
            }
        });
        $(document).on('click', '.item-disc-type', function () {
            var toggle = $(this).closest('.ci-disc-toggle');
            toggle.find('.item-disc-type').removeClass('active');
            $(this).addClass('active');
            var item = $(this).closest('.pos-cart-item');
            var discType = $(this).data('type');
            var $inp = item.find('.item-disc-input');
            $inp.val(formatDiscInputDisplay($inp.val(), discType));
            updateCartTotals();
        });
        $(document).on('input', '.item-disc-input', function () {
            var item = $(this).closest('.pos-cart-item');
            var discType = item.find('.item-disc-type.active').data('type') || 'percent';
            handleDiscInputInput(this, discType);
            updateCartTotals();
        });
    }

    function bindCancel() {
        $('#btnCancel').on('click', function () {
            Swal.fire({
                title: 'Hapus semua item?',
                text: 'Keranjang akan dikosongkan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus',
                cancelButtonText: 'Batal',
                customClass: {
                    confirmButton: 'btn btn-danger me-2',
                    cancelButton: 'btn btn-label-secondary',
                },
                buttonsStyling: false,
            }).then(function (r) {
                if (r.isConfirmed) {
                    clearCart();
                }
            });
        });
    }

    function bindPaymentModal() {
        function setPayMobilePanel(panel) {
            var $body = $('#payModalBody');
            var view = panel === 'other' ? 'other' : 'cash';
            if (view === 'other') {
                $body.addClass('pay-mobile-view-other');
            } else {
                $body.removeClass('pay-mobile-view-other');
            }
            $('#payMobileTabs .pay-mobile-tab').removeClass('active').attr('aria-selected', 'false');
            $('#payMobileTabs .pay-mobile-tab[data-pay-panel="' + view + '"]').addClass('active').attr('aria-selected', 'true');
        }

        $('#payMobileTabs').on('click', '.pay-mobile-tab', function () {
            setPayMobilePanel($(this).data('pay-panel'));
        });

        $('#btnPayment').on('click', function () {
            if (getCartItemQty() === 0) {
                Swal.fire({
                    icon: 'warning',
                    text: 'Tambahkan item ke keranjang terlebih dahulu.',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
                return;
            }
            var priceListId = $('#priceListWrapper').attr('data-selected-id') || $('#priceListSelect').val() || '';
            if (!priceListId) {
                Swal.fire({
                    icon: 'warning',
                    text: 'Pilih daftar harga.',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
                return;
            }
            $('#payGrandTotal').text(formatRp(getCartTotal()));
            $('#payCashInput').val('');
            setPayMobilePanel('cash');
            if (paymentModal) {
                paymentModal.show();
            }
        });

        $(document).on('click', '.pay-denom[data-val]', function () {
            var val = parseInt($(this).data('val'), 10);
            var cur = parseInt($('#payCashInput').val().replace(/\D/g, ''), 10) || 0;
            $('#payCashInput').val((cur + val).toLocaleString('id-ID'));
        });
        $('#payCashClear').on('click', function () {
            $('#payCashInput').val('');
        });
        $('#payCashExact').on('click', function () {
            $('#payCashInput').val(getCartTotal().toLocaleString('id-ID'));
        });
        $('#payCashInput').on('input', function () {
            var raw = this.value.replace(/\D/g, '');
            this.value = raw === '' ? '' : parseInt(raw, 10).toLocaleString('id-ID');
        });
        $('#payCashPay').on('click', function () {
            var cashAmount = parseInt($('#payCashInput').val().replace(/\D/g, ''), 10) || 0;
            var total = getCartTotal();
            if (cashAmount < total) {
                Swal.fire({
                    icon: 'warning',
                    text: 'Nominal tunai kurang dari total (Rp ' + total.toLocaleString('id-ID') + ')',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
                return;
            }
            var cashMethodId = cfg.cashMethodId || cfg.fallbackMethodId || null;
            if (!cashMethodId) {
                Swal.fire({
                    icon: 'error',
                    text: 'Metode tunai tidak tersedia.',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
                return;
            }
            processPayment(cashMethodId, 'Tunai', cashAmount);
        });
        $(document).on('click', '.pay-channel-btn', function () {
            processPayment(
                $(this).data('payment-id'),
                $(this).data('payment-name'),
                getCartTotal(),
                true,
                $(this).data('xendit-channel') || null
            );
        });
        $(document).on('click', '.pay-other-btn', function () {
            processPayment(
                $(this).data('payment-id'),
                $(this).data('payment-name'),
                getCartTotal(),
                false,
                null
            );
        });
        $('#btnXenditClose').on('click', function () {
            if (!xenditPendingOrderId) {
                closeXenditCheckout();
                return;
            }
            Swal.fire({
                title: 'Tutup pembayaran?',
                text: 'Order masih menunggu pembayaran.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, tutup',
                cancelButtonText: 'Lanjut bayar',
                customClass: {
                    confirmButton: 'btn btn-label-secondary me-2',
                    cancelButton: 'btn btn-primary',
                },
                buttonsStyling: false,
            }).then(function (r) {
                if (r.isConfirmed) {
                    closeXenditCheckout();
                }
            });
        });
        $('#btnXenditContinue').on('click', function () {
            if (xenditPendingInvoiceUrl) {
                window.location.href = xenditPendingInvoiceUrl;
            }
        });
    }

    function $cartLineItems() {
        return $('#cartItems .pos-cart-item').not('#sampleCartItem');
    }

    function $cartPaidItems() {
        return $cartLineItems().not('.is-promo-free');
    }

    function getCartItemQty() {
        var count = 0;
        $cartPaidItems().each(function () {
            count += parseInt($(this).find('.quantity-input').val(), 10) || 0;
        });
        return count;
    }

    function getCartTotal() {
        return parseInt($('#total').text().replace(/\D/g, ''), 10) || 0;
    }

    function formatRp(n) {
        return 'Rp ' + Number(n).toLocaleString('id-ID');
    }

    function escapeHtml(t) {
        var d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    function parseDiscRaw(val) {
        return parseInt(String(val || '').replace(/\D/g, ''), 10) || 0;
    }

    function parseDiscValue(val, type) {
        if (type === 'nominal') {
            return parseDiscRaw(val);
        }
        var n = parseInt(String(val || '').replace(/\D/g, ''), 10) || 0;
        return Math.min(100, Math.max(0, n));
    }

    function formatDiscInputDisplay(val, type) {
        if (type === 'nominal') {
            var n = parseDiscRaw(val);
            return n === 0 ? '0' : n.toLocaleString('id-ID');
        }
        var p = parseDiscValue(val, 'percent');
        return p === 0 && String(val || '').trim() === '' ? '' : String(p);
    }

    function handleDiscInputInput(inputEl, type) {
        if (type === 'nominal') {
            var raw = inputEl.value.replace(/\D/g, '');
            inputEl.value = raw === '' ? '' : parseInt(raw, 10).toLocaleString('id-ID');
        } else {
            var rawPct = inputEl.value.replace(/\D/g, '');
            if (rawPct === '') {
                inputEl.value = '';
                return;
            }
            inputEl.value = String(Math.min(100, parseInt(rawPct, 10) || 0));
        }
    }

    function collectCartItems() {
        var items = [];
        $cartPaidItems().each(function () {
            var $el = $(this);
            var inp = $el.find('.quantity-input');
            var unitPrice = parseFloat(inp.data('unit-price')) || 0;
            var qty = parseInt(inp.val(), 10) || 1;
            var discType = 'percent';
            var discVal = 0;
            if ($el.hasClass('has-discount')) {
                discType = $el.find('.item-disc-type.active').data('type') || 'percent';
                discVal = parseDiscValue($el.find('.item-disc-input').val(), discType);
            }
            items.push({
                variant_id: $el.data('variant-id'),
                unit_id: $el.data('unit-id'),
                quantity: qty,
                unit_price: unitPrice,
                discount_type: discType,
                discount_value: discVal,
                serial_numbers: [],
            });
        });
        return items;
    }

    function clearPromoFreeLines() {
        $cartLineItems().filter('.is-promo-free').remove();
        $('#promoHintRow').hide();
    }

    function renderPromoFreeLines(freeItems) {
        clearPromoFreeLines();
        if (!freeItems || !freeItems.length) {
            checkEmptyCart();
            return;
        }
        freeItems.forEach(function (row) {
            var item = $('#sampleCartItem').clone().removeAttr('id').show();
            item.addClass('is-promo-free');
            item.attr('data-variant-id', row.variant_id);
            item.attr('data-unit-id', row.unit_id || '');
            item.attr('data-unit-label', row.unit_label || '');
            item.find('.ci-img').attr('src', row.image || 'https://placehold.co/44x44/d8f3dc/146c2e?text=FREE');
            item.find('.ci-name').html(
                '<span class="ci-promo-badge"><i class="ti ti-gift"></i> FREE' +
                (row.promo_code ? ' · ' + row.promo_code : '') +
                '</span><span>' + escapeHtml(row.name || 'Promo item') + '</span>'
            );
            item.find('.ci-price').text('Rp 0' + (row.unit_label ? ' / ' + row.unit_label : ''));
            item.find('.ci-qty-unit').text(row.unit_label || '');
            item.find('.quantity-input').val(row.quantity).data('unit-price', 0);
            $('#cartItems').append(item);
        });
        var totalFreeQty = freeItems.reduce(function (sum, row) {
            return sum + (parseFloat(row.quantity) || 0);
        }, 0);
        $('#promoHintText').text('Promo: ' + totalFreeQty + ' item gratis.');
        $('#promoHintRow').show();
        checkEmptyCart();
    }

    function refreshPromoPreview() {
        clearTimeout(promoPreviewTimer);
        promoPreviewTimer = setTimeout(function () {
            var items = collectCartItems();
            if (!items.length) {
                clearPromoFreeLines();
                updateCartBadgeOnly();
                return;
            }
            if (promoPreviewXhr) {
                promoPreviewXhr.abort();
            }
            promoPreviewXhr = $.ajax({
                url: routes.previewPromo,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    Accept: 'application/json',
                },
                contentType: 'application/json',
                data: JSON.stringify({ items: items }),
                success: function (res) {
                    if (res && res.success) {
                        renderPromoFreeLines(res.free_items || []);
                        updateCartBadgeOnly();
                    }
                },
                error: function (xhr) {
                    if (xhr.statusText === 'abort') {
                        return;
                    }
                    clearPromoFreeLines();
                    updateCartBadgeOnly();
                },
            });
        }, 280);
    }

    function updateCartBadgeOnly() {
        var paidQty = 0;
        var freeQty = 0;
        $cartPaidItems().each(function () {
            paidQty += parseInt($(this).find('.quantity-input').val(), 10) || 0;
        });
        $cartLineItems().filter('.is-promo-free').each(function () {
            freeQty += parseInt($(this).find('.quantity-input').val(), 10) || 0;
        });
        $('#cartItemCount, #cartItemCountBadge, #cartMobileTabBadge').text(paidQty + freeQty);
    }

    function addToCart(variantId, name, price, image, unitId, unitLabel) {
        $('#emptyCart').hide();
        var existing = $cartPaidItems().filter(function () {
            return $(this).data('variant-id') == variantId;
        });
        if (existing.length > 0) {
            var inp = existing.find('.quantity-input');
            inp.val(parseInt(inp.val(), 10) + 1);
            if (window.agentPosIsMobile && window.agentPosIsMobile()) {
                window.agentPosSetMobileView('cart');
            }
            updateCartTotals();
            checkEmptyCart();
            return;
        }
        var item = $('#sampleCartItem').clone().removeAttr('id').show();
        item.attr('data-variant-id', variantId);
        item.attr('data-unit-id', unitId || '');
        item.attr('data-unit-label', unitLabel || '');
        item.find('.ci-img').attr('src', image || 'https://placehold.co/44x44/f8f9fa/b0b7c3?text=?');
        item.find('.ci-name').text(name);
        item.find('.ci-price').text('Rp ' + Number(price).toLocaleString('id-ID') + (unitLabel ? ' / ' + unitLabel : ''));
        item.find('.ci-qty-unit').text(unitLabel || '');
        item.find('.quantity-input').val(1).data('unit-price', price);
        $('#cartItems').append(item);
        if (window.agentPosIsMobile && window.agentPosIsMobile()) {
            window.agentPosSetMobileView('cart');
        }
        updateCartTotals();
        checkEmptyCart();
    }

    function updateCartTotals() {
        var subtotalNet = 0;
        var totalItemDisc = 0;
        $cartPaidItems().each(function () {
            var $el = $(this);
            var inp = $el.find('.quantity-input');
            var unitPrice = parseFloat(inp.data('unit-price')) || 0;
            var qty = parseInt(inp.val(), 10) || 1;
            var lineTotal = unitPrice * qty;
            var itemDiscAmt = 0;
            if ($el.hasClass('has-discount')) {
                var dt = $el.find('.item-disc-type.active').data('type') || 'percent';
                var dv = parseDiscValue($el.find('.item-disc-input').val(), dt);
                if (dt === 'percent') {
                    itemDiscAmt = Math.round(lineTotal * dv / 100);
                } else {
                    itemDiscAmt = Math.round(dv);
                    if (itemDiscAmt > lineTotal) {
                        itemDiscAmt = lineTotal;
                    }
                }
                $el.find('.item-disc-display').text('Rp ' + itemDiscAmt.toLocaleString('id-ID'));
            }
            totalItemDisc += itemDiscAmt;
            subtotalNet += (lineTotal - itemDiscAmt);
        });

        var discVal = parseDiscValue($('#discountInput').val(), discountType);
        var txnDiscAmt = 0;
        if (discountType === 'percent') {
            txnDiscAmt = Math.round(subtotalNet * discVal / 100);
        } else {
            txnDiscAmt = Math.round(discVal);
            if (txnDiscAmt > subtotalNet) {
                txnDiscAmt = subtotalNet;
            }
        }

        var total = Math.max(0, subtotalNet - txnDiscAmt);
        var itemCount = getCartItemQty();

        $('#subtotal').text(formatRp(subtotalNet));
        $('#subtotalItemCount').text(itemCount);
        $('#itemDiscTotal').text(formatRp(totalItemDisc));
        if (totalItemDisc > 0) {
            $('#itemDiscRow').show();
        } else {
            $('#itemDiscRow').hide();
        }
        $('#discountDisplay').text(formatRp(txnDiscAmt));
        $('#total').text(formatRp(total));
        updateCartBadgeOnly();
        refreshPromoPreview();
    }

    function checkEmptyCart() {
        if ($cartLineItems().length > 0) {
            $('#emptyCart').hide();
        } else {
            $('#emptyCart').show();
            $('#promoHintRow').hide();
        }
    }

    function clearCart() {
        $('#cartItems .pos-cart-item').not('#sampleCartItem').remove();
        $('#posTrxNumberWrap').hide();
        $('#posTrxNumber').text('');
        $('#discountInput').val('0');
        discountType = 'percent';
        $('.disc-type').removeClass('active');
        $('.disc-type[data-type="percent"]').addClass('active');
        orderNotes = '';
        updateCartTotals();
        checkEmptyCart();
    }

    function resetPosUiAfterPayment() {
        closeXenditCheckout();
        if (paymentModal) {
            paymentModal.hide();
        }
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
        $('#paymentModal .pay-denom-cash-pay, #paymentModal .pay-other-btn, #paymentModal .pay-channel-btn').prop('disabled', false);
        $('#payCashPay').html('Tunai');
        clearCart();
    }

    function clearXenditPoll() {
        if (xenditPollTimer) {
            clearInterval(xenditPollTimer);
            xenditPollTimer = null;
        }
        sessionStorage.removeItem('xendit_pending_order');
        sessionStorage.removeItem('xendit_pending_method');
    }

    function closeXenditCheckout() {
        clearXenditPoll();
        xenditPendingOrderId = null;
        xenditPendingMethodName = null;
        xenditPendingInvoiceUrl = null;
        $('#xenditCheckoutFrame').attr('src', 'about:blank');
        $('body').removeClass('xendit-checkout-active xendit-checkout-fallback');
        $('#xenditCheckoutOverlay').attr('aria-hidden', 'true').hide();
    }

    function showXenditCheckout(d, methodName) {
        xenditPendingOrderId = d.sales_order_id;
        xenditPendingMethodName = methodName;
        xenditPendingInvoiceUrl = d.invoice_url;
        sessionStorage.setItem('xendit_pending_order', d.sales_order_id);
        sessionStorage.setItem('xendit_pending_method', methodName);
        $('#xenditCheckoutOrder').text(d.sales_number);
        $('#xenditCheckoutTotal').text(formatRp(d.total));
        $('body').removeClass('xendit-checkout-fallback');
        $('#xenditCheckoutFrame').attr('src', d.invoice_url);
        $('body').addClass('xendit-checkout-active');
        $('#xenditCheckoutOverlay').attr('aria-hidden', 'false').show();
        pollXenditPayment(d.sales_order_id, methodName);
        setTimeout(function () {
            var blocked = false;
            try {
                var frame = document.getElementById('xenditCheckoutFrame');
                var doc = frame.contentDocument || frame.contentWindow.document;
                if (!doc || !doc.body || doc.body.innerHTML === '') {
                    blocked = true;
                }
            } catch (e) {
                blocked = true;
            }
            if (blocked && xenditPendingInvoiceUrl) {
                window.location.href = xenditPendingInvoiceUrl;
            }
        }, 2000);
    }

    function showPaymentSuccess(d, methodName) {
        var html = '<table class="w-100" style="font-size:0.95rem">';
        html += '<tr><td class="text-start py-1" style="color:#9aa4b8">No. Transaksi</td><td class="text-end py-1 fw-bold">' + (d.sales_number || '-') + '</td></tr>';
        html += '<tr><td class="text-start py-1" style="color:#9aa4b8">Total</td><td class="text-end py-1 fw-bold">' + formatRp(d.total) + '</td></tr>';
        html += '<tr><td class="text-start py-1" style="color:#9aa4b8">Pembayaran</td><td class="text-end py-1 fw-bold">' + methodName + '</td></tr>';
        if (d.amount_paid !== undefined) {
            html += '<tr><td class="text-start py-1" style="color:#9aa4b8">Dibayar</td><td class="text-end py-1 fw-bold">' + formatRp(d.amount_paid) + '</td></tr>';
        }
        if (d.change_amount > 0) {
            html += '<tr><td class="text-start py-1" style="color:#9aa4b8">Kembalian</td><td class="text-end py-1 fw-bold text-success">' + formatRp(d.change_amount) + '</td></tr>';
        }
        if (d.promo_free_count > 0) {
            html += '<tr><td class="text-start py-1" style="color:#9aa4b8">Promo FREE</td><td class="text-end py-1 fw-bold text-success">' + d.promo_free_count + ' item</td></tr>';
        }
        html += '</table>';
        resetPosUiAfterPayment();
        if (d.sales_number) {
            $('#posTrxNumber').text(d.sales_number);
            $('#posTrxNumberWrap').show();
        }
        Swal.fire({
            title: 'Transaksi Selesai!',
            html: html,
            icon: 'success',
            confirmButtonText: 'OK',
            customClass: { confirmButton: 'btn btn-success' },
            buttonsStyling: false,
        });
    }

    function pollXenditPayment(orderId, methodName) {
        clearXenditPoll();
        var attempts = 0;
        xenditPollTimer = setInterval(function () {
            attempts++;
            if (attempts > 200) {
                clearXenditPoll();
                return;
            }
            $.get(routes.paymentStatusBase + '/' + orderId + '/status', { sync: 1 }).done(function (res) {
                if (res.success && res.data && res.data.is_paid) {
                    clearXenditPoll();
                    closeXenditCheckout();
                    showPaymentSuccess({
                        sales_order_id: res.data.sales_order_id,
                        sales_number: res.data.sales_number,
                        total: res.data.total,
                        change_amount: 0,
                    }, methodName);
                }
            });
        }, 3000);
    }

    function openXenditCheckout(res, methodName) {
        if (paymentModal) {
            paymentModal.hide();
        }
        showXenditCheckout(res.data, methodName);
    }

    function processPayment(methodId, methodName, amountPaid, useXendit, xenditChannel) {
        var priceListId = $('#priceListWrapper').attr('data-selected-id') || $('#priceListSelect').val() || '';
        var items = collectCartItems();
        $('#paymentModal .pay-denom-cash-pay, #paymentModal .pay-other-btn, #paymentModal .pay-channel-btn').prop('disabled', true);
        $('#payCashPay').html('<span class="spinner-border spinner-border-sm me-1"></span>Memproses...');

        var payload = {
            price_list_id: priceListId,
            items: items,
            payment_method_id: methodId,
            customer_id: $('#customerSelect').val() || null,
            tax_rate: cfg.taxRate || 0,
            tax_enabled: false,
            discount_type: discountType,
            discount_value: parseDiscValue($('#discountInput').val(), discountType),
            redeem_points: 0,
            amount_paid: amountPaid,
            notes: orderNotes || null,
        };
        if (useXendit && xenditChannel) {
            payload.xendit_channel = xenditChannel;
        }

        $.ajax({
            url: routes.payment,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                Accept: 'application/json',
            },
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function (res) {
                if (res.success && res.xendit && res.data && res.data.invoice_url) {
                    openXenditCheckout(res, methodName);
                    return;
                }
                if (paymentModal) {
                    paymentModal.hide();
                }
                if (res.success) {
                    showPaymentSuccess(res.data, methodName);
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
                if (paymentModal) {
                    paymentModal.hide();
                }
                var msg = 'Pembayaran gagal';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    if (xhr.responseJSON.errors) {
                        var errList = [];
                        $.each(xhr.responseJSON.errors, function (_, v) {
                            errList.push(v.join(', '));
                        });
                        msg += '<br>' + errList.join('<br>');
                    }
                }
                Swal.fire({
                    title: 'Error',
                    html: msg,
                    icon: 'error',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
            },
            complete: function () {
                $('#paymentModal .pay-denom-cash-pay, #paymentModal .pay-other-btn, #paymentModal .pay-channel-btn').prop('disabled', false);
                $('#payCashPay').html('Tunai');
            },
        });
    }

    function handleXenditReturn() {
        var params = new URLSearchParams(window.location.search);
        var paymentStatus = params.get('payment');
        var orderId = params.get('order_id') || sessionStorage.getItem('xendit_pending_order');
        var methodName = sessionStorage.getItem('xendit_pending_method') || 'Xendit';
        if (!orderId) {
            return;
        }
        if (window.history.replaceState && params.get('order_id')) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
        if (paymentStatus === 'failed') {
            clearXenditPoll();
            Swal.fire({
                title: 'Pembayaran gagal',
                text: 'Pembayaran Xendit tidak berhasil atau dibatalkan.',
                icon: 'error',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false,
            });
            return;
        }
        if (paymentStatus === 'pending') {
            closeXenditCheckout();
            pollXenditPayment(orderId, methodName);
            return;
        }
        if (paymentStatus === 'success') {
            closeXenditCheckout();
            $.get(routes.paymentStatusBase + '/' + orderId + '/status', { sync: 1 }).done(function (res) {
                if (res.success && res.data && res.data.is_paid) {
                    showPaymentSuccess({
                        sales_order_id: res.data.sales_order_id,
                        sales_number: res.data.sales_number,
                        total: res.data.total,
                        change_amount: 0,
                    }, methodName);
                } else {
                    pollXenditPayment(orderId, methodName);
                }
            }).fail(function () {
                pollXenditPayment(orderId, methodName);
            });
        }
    }
})(jQuery);
