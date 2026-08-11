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
    var addItemModal = null;
    var pendingAddItem = null;
    var selectedMarketingPromo = null;
    var marketingPromoBlocked = false;
    var marketingPromoBlockMessage = '';
    var discountFromMarketingPromo = false;

    $(document).ready(function () {
        $('#priceListSelect').select2({
            dropdownParent: $('body'),
            placeholder: 'Daftar Harga',
            allowClear: false,
        });
        $('#customerSelect').select2({
            dropdownParent: $('body'),
            placeholder: 'Pelanggan Umum (Walk-in)',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: routes.resellerSearch,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: function (data) {
                    return { results: data.results || [] };
                },
            },
            templateResult: function (item) {
                if (!item.id) {
                    return item.text;
                }
                var $row = $('<span></span>').text(item.text);
                if (item.own) {
                    $row.append(' <span class="badge bg-label-primary ms-1">Reseller Anda</span>');
                }
                return $row;
            },
        });

        var variantModalEl = document.getElementById('variantModal');
        if (variantModalEl) {
            variantModal = new bootstrap.Modal(variantModalEl);
        }
        var addItemModalEl = document.getElementById('addItemModal');
        if (addItemModalEl) {
            addItemModal = new bootstrap.Modal(addItemModalEl);
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
        bindAddItemModal();
        bindMarketingCampaigns();
        handleXenditReturn();

        $('#priceListSelect').on('change select2:select', function () {
            $('#priceListWrapper').attr('data-selected-id', $(this).val() || '');
        }).trigger('change');

        $('#customerSelect').on('change select2:select select2:clear', function () {
            updateResellerAddressDisplay();
            if (selectedMarketingPromo) {
                revalidateSelectedMarketingPromo(true);
            }
        });

        $('#shippingInput').on('input change', function () {
            updateCartTotals(false);
        });

        setInterval(function () {
            var d = new Date();
            $('#posClock').text(d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }));
        }, 30000);

        updateCartTotals();
        checkEmptyCart();
    });

    function bindDiscountControls() {
        $('.pos-disc-toggle').on('click', '.disc-type', function () {
            if (discountFromMarketingPromo) {
                clearMarketingPromo(false);
            }
            $('.disc-type').removeClass('active');
            $(this).addClass('active');
            discountType = $(this).data('type');
            var $inp = $('#discountInput');
            $inp.val(formatDiscInputDisplay($inp.val(), discountType));
            updateCartTotals();
        });
        $('#discountInput').on('input', function () {
            if (discountFromMarketingPromo) {
                clearMarketingPromo(false);
            }
            handleDiscInputInput(this, discountType);
            updateCartTotals();
        });
    }

    function bindMarketingCampaigns() {
        $(document).on('click keypress', '.pos-campaign-card', function (e) {
            if (e.type === 'keypress' && e.which !== 13 && e.which !== 32) {
                return;
            }
            e.preventDefault();
            toggleMarketingPromoCard($(this));
        });
    }

    function getMarketingPromoDataFromCard($card) {
        return {
            id: String($card.data('promo-id') || ''),
            discount_type: String($card.data('discount-type') || 'percent'),
            discount_value: parseFloat($card.data('discount-value')) || 0,
            min_type: String($card.data('min-type') || 'amount'),
            min_value: parseFloat($card.data('min-value')) || 0,
            target_type: String($card.data('target-type') || 'both'),
            target_agent_id: String($card.data('target-agent') || ''),
            target_reseller_customer_id: String($card.data('target-reseller-customer') || ''),
            name: $.trim($card.find('.fw-semibold').first().text()),
        };
    }

    function checkMarketingPromoTarget(promo) {
        var agentId = String((window.agentPosCtx || {}).agentId || '');
        var customerId = String($('#customerSelect').val() || '');

        if (promo.target_type === 'agent') {
            return !promo.target_agent_id || promo.target_agent_id === agentId;
        }
        if (promo.target_type === 'reseller') {
            if (!customerId) {
                return false;
            }
            return !promo.target_reseller_customer_id || promo.target_reseller_customer_id === customerId;
        }
        if (promo.target_type === 'both') {
            var agentOk = !promo.target_agent_id || promo.target_agent_id === agentId;
            var resellerOk = customerId && (
                !promo.target_reseller_customer_id || promo.target_reseller_customer_id === customerId
            );
            return agentOk || resellerOk;
        }
        return false;
    }

    function getCartSubtotalNet() {
        var subtotalNet = 0;
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
            }
            subtotalNet += (lineTotal - itemDiscAmt);
        });
        return subtotalNet;
    }

    function checkMarketingPromoRequirement(promo) {
        if (promo.min_type === 'qty') {
            return getCartItemQty() >= promo.min_value;
        }
        return getCartSubtotalNet() >= promo.min_value;
    }

    function formatMarketingMinLabel(promo) {
        if (promo.min_type === 'qty') {
            return promo.min_value + ' item';
        }
        return 'Rp ' + Math.round(promo.min_value).toLocaleString('id-ID');
    }

    function showMarketingPromoAlert(message, isError) {
        var $alert = $('#marketingPromoAlert');
        if (!message) {
            $alert.hide().text('');
            return;
        }
        $alert.text(message)
            .toggleClass('is-error', !!isError)
            .show();
    }

    function updatePayButtonState() {
        var disabled = marketingPromoBlocked && !!selectedMarketingPromo;
        $('#btnPayment, #btnOrder').prop('disabled', disabled);
        if (disabled) {
            $('#btnPayment, #btnOrder').attr('title', marketingPromoBlockMessage || 'Syarat promo belum terpenuhi');
        } else {
            $('#btnPayment, #btnOrder').removeAttr('title');
        }
    }

    function updateResellerAddressDisplay() {
        var data = $('#customerSelect').select2('data');
        var selected = data && data.length ? data[0] : null;
        if (selected && selected.id && selected.address_label) {
            $('#resellerAddressText').text(selected.address_label);
            $('#resellerAddressBlock').show();
        } else {
            $('#resellerAddressText').text('');
            $('#resellerAddressBlock').hide();
        }
    }

    function getShippingAmount() {
        return Math.max(0, parseFloat($('#shippingInput').val()) || 0);
    }

    function buildPosOrderPayload() {
        var priceListId = $('#priceListWrapper').attr('data-selected-id') || $('#priceListSelect').val() || '';
        var payload = {
            price_list_id: priceListId,
            items: collectCartItems(),
            customer_id: $('#customerSelect').val() || null,
            tax_rate: cfg.taxRate || 0,
            tax_enabled: false,
            discount_type: discountType,
            discount_value: parseDiscValue($('#discountInput').val(), discountType),
            shipping_amount: getShippingAmount(),
            redeem_points: 0,
            notes: orderNotes || null,
        };
        if (selectedMarketingPromo && !marketingPromoBlocked) {
            payload.marketing_promotion_id = selectedMarketingPromo.id;
        }
        return payload;
    }

    function applyMarketingPromoDiscount(promo) {
        discountFromMarketingPromo = true;
        discountType = promo.discount_type;
        $('.disc-type').removeClass('active');
        $('.disc-type[data-type="' + promo.discount_type + '"]').addClass('active');
        $('#discountInput')
            .val(formatDiscInputDisplay(String(promo.discount_value), promo.discount_type))
            .prop('readonly', true);
    }

    function clearMarketingPromoDiscountFields() {
        discountFromMarketingPromo = false;
        $('#discountInput').prop('readonly', false);
        if (selectedMarketingPromo) {
            return;
        }
        $('#discountInput').val('0');
        discountType = 'percent';
        $('.disc-type').removeClass('active');
        $('.disc-type[data-type="percent"]').addClass('active');
    }

    function clearMarketingPromo(resetDiscount) {
        selectedMarketingPromo = null;
        marketingPromoBlocked = false;
        marketingPromoBlockMessage = '';
        $('.pos-campaign-card').removeClass('pos-campaign-card-active');
        showMarketingPromoAlert('');
        if (resetDiscount !== false) {
            clearMarketingPromoDiscountFields();
        } else {
            discountFromMarketingPromo = false;
            $('#discountInput').prop('readonly', false);
        }
        updatePayButtonState();
    }

    function revalidateSelectedMarketingPromo(fromCustomerChange) {
        if (!selectedMarketingPromo) {
            updatePayButtonState();
            return;
        }

        if (!checkMarketingPromoTarget(selectedMarketingPromo)) {
            marketingPromoBlocked = true;
            marketingPromoBlockMessage = 'Promo tidak berlaku untuk target ini';
            showMarketingPromoAlert(marketingPromoBlockMessage, true);
            discountFromMarketingPromo = false;
            $('#discountInput').prop('readonly', false).val('0');
            discountType = 'percent';
            $('.disc-type').removeClass('active');
            $('.disc-type[data-type="percent"]').addClass('active');
            updatePayButtonState();
            updateCartTotals(false);
            return;
        }

        if (!checkMarketingPromoRequirement(selectedMarketingPromo)) {
            marketingPromoBlocked = true;
            marketingPromoBlockMessage = 'Min belanja ' + formatMarketingMinLabel(selectedMarketingPromo) + ' belum tercapai';
            showMarketingPromoAlert(marketingPromoBlockMessage, true);
            discountFromMarketingPromo = false;
            $('#discountInput').prop('readonly', false).val('0');
            discountType = 'percent';
            $('.disc-type').removeClass('active');
            $('.disc-type[data-type="percent"]').addClass('active');
            updatePayButtonState();
            updateCartTotals(false);
            return;
        }

        marketingPromoBlocked = false;
        marketingPromoBlockMessage = '';
        showMarketingPromoAlert('Promo "' + selectedMarketingPromo.name + '" diterapkan.', false);
        applyMarketingPromoDiscount(selectedMarketingPromo);
        updatePayButtonState();
        updateCartTotals(false);
    }

    function toggleMarketingPromoCard($card) {
        var promo = getMarketingPromoDataFromCard($card);
        if (selectedMarketingPromo && selectedMarketingPromo.id === promo.id) {
            clearMarketingPromo(true);
            updateCartTotals(false);
            return;
        }

        if (!checkMarketingPromoTarget(promo)) {
            showMarketingPromoAlert('Promo tidak berlaku untuk target ini', true);
            marketingPromoBlocked = true;
            marketingPromoBlockMessage = 'Promo tidak berlaku untuk target ini';
            updatePayButtonState();
            return;
        }

        selectedMarketingPromo = promo;
        $('.pos-campaign-card').removeClass('pos-campaign-card-active');
        $card.addClass('pos-campaign-card-active');
        revalidateSelectedMarketingPromo(false);
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
            Swal.fire({
                title: 'Hapus semua item?',
                text: 'Keranjang, diskon, dan pelanggan akan direset.',
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
                    if (v.selling_price > 0) {
                        return true;
                    }
                    return (v.unit_options || []).some(function (u) {
                        return u.suggested_price > 0;
                    });
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
                    openAddItemModal(withPrice[0], productImage);
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
            var idx = $(this).data('variant-index');
            var variants = $(this).closest('#variantList').data('variants') || [];
            var variant = variants[idx];
            if (!variant) {
                return;
            }
            var productImage = $(this).closest('#variantList').data('product-image') || '';
            if (variantModal) {
                variantModal.hide();
            }
            openAddItemModal(variant, productImage);
        });
    }

    function bindAddItemModal() {
        $('#addItemUnitSelect').on('change', function () {
            syncAddItemPriceFromUnit();
        });

        $('#btnAddItemConfirm').on('click', function () {
            submitAddItemModal();
        });

        $('#addItemModal').on('hidden.bs.modal', function () {
            pendingAddItem = null;
        });
    }

    function resolveUnitOptions(variant) {
        var options = variant.unit_options || [];
        if (options.length > 0) {
            return options;
        }
        if (variant.unit_id) {
            return [{
                unit_id: variant.unit_id,
                unit_label: variant.unit_label || '',
                suggested_price: parseFloat(variant.selling_price) || 0,
            }];
        }
        return [];
    }

    function openAddItemModal(variant, productImage) {
        var unitOptions = resolveUnitOptions(variant);
        if (!unitOptions.length) {
            Swal.fire({
                icon: 'warning',
                text: 'Unit produk tidak tersedia.',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false,
            });
            return;
        }

        pendingAddItem = {
            variant: variant,
            image: variant.image || productImage || '',
            unitOptions: unitOptions,
        };

        $('#addItemVariantName').text(variant.display_name || variant.name || 'Produk');
        var $unitSelect = $('#addItemUnitSelect');
        $unitSelect.empty();
        unitOptions.forEach(function (opt) {
            $unitSelect.append(
                $('<option></option>')
                    .val(opt.unit_id)
                    .text(opt.unit_label)
                    .attr('data-suggested', opt.suggested_price)
            );
        });

        var defaultUnitId = variant.default_unit_id || variant.unit_id || unitOptions[0].unit_id;
        if ($unitSelect.find('option[value="' + defaultUnitId + '"]').length) {
            $unitSelect.val(defaultUnitId);
        } else {
            $unitSelect.val(unitOptions[0].unit_id);
        }

        if (unitOptions.length <= 1) {
            $('#addItemUnitWrap').hide();
        } else {
            $('#addItemUnitWrap').show();
        }

        syncAddItemPriceFromUnit();
        $('#addItemQtyInput').val(1);

        if (addItemModal) {
            addItemModal.show();
        }
    }

    function syncAddItemPriceFromUnit() {
        var $selected = $('#addItemUnitSelect option:selected');
        var suggested = parseFloat($selected.attr('data-suggested')) || 0;
        var $priceInput = $('#addItemPriceInput');
        $priceInput.attr('placeholder', suggested > 0 ? String(suggested) : '0');
        $priceInput.val(suggested > 0 ? suggested : '');
    }

    function submitAddItemModal() {
        if (!pendingAddItem) {
            return;
        }

        var variant = pendingAddItem.variant;
        var $selected = $('#addItemUnitSelect option:selected');
        var unitId = $selected.val();
        var unitLabel = $.trim($selected.text());
        var suggested = parseFloat($selected.attr('data-suggested')) || 0;
        var priceRaw = $('#addItemPriceInput').val();
        var price = priceRaw === '' ? suggested : parseFloat(priceRaw);
        var qty = parseInt($('#addItemQtyInput').val(), 10) || 1;

        if (!unitId) {
            Swal.fire({
                icon: 'warning',
                text: 'Pilih unit terlebih dahulu.',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false,
            });
            return;
        }
        if (!price || price <= 0) {
            Swal.fire({
                icon: 'warning',
                text: 'Masukkan harga yang valid.',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false,
            });
            return;
        }
        if (qty < 1) {
            Swal.fire({
                icon: 'warning',
                text: 'Qty minimal 1.',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false,
            });
            return;
        }

        addToCart(
            variant.id,
            variant.display_name,
            price,
            pendingAddItem.image,
            unitId,
            unitLabel,
            qty
        );

        if (addItemModal) {
            addItemModal.hide();
        }
    }

    function showVariantModal(variants, productImage, productId) {
        $('#variantLoading').hide();
        $('#variantList').hide();
        $('#variantEmpty').hide();
        if (!variants.length) {
            $('#variantEmpty').show();
        } else {
            var html = '<div class="variant-grid">';
            variants.forEach(function (v, idx) {
                var img = v.image || productImage || 'https://placehold.co/300x225/f8f9fa/b0b7c3?text=?';
                var stockClass = v.stock > 10 ? 'stock-ok' : (v.stock > 0 ? 'stock-low' : 'stock-out');
                var displayPrice = v.selling_price;
                if (!displayPrice && (v.unit_options || []).length) {
                    displayPrice = v.unit_options[0].suggested_price;
                }
                html += '<div class="variant-card variant-item" role="button" tabindex="0" data-variant-index="' + idx + '">';
                html += '<img src="' + img + '" alt="' + escapeHtml(v.display_name) + '" class="v-img" onerror="this.src=\'https://placehold.co/300x225/f8f9fa/b0b7c3?text=?\'">';
                html += '<div class="v-body">';
                html += '<div class="v-name">' + escapeHtml(v.display_name) + '</div>';
                html += '<div class="v-price">Rp ' + Number(displayPrice || 0).toLocaleString('id-ID') + '</div>';
                html += '<span class="v-stock ' + stockClass + '">Stok: ' + v.stock + '</span>';
                html += '</div></div>';
            });
            html += '</div>';
            $('#variantList').data('variants', variants).data('product-image', productImage).html(html).show();
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
            if (marketingPromoBlocked && selectedMarketingPromo) {
                Swal.fire({
                    icon: 'warning',
                    text: marketingPromoBlockMessage || 'Syarat promo belum terpenuhi.',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
                return;
            }
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

        $('#btnOrder').on('click', function () {
            if (marketingPromoBlocked && selectedMarketingPromo) {
                Swal.fire({
                    icon: 'warning',
                    text: marketingPromoBlockMessage || 'Syarat promo belum terpenuhi.',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
                return;
            }
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
            submitOrderOnly();
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

    function addToCart(variantId, name, price, image, unitId, unitLabel, qty) {
        qty = parseInt(qty, 10) || 1;
        $('#emptyCart').hide();
        var existing = $cartPaidItems().filter(function () {
            return $(this).data('variant-id') == variantId && String($(this).data('unit-id') || '') === String(unitId || '');
        });
        if (existing.length > 0) {
            var inp = existing.find('.quantity-input');
            inp.val(parseInt(inp.val(), 10) + qty);
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
        item.find('.quantity-input').val(qty).data('unit-price', price);
        $('#cartItems').append(item);
        if (window.agentPosIsMobile && window.agentPosIsMobile()) {
            window.agentPosSetMobileView('cart');
        }
        updateCartTotals();
        checkEmptyCart();
    }

    function updateCartTotals(revalidateMarketing) {
        if (revalidateMarketing !== false && selectedMarketingPromo) {
            revalidateSelectedMarketingPromo(false);
        }

        var subtotalNet = getCartSubtotalNet();
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

        var total = Math.max(0, subtotalNet - txnDiscAmt + getShippingAmount());
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
        updatePayButtonState();
        refreshPromoPreview();
    }

    function checkEmptyCart() {
        if ($cartLineItems().length > 0) {
            $('#emptyCart').hide();
        } else {
            $('#emptyCart').show();
            $('#promoHintRow').hide();
            if (selectedMarketingPromo) {
                clearMarketingPromo(true);
            }
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
        clearMarketingPromo(true);
        $('#shippingInput').val('0');
        $('#customerSelect').val(null).trigger('change');
        updateResellerAddressDisplay();
        updateCartTotals(false);
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

    function submitOrderOnly() {
        var $btn = $('#btnOrder');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...');

        $.ajax({
            url: routes.order,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                Accept: 'application/json',
            },
            contentType: 'application/json',
            data: JSON.stringify(buildPosOrderPayload()),
            success: function (res) {
                if (res.success) {
                    resetPosUiAfterPayment();
                    if (res.data && res.data.sales_number) {
                        $('#posTrxNumber').text(res.data.sales_number);
                        $('#posTrxNumberWrap').show();
                    }
                    Swal.fire({
                        title: 'Order tersimpan',
                        text: res.message || 'Transaksi disimpan sebagai pending, bisa dibayar di History.',
                        icon: 'success',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false,
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: res.message || 'Gagal menyimpan order.',
                        icon: 'error',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false,
                    });
                }
            },
            error: function (xhr) {
                var msg = xhr.responseJSON?.message || 'Gagal menyimpan order.';
                Swal.fire({
                    title: 'Error',
                    text: msg,
                    icon: 'error',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false,
                });
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="ti ti-clipboard-list me-1"></i> Order');
                updatePayButtonState();
            },
        });
    }

    function processPayment(methodId, methodName, amountPaid, useXendit, xenditChannel) {
        var payload = buildPosOrderPayload();
        payload.payment_method_id = methodId;
        payload.amount_paid = amountPaid;
        if (useXendit && xenditChannel) {
            payload.xendit_channel = xenditChannel;
        }

        $('#paymentModal .pay-denom-cash-pay, #paymentModal .pay-other-btn, #paymentModal .pay-channel-btn').prop('disabled', true);
        $('#payCashPay').html('<span class="spinner-border spinner-border-sm me-1"></span>Memproses...');

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
