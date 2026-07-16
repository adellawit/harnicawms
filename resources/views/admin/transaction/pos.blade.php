<x-app-layout>

    @section('title', 'POS Transaction | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
    @endpush

    @push('page-css')
        <style>
            /* ── POS monochrome accent (ganti var(--pos-accent)) ───────────────────── */
            #posWrapper {
                --pos-accent: #374151;
                --pos-accent-hover: #1f2937;
                --pos-accent-soft: rgba(55, 65, 81, 0.08);
                --pos-accent-ring: rgba(55, 65, 81, 0.14);
                --pos-accent-muted: #f3f4f6;
            }

            /* Select2 must always be above everything */
            .select2-container--open { z-index: 9999 !important; }
            /* Bootstrap modal always on top */
            .modal { z-index: 9990 !important; }
            .modal-backdrop { z-index: 9980 !important; }

            /* ── Top Bar ─────────────────────────────────────────────────── */
            .pos-top-bar {
                display: flex;
                align-items: center;
                gap: 1rem;
                padding: 0.625rem 1rem;
                background: #fff;
                border-bottom: 1px solid #e7e7e7;
                border-radius: 0.5rem 0.5rem 0 0;
                flex-wrap: wrap;
            }
            .pos-top-bar .pos-title {
                font-size: 1rem;
                font-weight: 700;
                color: #2c3e50;
                white-space: nowrap;
            }
            .pos-top-bar .pos-meta {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                font-size: 0.75rem;
                color: #9aa4b8;
            }
            .pos-top-bar .pos-meta .meta-val { color: #2c3e50; font-weight: 600; }
            .pos-top-bar .pos-meta .meta-id { color: #ff9f43; font-weight: 700; }
            .pos-top-bar .pos-controls {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin-left: auto;
            }
            .pos-top-bar .pos-controls .select2-container { min-width: 180px; }

            /* ── Main Two-Column ─────────────────────────────────────────── */
            .pos-main {
                display: flex;
                height: calc(100vh - 240px);
                min-height: 500px;
                background: #fff;
                border-radius: 0 0 0.5rem 0.5rem;
                overflow: hidden;
            }

            /* ── Left: Catalog ───────────────────────────────────────────── */
            .pos-catalog {
                flex: 1;
                display: flex;
                flex-direction: column;
                border-right: 1px solid #e7e7e7;
                min-width: 0;
            }
            .pos-catalog-header {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.75rem 1rem;
                border-bottom: 1px solid #f0f0f0;
                flex-wrap: wrap;
            }
            .pos-search {
                position: relative;
                flex: 1;
                min-width: 160px;
                max-width: 300px;
            }
            .pos-search input {
                padding-left: 2.25rem;
                height: 36px;
                border-radius: 2rem;
                border: 1px solid #e7e7e7;
                font-size: 0.8125rem;
                width: 100%;
            }
            .pos-search input:focus { border-color: var(--pos-accent); box-shadow: 0 0 0 0.2rem var(--pos-accent-ring); }
            .pos-search .icon {
                position: absolute;
                left: 0.75rem;
                top: 50%;
                transform: translateY(-50%);
                color: #9aa4b8;
                font-size: 0.875rem;
            }

            /* Product type pills */
            .pos-categories {
                display: flex;
                gap: 0.375rem;
                flex-wrap: nowrap;
                overflow-x: auto;
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
            .pos-categories::-webkit-scrollbar { display: none; }
            .pos-category-pill {
                padding: 0.3rem 0.75rem;
                border-radius: 2rem;
                font-size: 0.75rem;
                font-weight: 500;
                white-space: nowrap;
                cursor: pointer;
                border: 1px solid #e7e7e7;
                background: #fff;
                color: #677788;
                transition: all 0.15s;
            }
            .pos-category-pill:hover { border-color: var(--pos-accent); color: var(--pos-accent); }
            .pos-category-pill.active {
                background: var(--pos-accent);
                color: #fff;
                border-color: var(--pos-accent);
            }
            .pos-category-pill .pill-count {
                display: inline-block;
                background: rgba(255,255,255,0.25);
                padding: 0 0.35rem;
                border-radius: 1rem;
                font-size: 0.625rem;
                margin-left: 0.25rem;
            }
            .pos-category-pill.active .pill-count { background: rgba(255,255,255,0.3); }
            .pos-category-pill:not(.active) .pill-count { background: var(--pos-accent-muted); color: var(--pos-accent); }

            /* Product grid */
            #posWrapper #productGrid.pos-product-grid {
                flex: 1;
                display: grid !important;
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)) !important;
                grid-auto-rows: minmax(min-content, auto);
                gap: 0.75rem !important;
                padding: 0.75rem 1rem;
                overflow-y: auto;
                align-content: start;
                align-items: stretch;
            }
            .pos-product-grid::-webkit-scrollbar { width: 5px; }
            .pos-product-grid::-webkit-scrollbar-thumb { background: #d0d0d0; border-radius: 3px; }

            #posWrapper #productGrid > .pos-product-card {
                display: flex;
                flex-direction: column;
                border: 1px solid #eee;
                border-radius: 0.5rem;
                overflow: hidden;
                cursor: pointer;
                transition: border-color 0.15s, box-shadow 0.15s;
                background: #fff;
                width: 100%;
                min-width: 0;
                height: 100%;
                min-height: 100%;
                margin: 0 !important;
                position: relative;
                z-index: 0;
            }
            #posWrapper #productGrid > .pos-product-card:hover {
                border-color: var(--pos-accent);
                box-shadow: 0 3px 10px var(--pos-accent-ring);
                z-index: 1;
            }
            .pos-product-card .p-img-wrap {
                flex-shrink: 0;
                position: relative;
                width: 100%;
                height: 0;
                padding-top: 75%;
                overflow: hidden;
                background: #f8f9fa;
            }
            .pos-product-card .p-img {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100% !important;
                max-width: 100%;
                object-fit: cover;
                background: #f8f9fa;
            }
            .pos-product-card .p-body {
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
                min-height: 3.75rem;
                padding: 0.5rem 0.625rem 0.875rem;
                background: #fff;
                border-top: 1px solid #f0f0f0;
            }
            .pos-product-card .p-name {
                flex: 1 1 auto;
                min-height: 2.7em;
                font-size: 0.8125rem;
                font-weight: 600;
                color: #2c3e50;
                line-height: 1.35;
                margin: 0;
                white-space: normal;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                word-break: break-word;
            }

            /* Variant modal — card grid */
            .variant-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
                gap: 0.75rem;
            }
            .variant-card {
                border: 1px solid #eee;
                border-radius: 0.5rem;
                overflow: hidden;
                cursor: pointer;
                transition: all 0.15s;
                background: #fff;
            }
            .variant-card:hover:not(.disabled) {
                border-color: var(--pos-accent);
                box-shadow: 0 3px 10px var(--pos-accent-ring);
                transform: translateY(-1px);
            }
            .variant-card:active:not(.disabled) { transform: translateY(0); }
            .variant-card.disabled {
                opacity: 0.55;
                cursor: not-allowed;
            }
            .variant-card .v-img {
                width: 100%;
                aspect-ratio: 4/3;
                object-fit: cover;
                background: #f8f9fa;
            }
            .variant-card .v-body {
                padding: 0.5rem 0.625rem;
            }
            .variant-card .v-name {
                font-size: 0.8rem;
                font-weight: 600;
                color: #2c3e50;
                line-height: 1.25;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            .variant-card .v-price {
                font-size: 0.75rem;
                font-weight: 600;
                color: #28a745;
                margin-top: 0.25rem;
            }
            .variant-card .v-stock {
                display: inline-block;
                font-size: 0.625rem;
                margin-top: 0.35rem;
                padding: 0.15rem 0.4rem;
                border-radius: 0.25rem;
            }
            .variant-card .v-stock.stock-ok { background: #e8f5e9; color: #2e7d32; }
            .variant-card .v-stock.stock-low { background: #fff8e1; color: #f57f17; }
            .variant-card .v-stock.stock-out { background: #ffebee; color: #c62828; }

            /* ── Right: Cart ─────────────────────────────────────────────── */
            .pos-cart {
                width: 440px;
                min-width: 400px;
                display: flex;
                flex-direction: column;
                background: #fafbfe;
            }

            /* Cart top: customer + items badge */
            .pos-cart-top {
                padding: 0.625rem 0.75rem;
                border-bottom: 1px solid #f0f0f0;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .pos-cart-top .cart-badge {
                background: var(--pos-accent);
                color: #fff;
                font-size: 0.6875rem;
                font-weight: 700;
                padding: 0.2rem 0.5rem;
                border-radius: 2rem;
                white-space: nowrap;
            }
            .pos-customer-option {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.5rem;
                width: 100%;
            }
            .pos-customer-option .pos-customer-name {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .pos-customer-badge {
                font-size: 0.625rem;
                font-weight: 600;
                padding: 0.15rem 0.45rem;
                border-radius: 999px;
                white-space: nowrap;
            }
            .pos-customer-badge.agent { background: #e8f5e9; color: #2e7d32; }
            .pos-customer-badge.reseller { background: #e3f2fd; color: #1565c0; }
            .pos-customer-badge.partner-lead { background: #fff8e1; color: #f57f17; }
            #selectedPartnerBadge {
                display: none;
                font-size: 0.6875rem;
                font-weight: 600;
                padding: 0.2rem 0.5rem;
                border-radius: 999px;
                white-space: nowrap;
            }
            #selectedPartnerBadge.agent { background: #e8f5e9; color: #2e7d32; }
            #selectedPartnerBadge.reseller { background: #e3f2fd; color: #1565c0; }
            #selectedPartnerBadge.partner-lead { background: #fff8e1; color: #f57f17; }
            .pos-points-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
                font-size: 0.68rem;
                font-weight: 700;
                background: #fff3e0;
                color: #e65100;
                border-radius: 0.25rem;
                padding: 0.15rem 0.4rem;
                white-space: nowrap;
            }
            .pos-redeem-block {
                margin-top: 0.5rem;
                padding-top: 0.5rem;
                border-top: 1px dashed #e7e7e7;
            }
            .pos-redeem-block label { font-size: 0.75rem; font-weight: 600; color: #677788; }
            .pos-redeem-block .pos-discount-input-group { gap: 0.35rem; }

            /* Cart items list */
            .pos-cart-items {
                flex: 1;
                overflow-y: auto;
                padding: 0.5rem 0.75rem;
            }
            .pos-cart-items::-webkit-scrollbar { width: 4px; }
            .pos-cart-items::-webkit-scrollbar-thumb { background: #d0d0d0; border-radius: 2px; }

            .pos-cart-item {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 0.625rem;
                padding: 0.5rem;
                margin-bottom: 0.5rem;
                border-radius: 0.5rem;
                background: #fff;
                border: 1px solid #f0f0f0;
                transition: border-color 0.15s;
            }
            .pos-cart-item:hover { border-color: #ddd; }
            .pos-cart-item .ci-img {
                width: 44px;
                height: 44px;
                border-radius: 0.375rem;
                object-fit: cover;
                flex-shrink: 0;
            }
            .pos-cart-item .ci-info { flex: 1; min-width: 0; }
            .pos-cart-item .ci-name {
                font-size: 0.8rem;
                font-weight: 600;
                color: #2c3e50;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .pos-cart-item .ci-price { font-size: 0.725rem; color: #677788; }
            .pos-cart-item .ci-qty-unit {
                font-size: 0.68rem;
                color: #8592a3;
                font-weight: 500;
                white-space: nowrap;
                min-width: 1.5rem;
            }
            .pos-cart-item .ci-right { display: flex; align-items: center; gap: 0.375rem; }
            .pos-cart-item .ci-qty {
                display: flex;
                align-items: center;
                border: 1px solid #e7e7e7;
                border-radius: 0.375rem;
                overflow: hidden;
            }
            .pos-cart-item .ci-qty button {
                width: 26px;
                height: 26px;
                border: none;
                background: #f8f8f8;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.875rem;
                color: #555;
            }
            .pos-cart-item .ci-qty button:hover { background: #eee; }
            .pos-cart-item .ci-qty input {
                width: 32px;
                height: 26px;
                border: none;
                border-left: 1px solid #e7e7e7;
                border-right: 1px solid #e7e7e7;
                text-align: center;
                font-size: 0.8rem;
                font-weight: 600;
                -moz-appearance: textfield;
                appearance: textfield;
            }
            .pos-cart-item .ci-qty input::-webkit-outer-spin-button,
            .pos-cart-item .ci-qty input::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }
            .pos-cart-item .ci-delete {
                width: 26px;
                height: 26px;
                border: none;
                border-radius: 0.25rem;
                background: #ffebe9;
                color: #cf222e;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .pos-cart-item .ci-delete:hover { background: #ffd4d0; }

            /* Item discount row */
            .pos-cart-item .ci-bottom {
                display: none;
                width: 100%;
                padding-top: 0.375rem;
                margin-top: 0.375rem;
                border-top: 1px dashed #eee;
            }
            .pos-cart-item.has-discount .ci-bottom { display: flex; }
            .pos-cart-item .ci-disc-group {
                display: flex;
                align-items: center;
                gap: 0;
                border: 1px solid #e7e7e7;
                border-radius: 0.25rem;
                overflow: hidden;
                flex: 1;
                max-width: 200px;
            }
            .pos-cart-item .ci-disc-group input {
                flex: 1;
                border: none;
                padding: 0.2rem 0.4rem;
                font-size: 0.725rem;
                min-width: 0;
                outline: none;
            }
            .pos-cart-item .ci-disc-toggle {
                display: flex;
                flex-shrink: 0;
                border-left: 1px solid #e7e7e7;
            }
            .pos-cart-item .ci-disc-toggle button {
                border: none;
                background: #f8f8f8;
                padding: 0.2rem 0.4rem;
                font-size: 0.65rem;
                font-weight: 600;
                cursor: pointer;
                color: #677788;
            }
            .pos-cart-item .ci-disc-toggle button:not(:last-child) { border-right: 1px solid #e7e7e7; }
            .pos-cart-item .ci-disc-toggle button:hover { background: #eee; }
            .pos-cart-item .ci-disc-toggle button.active { background: var(--pos-accent); color: #fff; }
            .pos-cart-item .ci-disc-label {
                font-size: 0.65rem;
                color: #cf222e;
                margin-left: 0.375rem;
                white-space: nowrap;
            }
            .pos-cart-item .ci-disc-btn {
                width: 26px;
                height: 26px;
                border: none;
                border-radius: 0.25rem;
                background: var(--pos-accent-muted);
                color: var(--pos-accent);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .pos-cart-item .ci-disc-btn:hover { background: var(--pos-accent-muted); }
            .pos-cart-item.has-discount .ci-disc-btn { background: var(--pos-accent); color: #fff; }

            .pos-cart-item.is-promo-free {
                background: #f3faf5;
                border-color: #b7e4c7;
            }
            .pos-cart-item.is-promo-free .ci-name {
                display: flex;
                align-items: center;
                gap: 0.35rem;
                flex-wrap: wrap;
            }
            .pos-cart-item.is-promo-free .ci-promo-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.2rem;
                font-size: 0.65rem;
                font-weight: 700;
                letter-spacing: 0.02em;
                color: #146c2e;
                background: #d8f3dc;
                border-radius: 0.25rem;
                padding: 0.1rem 0.35rem;
            }
            .pos-cart-item.is-promo-free .ci-price { color: #146c2e; font-weight: 600; }
            .pos-cart-item.is-promo-free .ci-qty button,
            .pos-cart-item.is-promo-free .ci-disc-btn,
            .pos-cart-item.is-promo-free .ci-delete { display: none !important; }
            .pos-cart-item.is-promo-free .ci-qty input {
                border: none;
                background: transparent;
                font-weight: 700;
                color: #146c2e;
            }
            .pos-cart-item.is-promo-free .ci-bottom { display: none !important; }
            #promoHintRow {
                display: none;
                font-size: 0.75rem;
                color: #146c2e;
                background: #f3faf5;
                border: 1px dashed #b7e4c7;
                border-radius: 0.375rem;
                padding: 0.4rem 0.55rem;
                margin: 0 0.75rem 0.5rem;
            }

            .pos-empty-cart {
                text-align: center;
                padding: 2.5rem 1rem;
                color: #b0b7c3;
            }
            .pos-empty-cart .empty-icon { font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.5rem; }

            /* Cart footer / summary */
            .pos-cart-footer {
                border-top: 1px solid #e7e7e7;
                background: #fff;
                padding: 0.75rem;
            }
            .pos-summary-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 0.8rem;
                color: #677788;
                margin-bottom: 0.35rem;
            }
            .pos-summary-row.total-row {
                margin-top: 0.5rem;
                padding-top: 0.625rem;
                border-top: 2px dashed #e7e7e7;
                font-size: 1.05rem;
                font-weight: 700;
                color: #2c3e50;
            }
            .pos-summary-row.total-row .total-val { color: #2da44e; }

            /* Payment row */
            .pos-payment-row {
                display: flex;
                gap: 0.375rem;
                margin-top: 0.625rem;
                flex-wrap: wrap;
            }
            .pos-pay-chip {
                flex: 1;
                min-width: 0;
                padding: 0.4rem 0.25rem;
                text-align: center;
                border: 2px solid #e7e7e7;
                border-radius: 0.375rem;
                cursor: pointer;
                transition: all 0.15s;
                font-size: 0.7rem;
                font-weight: 500;
                color: #677788;
                background: #fff;
            }
            .pos-pay-chip:hover { border-color: var(--pos-accent); }
            .pos-pay-chip.active {
                border-color: var(--pos-accent);
                background: var(--pos-accent-soft);
                color: var(--pos-accent);
            }
            .pos-pay-chip .chip-icon { font-size: 1rem; display: block; margin-bottom: 0.125rem; }

            /* Discount block */
            .pos-discount-block {
                margin-top: 0.25rem;
                margin-bottom: 0.35rem;
            }
            .pos-discount-block label {
                font-size: 0.8rem;
                color: #677788;
                margin-bottom: 0.3rem;
                display: block;
            }
            .pos-discount-input-group {
                display: flex;
                align-items: center;
                gap: 0;
                border: 1px solid #e7e7e7;
                border-radius: 0.375rem;
                overflow: hidden;
                background: #fff;
            }
            .pos-discount-input-group input {
                flex: 1;
                border: none;
                padding: 0.375rem 0.625rem;
                font-size: 0.8rem;
                min-width: 0;
                outline: none;
            }
            .pos-discount-input-group input:focus { box-shadow: none; }
            .pos-disc-toggle {
                display: flex;
                flex-shrink: 0;
                border-left: 1px solid #e7e7e7;
            }
            .pos-disc-toggle button {
                border: none;
                background: #f8f8f8;
                padding: 0.375rem 0.625rem;
                font-size: 0.75rem;
                font-weight: 600;
                cursor: pointer;
                color: #677788;
                transition: all 0.15s;
            }
            .pos-disc-toggle button:not(:last-child) { border-right: 1px solid #e7e7e7; }
            .pos-disc-toggle button:hover { background: #eee; }
            .pos-disc-toggle button.active {
                background: var(--pos-accent);
                color: #fff;
            }
            .pos-discount-display {
                font-size: 0.8rem;
                color: #677788;
                text-align: right;
                margin-top: 0.2rem;
            }

            /* Action buttons */
            .pos-action-row { display: flex; gap: 0.5rem; margin-top: 0.625rem; }
            .pos-action-row .btn { flex: 1; font-weight: 600; padding: 0.6rem 0.5rem; font-size: 0.85rem; }

            /* ── Payment Modal ──────────────────────────────────────────── */
            .pay-modal { border-radius: 0.75rem; overflow: hidden; border: none; }
            .pay-modal-header {
                display: flex; align-items: center; justify-content: space-between;
                padding: 1.25rem 1.5rem; background: #f8f9fa; border-bottom: 1px solid #e7e7e7;
            }
            .pay-grand-label { font-size: 0.8rem; color: #9aa4b8; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
            .pay-grand-total { font-size: 1.75rem; font-weight: 800; color: #2c3e50; }
            .pay-close-btn {
                border: none; background: #ff9f43; color: #fff; font-weight: 700;
                padding: 0.5rem 1.25rem; border-radius: 0.375rem; font-size: 0.85rem; cursor: pointer;
            }
            .pay-close-btn:hover { background: #e8913a; }
            .pay-modal-body { display: flex; gap: 0; min-height: 320px; }
            .pay-col-cash { flex: 1; padding: 1.25rem 1.5rem; border-right: 1px solid #e7e7e7; }
            .pay-col-other { flex: 1; padding: 1.25rem 1.5rem; }
            .pay-col-title { font-size: 0.8rem; font-weight: 700; color: #677788; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.75rem; text-align: center; }
            .pay-cash-input {
                width: 100%; border: 2px solid #e7e7e7; border-radius: 0.375rem;
                padding: 0.625rem 0.75rem; font-size: 1.25rem; font-weight: 700;
                text-align: right; outline: none; margin-bottom: 0.75rem;
            }
            .pay-cash-input:focus { border-color: var(--pos-accent); }
            .pay-denom-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 0.5rem;
            }
            .pay-denom-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0.5rem;
                margin-top: 0.5rem;
            }
            .pay-denom-actions .pay-denom { margin: 0; }
            .pay-denom-pay-wrap { margin-top: 0.5rem; }
            .pay-denom-pay-wrap .pay-denom-cash-pay {
                width: 100%;
                display: block;
            }
            .pay-denom {
                border: 1px solid #e0e2e7; background: #f8f9fa; color: #2c3e50;
                font-weight: 600; font-size: 0.85rem; padding: 0.6rem 0.25rem;
                border-radius: 0.375rem; cursor: pointer; text-align: center;
                transition: all 0.15s;
            }
            .pay-denom:hover { background: #eef0f7; border-color: #c0c4cc; }
            .pay-denom-clear { background: #ea5455; color: #fff; border-color: #ea5455; }
            .pay-denom-clear:hover { background: #d44a4b; border-color: #d44a4b; }
            .pay-denom-exact { background: #28c76f; color: #fff; border-color: #28c76f; }
            .pay-denom-exact:hover { background: #22b362; border-color: #22b362; }
            .pay-denom-cash-pay { background: #28c76f; color: #fff; border-color: #28c76f; font-size: 0.95rem; }
            .pay-denom-cash-pay:hover { background: #22b362; border-color: #22b362; }
            .pay-other-list { display: flex; flex-direction: column; gap: 0.5rem; }
            .pay-channel-groups {
                max-height: 340px;
                overflow-y: auto;
                padding-right: 0.25rem;
            }
            .pay-channel-groups::-webkit-scrollbar { width: 4px; }
            .pay-channel-groups::-webkit-scrollbar-thumb { background: #d0d0d0; border-radius: 2px; }
            .pay-channel-group { margin-bottom: 0.875rem; }
            .pay-channel-group:last-child { margin-bottom: 0; }
            .pay-channel-group-title {
                display: flex;
                align-items: center;
                gap: 0.4rem;
                font-size: 0.72rem;
                font-weight: 700;
                color: #677788;
                text-transform: uppercase;
                letter-spacing: 0.4px;
                margin-bottom: 0.4rem;
                padding-bottom: 0.25rem;
                border-bottom: 1px solid #eceef1;
            }
            .pay-channel-group-title i {
                font-size: 0.95rem;
                color: var(--pos-accent);
            }
            .pay-channel-btn {
                display: flex;
                align-items: center;
                gap: 0.625rem;
                width: 100%;
                text-align: left;
                padding: 0.4rem 0.625rem;
                margin-bottom: 0.25rem;
                border: 1px solid #e7e7e7;
                border-radius: 0.5rem;
                background: #fff;
                font-size: 0.8125rem;
                font-weight: 500;
                color: #2c3e50;
                cursor: pointer;
                transition: all 0.15s;
            }
            .pay-channel-icon {
                width: 32px;
                height: 32px;
                object-fit: contain;
                border-radius: 0.375rem;
                flex-shrink: 0;
                background: #f8f9fa;
                border: 1px solid #f0f0f0;
            }
            .pay-channel-label { flex: 1; min-width: 0; }
            .pay-channel-btn:hover:not(:disabled) {
                border-color: var(--pos-accent);
                background: var(--pos-accent-soft);
                color: var(--pos-accent);
            }
            .pay-channel-btn:disabled { opacity: 0.6; cursor: not-allowed; }
            .pay-other-btn {
                display: flex;
                align-items: center;
                gap: 0.625rem;
                width: 100%;
                border: 1px solid #e7e7e7;
                background: #fff;
                color: #2c3e50;
                font-weight: 600;
                font-size: 0.8125rem;
                padding: 0.5rem 0.625rem;
                border-radius: 0.5rem;
                cursor: pointer;
                text-align: left;
                transition: all 0.15s;
            }
            .pay-other-btn:hover:not(:disabled) {
                border-color: var(--pos-accent);
                background: var(--pos-accent-soft);
                color: var(--pos-accent);
            }

            /* Payment modal — mobile tabs (Cash / Lainnya) */
            .pay-mobile-tabs {
                display: none;
                gap: 0.5rem;
                padding: 0.5rem 0.75rem;
                background: #fff;
                border-bottom: 1px solid #e7e7e7;
                flex-shrink: 0;
            }
            .pay-mobile-tab {
                flex: 1;
                padding: 0.55rem 0.75rem;
                border: 1px solid #e7e7e7;
                border-radius: 0.5rem;
                background: #fff;
                font-size: 0.8125rem;
                font-weight: 600;
                color: #677788;
                cursor: pointer;
                transition: all 0.15s;
            }
            .pay-mobile-tab.active {
                background: var(--pos-accent);
                border-color: var(--pos-accent);
                color: #fff;
            }
            .pay-channel-grid {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }

            /* ── Xendit checkout (inline di halaman POS) ───────────────── */
            .xendit-checkout-overlay {
                display: none;
                position: fixed;
                inset: 0;
                z-index: 10050;
                background: #fff;
                flex-direction: column;
            }
            body.xendit-checkout-active .xendit-checkout-overlay {
                display: flex;
            }
            .xendit-checkout-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: 0.75rem 1rem;
                background: #f8f9fa;
                border-bottom: 1px solid #e7e7e7;
                flex-shrink: 0;
            }
            .xendit-checkout-bar .xendit-checkout-label {
                font-size: 0.75rem;
                color: #9aa4b8;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                display: block;
            }
            .xendit-checkout-frame {
                flex: 1;
                width: 100%;
                border: none;
                background: #fff;
            }
            .xendit-checkout-fallback {
                display: none;
                flex: 1;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 2rem;
                text-align: center;
            }
            body.xendit-checkout-fallback .xendit-checkout-frame { display: none; }
            body.xendit-checkout-fallback .xendit-checkout-fallback { display: flex; }

            /* ── Mobile tab bar (Produk / Keranjang) ─────────────────────── */
            .pos-mobile-tabs {
                display: none;
                gap: 0.5rem;
                padding: 0.5rem 0.75rem;
                background: #fff;
                border-bottom: 1px solid #e7e7e7;
            }
            .pos-mobile-tab {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.35rem;
                padding: 0.55rem 0.75rem;
                border: 1px solid #e7e7e7;
                border-radius: 0.5rem;
                background: #fff;
                font-size: 0.8125rem;
                font-weight: 600;
                color: #677788;
                cursor: pointer;
                transition: all 0.15s;
            }
            .pos-mobile-tab.active {
                background: var(--pos-accent);
                border-color: var(--pos-accent);
                color: #fff;
            }
            .pos-mobile-tab-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 1.25rem;
                height: 1.25rem;
                padding: 0 0.35rem;
                border-radius: 1rem;
                font-size: 0.6875rem;
                font-weight: 700;
                background: rgba(255,255,255,0.25);
            }
            .pos-mobile-tab:not(.active) .pos-mobile-tab-badge {
                background: var(--pos-accent-muted);
                color: var(--pos-accent);
            }

            /* ── Responsive ──────────────────────────────────────────────── */
            @media (max-width: 991.98px) {
                .pos-main {
                    flex-direction: column;
                    height: auto;
                    min-height: 0;
                }
                .pos-catalog {
                    border-right: none;
                    border-bottom: 1px solid #e7e7e7;
                    min-height: 320px;
                }
                .pos-cart {
                    width: 100%;
                    min-width: 0;
                    max-width: 100%;
                }
                .pos-top-bar { flex-wrap: wrap; gap: 0.5rem; }
                .pos-top-bar .pos-controls {
                    width: 100%;
                    margin-left: 0;
                    justify-content: space-between;
                }
                .pos-top-bar .pos-controls .select2-container { min-width: 0; flex: 1; }
                .pos-top-bar .pos-controls #priceListWrapper { flex: 1; min-width: 0; }
                .pos-top-bar .pos-controls #priceListSelect { width: 100% !important; }
                .pay-modal-body { flex-direction: column; min-height: 0; }
                .pay-col-cash { border-right: none; border-bottom: 1px solid #e7e7e7; }
                .pay-denom-grid { grid-template-columns: repeat(3, 1fr); }
                #posWrapper #productGrid.pos-product-grid {
                    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)) !important;
                    gap: 0.5rem !important;
                    padding: 0.5rem 0.75rem;
                }
            }

            @media (max-width: 767.98px) {
                #posWrapper.container-p-y {
                    padding-top: 0.5rem !important;
                    padding-bottom: 0.5rem !important;
                    padding-left: 0.5rem !important;
                    padding-right: 0.5rem !important;
                }
                .pos-top-bar {
                    border-radius: 0.375rem 0.375rem 0 0;
                    padding: 0.5rem 0.75rem;
                }
                .pos-title { font-size: 0.9rem; }
                .pos-meta {
                    width: 100%;
                    overflow-x: auto;
                    flex-wrap: nowrap;
                    -webkit-overflow-scrolling: touch;
                    padding-bottom: 0.125rem;
                }
                .pos-catalog-header {
                    flex-direction: column;
                    align-items: stretch;
                    padding: 0.5rem 0.75rem;
                }
                .pos-search {
                    max-width: none;
                    width: 100%;
                }
                .pos-categories {
                    width: 100%;
                }
                .pos-mobile-tabs { display: flex; }
                .pos-main {
                    flex: 1;
                    height: calc(100dvh - 11rem);
                    min-height: 280px;
                    border-radius: 0 0 0.375rem 0.375rem;
                    overflow: hidden;
                }
                .pos-main .pos-catalog,
                .pos-main .pos-cart {
                    flex: 1;
                    min-height: 0;
                    overflow: hidden;
                    border-bottom: none;
                }
                .pos-main:not(.pos-mobile-view-cart) .pos-cart { display: none; }
                .pos-main.pos-mobile-view-cart .pos-catalog { display: none; }
                #posWrapper #productGrid.pos-product-grid {
                    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)) !important;
                    gap: 0.5rem !important;
                }
                .pos-cart-footer {
                    padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));
                }
                .pos-cart-item .ci-name { white-space: normal; }
                .pos-action-row .btn { padding: 0.75rem 0.5rem; }
                .variant-grid {
                    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                }
                #variantModal .modal-dialog {
                    margin: 0.5rem;
                    max-width: calc(100% - 1rem);
                }
                #paymentModal .modal-dialog {
                    margin: 0;
                    max-width: 100%;
                    height: 100%;
                }
                #paymentModal .pay-modal {
                    display: flex;
                    flex-direction: column;
                    height: 100%;
                    max-height: 100dvh;
                    border-radius: 0;
                }
                #paymentModal .pay-modal-header {
                    flex-shrink: 0;
                    flex-wrap: nowrap;
                    gap: 0.5rem;
                    padding: 0.75rem 1rem;
                    padding-top: calc(0.75rem + env(safe-area-inset-top, 0px));
                }
                #paymentModal .pay-modal-header > div:first-child { flex: 1; min-width: 0; }
                .pay-grand-total { font-size: 1.25rem; line-height: 1.2; word-break: break-word; }
                .pay-close-btn {
                    flex-shrink: 0;
                    width: auto;
                    padding: 0.5rem 0.75rem;
                    font-size: 0.75rem;
                    white-space: nowrap;
                }
                .pay-mobile-tabs { display: flex; }
                #paymentModal .pay-modal-body {
                    flex: 1;
                    min-height: 0;
                    overflow-y: auto;
                    -webkit-overflow-scrolling: touch;
                    flex-direction: column;
                }
                #paymentModal .pay-modal-body:not(.pay-mobile-view-other) .pay-col-other { display: none; }
                #paymentModal .pay-modal-body.pay-mobile-view-other .pay-col-cash { display: none; }
                #paymentModal .pay-modal-body.pay-mobile-view-other .pay-col-other { display: block; }
                .pay-col-cash,
                .pay-col-other {
                    padding: 0.75rem 1rem;
                    padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));
                    flex: none;
                    width: 100%;
                }
                .pay-col-cash { border-bottom: none; }
                .pay-cash-input {
                    font-size: 1.5rem;
                    padding: 0.75rem;
                    min-height: 3rem;
                }
                .pay-denom-grid {
                    grid-template-columns: repeat(3, 1fr);
                    gap: 0.4rem;
                }
                .pay-denom {
                    padding: 0.65rem 0.2rem;
                    font-size: 0.8rem;
                    min-height: 2.75rem;
                }
                .pay-denom-pay-wrap .pay-denom-cash-pay {
                    min-height: 3rem;
                    font-size: 1rem;
                }
                .pay-denom-actions .pay-denom {
                    min-height: 2.75rem;
                    font-size: 0.85rem;
                }
                .pay-channel-groups {
                    max-height: none;
                    overflow: visible;
                }
                .pay-channel-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 0.5rem;
                }
                .pay-channel-btn,
                .pay-other-btn {
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    padding: 0.5rem 0.35rem;
                    margin-bottom: 0;
                    min-height: 4.5rem;
                    gap: 0.35rem;
                }
                .pay-channel-icon {
                    width: 36px;
                    height: 36px;
                }
                .pay-channel-label {
                    font-size: 0.7rem;
                    line-height: 1.2;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }
                .xendit-checkout-bar {
                    flex-wrap: wrap;
                    padding: 0.5rem 0.75rem;
                }
            }

            @media (max-width: 575.98px) {
                .pay-denom-grid { grid-template-columns: repeat(2, 1fr); }
                .pay-channel-grid { grid-template-columns: 1fr; }
                .pay-channel-btn,
                .pay-other-btn { min-height: 3.5rem; flex-direction: row; text-align: left; }
                .pay-channel-label { -webkit-line-clamp: 1; }
                #posWrapper #productGrid.pos-product-grid {
                    grid-template-columns: repeat(2, 1fr) !important;
                    gap: 0.75rem !important;
                }
                .variant-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
                .pos-cart-item {
                    gap: 0.5rem;
                }
                .pos-cart-item .ci-right {
                    width: 100%;
                    justify-content: flex-end;
                }
            }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y pos-content-wrapper" id="posWrapper">

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <!-- ═══ TOP BAR ═══ -->
        <div class="pos-top-bar">
            <span class="pos-title"><i class="ti ti-receipt me-1"></i>POS</span>
            <div class="pos-meta">
                <span id="posTrxNumberWrap" style="display:none"><span class="meta-id" id="posTrxNumber"></span></span>
                <span>{{ date('d M Y') }}</span>
                <span id="posClock">{{ date('H:i') }}</span>
                <span><span id="cartItemCount" class="meta-val">0</span> items</span>
            </div>
            <div class="pos-controls">
                <div id="priceListWrapper" data-selected-id="{{ $defaultPriceListId ?? '' }}">
                    <select id="priceListSelect" style="width:200px">
                        <option value="">Select Price List</option>
                        @forelse($priceLists ?? collect() as $pl)
                        <option value="{{ $pl->id }}" {{ ($defaultPriceListId ?? '') == $pl->id ? 'selected' : '' }}>{{ $pl->name }}</option>
                        @empty
                        @endforelse
                    </select>
                </div>
            </div>
        </div>

        <!-- Mobile: switch Produk / Keranjang -->
        <div class="pos-mobile-tabs" id="posMobileTabs" role="tablist" aria-label="POS views">
            <button type="button" class="pos-mobile-tab active" data-pos-view="catalog" role="tab" aria-selected="true">
                <i class="ti ti-layout-grid"></i> Produk
            </button>
            <button type="button" class="pos-mobile-tab" data-pos-view="cart" role="tab" aria-selected="false">
                <i class="ti ti-shopping-cart"></i> Keranjang
                <span class="pos-mobile-tab-badge" id="cartMobileTabBadge">0</span>
            </button>
        </div>

        <!-- ═══ MAIN AREA ═══ -->
        <div class="pos-main" id="posMain">

            <!-- ─── LEFT: Product Catalog ─── -->
            <div class="pos-catalog">
                <div class="pos-catalog-header">
                    <div class="pos-search">
                        <i class="ti ti-search icon"></i>
                        <input type="text" class="form-control" placeholder="Search products..." id="searchProduct">
                    </div>
                    <div class="pos-categories" id="productTypeTabs" aria-label="Product Type">
                        <span class="pos-category-pill active" data-product-type="all">
                            All <span class="pill-count">{{ $products->count() ?? 0 }}</span>
                        </span>
                        @foreach($productTypes ?? collect() as $productType)
                        <span class="pos-category-pill" data-product-type="{{ $productType->id }}">
                            {{ $productType->name }} <span class="pill-count">{{ $productType->products_count ?? 0 }}</span>
                        </span>
                        @endforeach
                    </div>
                </div>

                <div class="pos-product-grid" id="productGrid">
                    @forelse($products ?? collect() as $product)
                    <div class="pos-product-card" data-product-id="{{ $product->id }}" data-product-type-id="{{ $product->nature_id ?? '' }}" data-variants-count="{{ $product->variants_count ?? 0 }}">
                        <div class="p-img-wrap">
                            <img src="{{ $product->image ?: 'https://placehold.co/300x225/f8f9fa/d1d5db?text=+' }}"
                                 alt="{{ $product->name }}"
                                 class="p-img"
                                 loading="lazy"
                                 onerror="this.onerror=null;this.src='https://placehold.co/300x225/f8f9fa/d1d5db?text=+';">
                        </div>
                        <div class="p-body">
                            <div class="p-name" title="{{ $product->name }}">{{ $product->name }}</div>
                        </div>
                    </div>
                    @empty
                    <div class="d-flex flex-column align-items-center justify-content-center py-5 w-100" style="grid-column: 1 / -1;">
                        <i class="ti ti-shopping-cart text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2 mb-0">No products available</p>
                    </div>
                    @endforelse
                </div>
            </div>

            <!-- ─── RIGHT: Cart ─── -->
            <div class="pos-cart">
                <!-- Cart top: customer selector -->
                <div class="pos-cart-top">
                    <select id="customerSelect" style="width:100%">
                        <option value="">Walk-in Customer</option>
                        @forelse($customerSelectGroups ?? [] as $group)
                        <optgroup label="{{ $group['label'] }}">
                            @foreach($group['customers'] as $customer)
                            @php
                                $role = $customer->partnerRole();
                                $partnerCode = $customer->agent?->code ?? $customer->reseller?->code;
                                $shortLabel = $role === 'reseller'
                                    ? '↳ '.($partnerCode ? $partnerCode.' · ' : '').$customer->name
                                    : (($partnerCode ? $partnerCode.' · ' : '').$customer->name);
                            @endphp
                            <option value="{{ $customer->id }}"
                                data-partner-role="{{ $role }}"
                                data-partner-label="{{ $customer->partnerRoleLabel() }}"
                                data-partner-code="{{ $partnerCode }}"
                                data-customer-code="{{ $customer->code }}"
                                data-points-balance="{{ (int) ($customer->points_balance ?? 0) }}"
                                data-earn-point="{{ $customer->customerGroup?->earn_point ? 1 : 0 }}"
                                data-point-multiplier="{{ (float) ($customer->customerGroup?->point_multiplier ?? 1) }}"
                                data-short-label="{{ $shortLabel }}">
                                {{ $shortLabel }}
                            </option>
                            @endforeach
                        </optgroup>
                        @empty
                        @forelse($customers ?? collect() as $customer)
                        <option value="{{ $customer->id }}"
                            data-partner-role="{{ $customer->partnerRole() }}"
                            data-partner-label="{{ $customer->partnerRoleLabel() }}"
                            data-partner-code="{{ $customer->agent?->code ?? $customer->reseller?->code }}"
                            data-customer-code="{{ $customer->code }}"
                            data-points-balance="{{ (int) ($customer->points_balance ?? 0) }}"
                            data-earn-point="{{ $customer->customerGroup?->earn_point ? 1 : 0 }}"
                            data-point-multiplier="{{ (float) ($customer->customerGroup?->point_multiplier ?? 1) }}">
                            {{ $customer->name }}
                        </option>
                        @empty
                        @endforelse
                        @endforelse
                    </select>
                    <span id="selectedPartnerBadge"></span>
                    <span id="selectedPointsBadge" class="pos-points-badge" style="display:none;"></span>
                    <span class="cart-badge"><span id="cartItemCountBadge">0</span></span>
                </div>

                <!-- Cart items -->
                <div class="pos-cart-items" id="cartItems">
                    <div class="pos-empty-cart" id="emptyCart">
                        <i class="ti ti-shopping-cart empty-icon"></i>
                        <p class="mb-1">No items in cart</p>
                        <small>Click a product to add</small>
                    </div>

                    <!-- Hidden template -->
                    <div class="pos-cart-item" style="display:none" id="sampleCartItem">
                        <img src="" class="ci-img" onerror="this.src='https://placehold.co/44x44/f8f9fa/b0b7c3?text=?'">
                        <div class="ci-info">
                            <div class="ci-name">Product</div>
                            <div class="ci-price">Rp 0</div>
                        </div>
                        <div class="ci-right">
                            <div class="ci-qty">
                                <button type="button" class="btn-minus">-</button>
                                <input type="text" value="1" class="quantity-input" inputmode="numeric" pattern="[0-9]*" readonly tabindex="-1" aria-label="Quantity">
                                <button type="button" class="btn-plus">+</button>
                            </div>
                            <span class="ci-qty-unit"></span>
                            <button type="button" class="ci-disc-btn btn-item-disc" title="Item Discount">
                                <i class="ti ti-discount-2" style="font-size:0.7rem"></i>
                            </button>
                            <button type="button" class="ci-delete btn-delete" title="Remove">
                                <i class="ti ti-trash" style="font-size:0.7rem"></i>
                            </button>
                        </div>
                        <div class="ci-bottom">
                            <div class="ci-disc-group">
                                <input type="text" class="item-disc-input" value="0" placeholder="0" autocomplete="off">
                                <div class="ci-disc-toggle">
                                    <button type="button" class="item-disc-type active" data-type="percent">%</button>
                                    <button type="button" class="item-disc-type" data-type="nominal">Rp</button>
                                </div>
                            </div>
                            <span class="ci-disc-label">- <span class="item-disc-display">Rp 0</span></span>
                        </div>
                    </div>
                </div>
                <div id="promoHintRow">
                    <i class="ti ti-gift me-1"></i>
                    <span id="promoHintText">Promo applied</span>
                </div>

                <!-- Footer / Summary -->
                <div class="pos-cart-footer">
                    <div class="pos-summary-row">
                        <span>Sub Total</span>
                        <span id="subtotal">Rp 0</span>
                    </div>
                    <div class="pos-summary-row" id="itemDiscRow" style="display:none; color:#cf222e;">
                        <span><i class="ti ti-discount-2" style="font-size:0.7rem"></i> Item Discounts</span>
                        <span>- <span id="itemDiscTotal">Rp 0</span></span>
                    </div>
                    <div class="pos-summary-row">
                        <span>
                            <label class="form-check form-check-inline mb-0" style="font-size:0.8rem; cursor:pointer;">
                                <input type="checkbox" class="form-check-input" id="taxToggle" checked style="cursor:pointer;">
                                <span class="form-check-label" style="font-size:0.8rem;">Tax ({{ $taxRate ?? 11 }}%)</span>
                            </label>
                        </span>
                        <span id="tax">Rp 0</span>
                    </div>
                    <div class="pos-discount-block">
                        <label>Discount</label>
                        <div class="pos-discount-input-group">
                            <input type="text" id="discountInput" value="0" placeholder="0" autocomplete="off">
                            <div class="pos-disc-toggle">
                                <button type="button" class="disc-type active" data-type="percent">%</button>
                                <button type="button" class="disc-type" data-type="nominal">Rp</button>
                            </div>
                        </div>
                        <div class="pos-discount-display">- <span id="discountDisplay">Rp 0</span></div>
                    </div>
                    <div class="pos-redeem-block" id="redeemPointsBlock" style="display:none;">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="mb-0">Redeem Points</label>
                            <small class="text-muted">Saldo: <span id="pointsBalanceLabel">0</span></small>
                        </div>
                        <div class="pos-discount-input-group">
                            <input type="text" id="redeemPointsInput" value="0" placeholder="0" inputmode="numeric" autocomplete="off">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnRedeemMax" style="border-radius:0.375rem; white-space:nowrap;">Max</button>
                        </div>
                        <div class="pos-discount-display text-success">- <span id="redeemDiscountDisplay">Rp 0</span></div>
                        <small class="text-muted d-block mt-1" id="redeemRateHint"></small>
                    </div>
                    <div class="pos-summary-row total-row">
                        <span>Total</span>
                        <span class="total-val" id="total">Rp 0</span>
                    </div>

                    <div class="pos-action-row">
                        <button type="button" class="btn btn-label-danger btn-sm" id="btnCancel">
                            <i class="ti ti-x me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="btnPayment">
                            <i class="ti ti-check me-1"></i>Payment
                        </button>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- Variant Modal (outside posWrapper to avoid overflow/z-index issues) -->
    <div class="modal fade" id="variantModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Variants</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="variantModalBody">
                    <div class="text-center py-4 text-muted" id="variantLoading">
                        <div class="spinner-border" role="status"></div>
                        <p class="mt-2 mb-0">Loading variants...</p>
                    </div>
                    <div id="variantList" style="display:none"></div>
                    <div class="text-center py-4 text-muted" id="variantEmpty" style="display:none">
                        No variants with price for this price list
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down modal-dialog-scrollable">
            <div class="modal-content pay-modal">
                <div class="pay-modal-header">
                    <div>
                        <div class="pay-grand-label">Grand Total</div>
                        <div class="pay-grand-total" id="payGrandTotal">0</div>
                    </div>
                    <button type="button" class="pay-close-btn" data-bs-dismiss="modal" aria-label="Tutup pembayaran">
                        <span class="d-none d-sm-inline">Keluar [ESC]</span>
                        <span class="d-sm-none"><i class="ti ti-x"></i></span>
                    </button>
                </div>
                <div class="pay-mobile-tabs" id="payMobileTabs" role="tablist" aria-label="Metode pembayaran">
                    <button type="button" class="pay-mobile-tab active" data-pay-panel="cash" role="tab" aria-selected="true">Cash</button>
                    <button type="button" class="pay-mobile-tab" data-pay-panel="other" role="tab" aria-selected="false">Lainnya</button>
                </div>
                <div class="pay-modal-body" id="payModalBody">
                    <!-- CASH column -->
                    <div class="pay-col-cash">
                        <div class="pay-col-title">CASH</div>
                        <input type="text" class="pay-cash-input" id="payCashInput" placeholder="0" autocomplete="off">
                        <div class="pay-denom-grid">
                            <button type="button" class="pay-denom" data-val="100000">100rb</button>
                            <button type="button" class="pay-denom" data-val="50000">50rb</button>
                            <button type="button" class="pay-denom" data-val="20000">20rb</button>
                            <button type="button" class="pay-denom" data-val="10000">10rb</button>
                            <button type="button" class="pay-denom" data-val="5000">5rb</button>
                            <button type="button" class="pay-denom" data-val="2000">2rb</button>
                            <button type="button" class="pay-denom" data-val="1000">1rb</button>
                            <button type="button" class="pay-denom" data-val="500">500</button>
                            <button type="button" class="pay-denom" data-val="100">100</button>
                        </div>
                        <div class="pay-denom-actions">
                            <button type="button" class="pay-denom pay-denom-clear" id="payCashClear">Clear</button>
                            <button type="button" class="pay-denom pay-denom-exact" id="payCashExact">Uang Pas</button>
                        </div>
                        <div class="pay-denom-pay-wrap">
                            <button type="button" class="pay-denom pay-denom-cash-pay" id="payCashPay">Cash</button>
                        </div>
                    </div>
                    <!-- OTHER PAYMENT column -->
                    <div class="pay-col-other">
                        <div class="pay-col-title">OTHER PAYMENT</div>
                        <div class="pay-other-list">
                            @if(!empty($xenditChannelGroups))
                            <div class="pay-channel-groups" id="payChannelGroups">
                                @foreach($xenditChannelGroups as $group)
                                <div class="pay-channel-group">
                                    <div class="pay-channel-group-title">
                                        <i class="ti {{ $group['group_icon'] ?? 'ti-wallet' }}"></i>
                                        {{ $group['title'] }}
                                    </div>
                                    <div class="pay-channel-grid">
                                    @foreach($group['channels'] as $channel)
                                    <button type="button"
                                        class="pay-channel-btn"
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
                            <button type="button" class="pay-other-btn mt-2"
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
                            <div class="text-muted small text-center py-3">No other payment methods available</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Xendit checkout inline (tanpa popup / tab baru) -->
    <div id="xenditCheckoutOverlay" class="xendit-checkout-overlay" aria-hidden="true">
        <div class="xendit-checkout-bar">
            <div>
                <span class="xendit-checkout-label">Pembayaran Xendit</span>
                <strong id="xenditCheckoutOrder">-</strong>
                <span class="text-success fw-bold ms-2" id="xenditCheckoutTotal"></span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small d-none d-md-inline" id="xenditCheckoutStatus">
                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>Menunggu pembayaran...
                </span>
                <button type="button" class="btn btn-sm btn-label-secondary" id="btnXenditClose">
                    <i class="ti ti-x me-1"></i>Tutup
                </button>
            </div>
        </div>
        <iframe id="xenditCheckoutFrame" class="xendit-checkout-frame" title="Pembayaran Xendit"></iframe>
        <div class="xendit-checkout-fallback" id="xenditCheckoutFallback">
            <i class="ti ti-credit-card text-primary mb-3" style="font-size:3rem"></i>
            <p class="mb-3 text-muted">Memuat halaman pembayaran...</p>
            <button type="button" class="btn btn-primary" id="btnXenditContinue">Lanjutkan ke Pembayaran</button>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(document).ready(function() {
                // ── Select2 ───────────────────────────────────────────
                $('#priceListSelect').select2({
                    dropdownParent: $('body'),
                    placeholder: 'Select Price List',
                    allowClear: false
                });
                $('#customerSelect').select2({
                    dropdownParent: $('body'),
                    placeholder: 'Walk-in Customer',
                    allowClear: true,
                    templateResult: function(option) {
                        if (!option.id) {
                            // optgroup or placeholder
                            return option.text;
                        }

                        var $option = $(option.element);
                        var role = $option.data('partner-role');
                        var label = $option.data('partner-label');
                        var code = $option.data('partner-code');
                        var shortLabel = $option.data('short-label') || option.text.trim();
                        var $wrap = $('<div class="pos-customer-option"></div>');
                        var $name = $('<span class="pos-customer-name"></span>').text(shortLabel);
                        if (role === 'reseller') {
                            $name.css('padding-left', '0.5rem');
                        }
                        $wrap.append($name);

                        if (role && label) {
                            var badgeClass = role === 'agent' ? 'agent' : (role === 'reseller' ? 'reseller' : 'partner-lead');
                            $wrap.append(
                                $('<span class="pos-customer-badge ' + badgeClass + '"></span>').text(label + (code ? ' · ' + code : ''))
                            );
                        }

                        return $wrap;
                    },
                    templateSelection: function(option) {
                        if (!option.id) {
                            return option.text;
                        }

                        var $option = $(option.element);
                        return $option.data('short-label') || option.text.trim();
                    }
                });

                var discountType = 'percent';
                var redeemValuePerPoint = {{ (int) ($redeemValuePerPoint ?? 0) }};

                function updateSelectedPartnerBadge() {
                    var $selected = $('#customerSelect option:selected');
                    var $badge = $('#selectedPartnerBadge');
                    var role = $selected.data('partner-role');
                    var label = $selected.data('partner-label');
                    var code = $selected.data('partner-code');

                    if (!role || !label) {
                        $badge.hide().removeClass('agent reseller partner-lead').text('');
                    } else {
                        var badgeClass = role === 'agent' ? 'agent' : (role === 'reseller' ? 'reseller' : 'partner-lead');
                        $badge
                            .removeClass('agent reseller partner-lead')
                            .addClass(badgeClass)
                            .text(label + (code ? ' · ' + code : ''))
                            .show();
                    }

                    updateMembershipPointsUi(true);
                }

                function getSelectedPointsBalance() {
                    var $selected = $('#customerSelect option:selected');
                    if (!$selected.val()) return 0;
                    return parseInt($selected.data('points-balance'), 10) || 0;
                }

                function updateMembershipPointsUi(recalcTotals) {
                    var $selected = $('#customerSelect option:selected');
                    var $pointsBadge = $('#selectedPointsBadge');
                    var hasCustomer = !!$selected.val();
                    var balance = getSelectedPointsBalance();

                    if (!hasCustomer) {
                        $pointsBadge.hide().text('');
                        $('#redeemPointsBlock').hide();
                        $('#redeemPointsInput').val(0);
                    } else {
                        $pointsBadge.html('<i class="ti ti-star"></i> ' + balance.toLocaleString('id-ID') + ' poin').show();

                        if (redeemValuePerPoint > 0) {
                            $('#redeemPointsBlock').show();
                            $('#pointsBalanceLabel').text(balance.toLocaleString('id-ID'));
                            $('#redeemRateHint').text('1 poin = Rp ' + redeemValuePerPoint.toLocaleString('id-ID'));
                        } else {
                            $('#redeemPointsBlock').hide();
                            $('#redeemPointsInput').val(0);
                        }
                    }

                    if (recalcTotals && typeof updateCartTotals === 'function') {
                        updateCartTotals();
                    }
                }

                function getRedeemPointsRequested() {
                    return parseInt(String($('#redeemPointsInput').val() || '0').replace(/\D/g, ''), 10) || 0;
                }

                function calcMaxRedeemPoints(payableBeforeRedeem) {
                    var balance = getSelectedPointsBalance();
                    if (redeemValuePerPoint <= 0 || balance <= 0 || payableBeforeRedeem <= 0) return 0;
                    var byPayable = Math.floor(payableBeforeRedeem / redeemValuePerPoint);
                    return Math.max(0, Math.min(balance, byPayable));
                }

                $('#customerSelect').on('change', updateSelectedPartnerBadge);
                // Initial badges (no cart totals yet)
                (function initCustomerBadges() {
                    var $selected = $('#customerSelect option:selected');
                    var $badge = $('#selectedPartnerBadge');
                    var role = $selected.data('partner-role');
                    var label = $selected.data('partner-label');
                    var code = $selected.data('partner-code');
                    if (!role || !label) {
                        $badge.hide().removeClass('agent reseller partner-lead').text('');
                    } else {
                        var badgeClass = role === 'agent' ? 'agent' : (role === 'reseller' ? 'reseller' : 'partner-lead');
                        $badge.removeClass('agent reseller partner-lead').addClass(badgeClass)
                            .text(label + (code ? ' · ' + code : '')).show();
                    }
                    updateMembershipPointsUi(false);
                })();

                $('#btnRedeemMax').on('click', function() {
                    var subtotalNet = 0;
                    $cartPaidItems().each(function() {
                        var $el = $(this);
                        var inp = $el.find('.quantity-input');
                        var unitPrice = parseFloat(inp.data('unit-price')) || 0;
                        var qty = parseInt(inp.val()) || 1;
                        var lineTotal = unitPrice * qty;
                        var itemDiscAmt = 0;
                        if ($el.hasClass('has-discount')) {
                            var dt = $el.find('.item-disc-type.active').data('type') || 'percent';
                            var dv = parseDiscValue($el.find('.item-disc-input').val(), dt);
                            itemDiscAmt = dt === 'percent' ? Math.round(lineTotal * dv / 100) : Math.min(Math.round(dv), lineTotal);
                        }
                        subtotalNet += (lineTotal - itemDiscAmt);
                    });
                    var taxRate = {{ $taxRate ?? 11 }};
                    var tax = $('#taxToggle').is(':checked') ? Math.round(subtotalNet * taxRate / 100) : 0;
                    var discVal = parseDiscValue($('#discountInput').val(), discountType);
                    var txnDiscAmt = discountType === 'percent'
                        ? Math.round(subtotalNet * discVal / 100)
                        : Math.min(Math.round(discVal), subtotalNet);
                    var payable = Math.max(0, subtotalNet + tax - txnDiscAmt);
                    $('#redeemPointsInput').val(calcMaxRedeemPoints(payable));
                    updateCartTotals();
                });

                $('#redeemPointsInput').on('input', function() {
                    this.value = String(this.value || '').replace(/\D/g, '');
                    updateCartTotals();
                });

                // ── Discount toggle (% / Rp) ─────────────────────────────

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
                    var n = parseDiscValue(val, 'percent');
                    return n === 0 && String(val || '').trim() === '' ? '' : String(n);
                }

                function handleDiscInputInput(inputEl, type) {
                    if (type === 'nominal') {
                        var raw = inputEl.value.replace(/\D/g, '');
                        inputEl.value = raw === '' ? '' : parseInt(raw, 10).toLocaleString('id-ID');
                    } else {
                        var raw = inputEl.value.replace(/\D/g, '');
                        if (raw === '') {
                            inputEl.value = '';
                            return;
                        }
                        var n = Math.min(100, parseInt(raw, 10) || 0);
                        inputEl.value = String(n);
                    }
                }

                $('.pos-disc-toggle').on('click', '.disc-type', function() {
                    $('.disc-type').removeClass('active');
                    $(this).addClass('active');
                    discountType = $(this).data('type');
                    var $inp = $('#discountInput');
                    $inp.val(formatDiscInputDisplay($inp.val(), discountType));
                    updateCartTotals();
                });
                $('#discountInput').on('input', function() {
                    handleDiscInputInput(this, discountType);
                    updateCartTotals();
                });
                $('#taxToggle').on('change', function() { updateCartTotals(); });

                // ── Mobile: tab Produk / Keranjang ─────────────────────
                function isPosMobile() {
                    return window.matchMedia('(max-width: 767.98px)').matches;
                }
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
                $('#posMobileTabs').on('click', '.pos-mobile-tab', function() {
                    setPosMobileView($(this).data('pos-view'));
                });
                $(window).on('resize', function() {
                    if (!isPosMobile()) {
                        $('#posMain').removeClass('pos-mobile-view-cart');
                    }
                    if (!window.matchMedia('(max-width: 767.98px)').matches) {
                        $('#payModalBody').removeClass('pay-mobile-view-other');
                    }
                });

                // ── Payment modal: tab Cash / Lainnya (mobile) ─────────
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
                $('#payMobileTabs').on('click', '.pay-mobile-tab', function() {
                    setPayMobilePanel($(this).data('pay-panel'));
                });

                // ── Live clock ────────────────────────────────────────
                setInterval(function() {
                    var d = new Date();
                    $('#posClock').text(d.toLocaleTimeString('en-GB', {hour:'2-digit',minute:'2-digit'}));
                }, 30000);

                // ── Product type pills ─────────────────────────────────
                $('#productTypeTabs').on('click', '.pos-category-pill', function() {
                    $('#productTypeTabs .pos-category-pill').removeClass('active');
                    $(this).addClass('active');
                    var typeId = $(this).data('product-type');
                    if (typeId === 'all') {
                        $('.pos-product-card').show();
                    } else {
                        $('.pos-product-card').hide();
                        $('.pos-product-card[data-product-type-id="' + typeId + '"]').show();
                    }
                });

                // ── Product search ────────────────────────────────────
                $('#searchProduct').on('keyup', function() {
                    var v = $(this).val().toLowerCase();
                    $('.pos-product-card').each(function() {
                        var name = $(this).find('.p-name').text().toLowerCase();
                        $(this).toggle(name.indexOf(v) > -1);
                    });
                });

                // ── Price list change ─────────────────────────────────
                $('#priceListSelect').on('change select2:select', function() {
                    $('#priceListWrapper').attr('data-selected-id', $(this).val() || '');
                }).trigger('change');

                // ── Add to Cart (product card click) ──────────────────
                $(document).on('click', '.pos-product-card', function() {
                    var priceListId = $('#priceListWrapper').attr('data-selected-id') || $('#priceListSelect').val() || '';
                    if (!priceListId) {
                        alert('Please select Type Transaction (Price List) first');
                        return;
                    }
                    var productId = $(this).data('product-id');
                    var productImage = $(this).find('.p-img').attr('src');

                    $.get('{{ route("transaction.pos.product-variants") }}', {
                        product_id: productId,
                        price_list_id: priceListId
                    }).done(function(res) {
                        var variants = res.variants || [];
                        var withPrice = variants.filter(function(v) { return v.selling_price > 0; });
                        if (withPrice.length === 0) { alert('No variants with price for this price list'); return; }
                        if (withPrice.length === 1) {
                            var v = withPrice[0];
                            addToCart(v.id, v.display_name, v.selling_price, v.image || productImage, v.unit_id, v.unit_label);
                            return;
                        }
                        showVariantModal(variants, productImage);
                    }).fail(function() { alert('Failed to load variants'); });
                });

                // ── Variant modal (single instance) ──────────────────
                var variantModalEl = document.getElementById('variantModal');
                var variantModal = new bootstrap.Modal(variantModalEl);

                function showVariantModal(variants, productImage) {
                    $('#variantLoading').hide();
                    $('#variantList').hide();
                    $('#variantEmpty').hide();
                    var list = variants.filter(function(v) { return v.selling_price > 0; });
                    if (list.length === 0) {
                        $('#variantEmpty').show();
                    } else {
                        var html = '<div class="variant-grid">';
                        list.forEach(function(v) {
                            var img = v.image || productImage || 'https://placehold.co/300x225/f8f9fa/b0b7c3?text=?';
                            var stockClass = v.stock > 10 ? 'stock-ok' : (v.stock > 0 ? 'stock-low' : 'stock-out');
                            html += '<div class="variant-card variant-item" role="button" tabindex="0"';
                            html += ' data-variant-id="'+v.id+'" data-name="'+escapeHtml(v.display_name)+'" data-price="'+v.selling_price+'"';
                            html += ' data-image="'+img.replace(/"/g, '&quot;')+'" data-unit-id="'+(v.unit_id||'')+'" data-unit-label="'+(v.unit_label||'')+'">';
                            html += '<img src="'+img+'" alt="'+escapeHtml(v.display_name)+'" class="v-img" onerror="this.src=\'https://placehold.co/300x225/f8f9fa/b0b7c3?text=?\'">';
                            html += '<div class="v-body">';
                            html += '<div class="v-name">'+escapeHtml(v.display_name)+'</div>';
                            html += '<div class="v-price">Rp '+Number(v.selling_price).toLocaleString('id-ID')+'</div>';
                            html += '<span class="v-stock '+stockClass+'">Stok: '+v.stock+'</span>';
                            html += '</div></div>';
                        });
                        html += '</div>';
                        $('#variantList').html(html).show();
                    }
                    variantModal.show();
                }

                $(document).on('click', '.variant-item', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    addToCart($(this).data('variant-id'), $(this).data('name'), $(this).data('price'), $(this).data('image'), $(this).data('unit-id'), $(this).data('unit-label'));
                    variantModal.hide();
                });

                function escapeHtml(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

                // (Payment method selection moved to Payment Modal)

                // ── Cart quantity ─────────────────────────────────────
                $(document).on('click', '.btn-minus', function() {
                    if ($(this).closest('.pos-cart-item').hasClass('is-promo-free')) return;
                    var inp = $(this).siblings('.quantity-input');
                    var val = parseInt(inp.val());
                    if (val > 1) { inp.val(val - 1); updateCartTotals(); }
                });
                $(document).on('click', '.btn-plus', function() {
                    if ($(this).closest('.pos-cart-item').hasClass('is-promo-free')) return;
                    var inp = $(this).siblings('.quantity-input');
                    inp.val(parseInt(inp.val()) + 1);
                    updateCartTotals();
                });
                $(document).on('click', '.btn-delete', function() {
                    if ($(this).closest('.pos-cart-item').hasClass('is-promo-free')) return;
                    $(this).closest('.pos-cart-item').remove();
                    updateCartTotals();
                    checkEmptyCart();
                });

                // ── Item discount toggle ──────────────────────────────
                $(document).on('click', '.btn-item-disc', function() {
                    var item = $(this).closest('.pos-cart-item');
                    item.toggleClass('has-discount');
                    if (!item.hasClass('has-discount')) {
                        item.find('.item-disc-input').val('0');
                        updateCartTotals();
                    } else {
                        item.find('.item-disc-input').focus();
                    }
                });
                $(document).on('click', '.item-disc-type', function() {
                    var toggle = $(this).closest('.ci-disc-toggle');
                    toggle.find('.item-disc-type').removeClass('active');
                    $(this).addClass('active');
                    var item = $(this).closest('.pos-cart-item');
                    var discType = $(this).data('type');
                    var $inp = item.find('.item-disc-input');
                    $inp.val(formatDiscInputDisplay($inp.val(), discType));
                    updateCartTotals();
                });
                $(document).on('input', '.item-disc-input', function() {
                    var item = $(this).closest('.pos-cart-item');
                    var discType = item.find('.item-disc-type.active').data('type') || 'percent';
                    handleDiscInputInput(this, discType);
                    updateCartTotals();
                });

                // ── Cancel ────────────────────────────────────────────
                $('#btnCancel').on('click', function() {
                    Swal.fire({
                        title: 'Cancel Order?',
                        text: 'Are you sure you want to cancel this order?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, cancel',
                        cancelButtonText: 'No',
                        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
                        buttonsStyling: false
                    }).then(function(r) {
                        if (r.isConfirmed) {
                            clearCart();
                        }
                    });
                });

                /** Hapus item keranjang tanpa menghapus template #sampleCartItem */
                function clearCart() {
                    $('#cartItems .pos-cart-item').not('#sampleCartItem').remove();
                    $('#posTrxNumberWrap').hide();
                    $('#posTrxNumber').text('');
                    $('#discountInput').val('0');
                    discountType = 'percent';
                    $('.disc-type').removeClass('active');
                    $('.disc-type[data-type="percent"]').addClass('active');
                    updateCartTotals();
                    checkEmptyCart();
                }

                /** Reset UI POS setelah transaksi selesai (overlay, modal, keranjang) */
                function resetPosUiAfterPayment() {
                    closeXenditCheckout();
                    if (paymentModal) {
                        paymentModal.hide();
                    }
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                    $('#paymentModal .pay-denom-cash-pay, #paymentModal .pay-other-btn, #paymentModal .pay-channel-btn').prop('disabled', false);
                    $('#payCashPay').html('Cash');
                    $('#redeemPointsInput').val(0);
                    clearCart();
                }

                // ── Payment Modal ────────────────────────────────────
                var paymentModalEl = document.getElementById('paymentModal');
                var paymentModal = new bootstrap.Modal(paymentModalEl);
                var pendingPaymentData = null;

                function getCartTotal() {
                    return parseInt($('#total').text().replace(/\D/g, '')) || 0;
                }

                function formatRp(n) {
                    return 'Rp ' + Number(n).toLocaleString('id-ID');
                }

                // Open payment modal on Payment button click
                $('#btnPayment').on('click', function() {
                    var itemCount = getCartItemQty();
                    if (itemCount === 0) {
                        Swal.fire({ icon: 'warning', text: 'Please add items to your cart first!', customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
                        return;
                    }
                    var priceListId = $('#priceListWrapper').attr('data-selected-id') || $('#priceListSelect').val() || '';
                    if (!priceListId) {
                        Swal.fire({ icon: 'warning', text: 'Please select a Price List', customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
                        return;
                    }

                    var total = getCartTotal();
                    $('#payGrandTotal').text(formatRp(total));
                    $('#payCashInput').val('');
                    setPayMobilePanel('cash');
                    paymentModal.show();
                });

                // Denomination buttons
                $(document).on('click', '.pay-denom[data-val]', function() {
                    var val = parseInt($(this).data('val'));
                    var cur = parseInt($('#payCashInput').val().replace(/\D/g, '')) || 0;
                    var newVal = cur + val;
                    $('#payCashInput').val(newVal.toLocaleString('id-ID'));
                });

                // Clear cash input
                $('#payCashClear').on('click', function() { $('#payCashInput').val(''); });

                // Exact amount
                $('#payCashExact').on('click', function() {
                    var total = getCartTotal();
                    $('#payCashInput').val(total.toLocaleString('id-ID'));
                });

                // Format cash input on typing
                $('#payCashInput').on('input', function() {
                    var raw = this.value.replace(/\D/g, '');
                    if (raw === '') { this.value = ''; return; }
                    this.value = parseInt(raw).toLocaleString('id-ID');
                });

                // Cash pay button
                $('#payCashPay').on('click', function() {
                    var cashAmount = parseInt($('#payCashInput').val().replace(/\D/g, '')) || 0;
                    var total = getCartTotal();
                    if (cashAmount < total) {
                        Swal.fire({ icon: 'warning', text: 'Cash amount is less than total (Rp ' + total.toLocaleString('id-ID') + ')', customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
                        return;
                    }
                    var cashMethodId = null;
                    @foreach($methodPayments ?? collect() as $mp)
                        @if(strtoupper($mp->code) === 'CASH')
                            cashMethodId = '{{ $mp->id }}';
                        @endif
                    @endforeach
                    if (!cashMethodId) {
                        // Fallback: first method payment from server
                        @if(($methodPayments ?? collect())->isNotEmpty())
                            cashMethodId = '{{ ($methodPayments ?? collect())->first()->id }}';
                        @endif
                    }
                    processPayment(cashMethodId, 'Cash', cashAmount);
                });

                // Channel Xendit (Bank, QRIS, E-Wallet, dll.)
                $(document).on('click', '.pay-channel-btn', function() {
                    var methodId = $(this).data('payment-id');
                    var methodName = $(this).data('payment-name');
                    var xenditChannel = $(this).data('xendit-channel') || null;
                    var total = getCartTotal();
                    processPayment(methodId, methodName, total, true, xenditChannel);
                });

                // Metode non-Xendit lain (jika ada, selain Cash/COD)
                $(document).on('click', '.pay-other-btn', function() {
                    var methodId = $(this).data('payment-id');
                    var methodName = $(this).data('payment-name');
                    var total = getCartTotal();
                    processPayment(methodId, methodName, total, false, null);
                });

                // ESC to close payment modal
                $(paymentModalEl).on('keydown', function(e) {
                    if (e.key === 'Escape') paymentModal.hide();
                });

                /** Cart lines (jangan pakai :visible — panel keranjang disembunyikan di tab Produk mobile) */
                function $cartLineItems() {
                    return $('#cartItems .pos-cart-item').not('#sampleCartItem');
                }

                function $cartPaidItems() {
                    return $cartLineItems().not('.is-promo-free');
                }

                function getCartItemQty() {
                    var count = 0;
                    $cartPaidItems().each(function() {
                        count += parseInt($(this).find('.quantity-input').val(), 10) || 0;
                    });
                    return count;
                }

                function collectCartItems() {
                    var items = [];
                    $cartPaidItems().each(function() {
                        var $el = $(this);
                        var inp = $el.find('.quantity-input');
                        var unitPrice = parseFloat(inp.data('unit-price')) || 0;
                        var qty = parseInt(inp.val()) || 1;
                        var discType = 'percent', discVal = 0;
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
                            discount_value: discVal
                        });
                    });
                    return items;
                }

                var promoPreviewTimer = null;
                var promoPreviewXhr = null;

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

                    freeItems.forEach(function(row) {
                        var item = $('#sampleCartItem').clone().removeAttr('id').show();
                        item.addClass('is-promo-free');
                        item.attr('data-variant-id', row.variant_id);
                        item.attr('data-unit-id', row.unit_id || '');
                        item.attr('data-unit-label', row.unit_label || '');
                        item.attr('data-promotion-id', row.promotion_id || '');
                        item.find('.ci-img').attr('src', row.image || 'https://placehold.co/44x44/d8f3dc/146c2e?text=FREE');
                        item.find('.ci-name').html(
                            '<span class="ci-promo-badge"><i class="ti ti-gift"></i> FREE' +
                            (row.promo_code ? ' · ' + row.promo_code : '') +
                            '</span><span>' + $('<div>').text(row.name || 'Promo item').html() + '</span>'
                        );
                        item.find('.ci-price').text('Rp 0' + (row.unit_label ? ' / ' + row.unit_label : ''));
                        item.find('.ci-qty-unit').text(row.unit_label || '');
                        item.find('.quantity-input').val(row.quantity).data('unit-price', 0);
                        $('#cartItems').append(item);
                    });

                    var totalFreeQty = freeItems.reduce(function(sum, row) {
                        return sum + (parseFloat(row.quantity) || 0);
                    }, 0);
                    $('#promoHintText').text(
                        'Promo applied: ' + totalFreeQty + ' free item(s) will be fulfilled from configured warehouse.'
                    );
                    $('#promoHintRow').show();
                    checkEmptyCart();
                }

                function refreshPromoPreview() {
                    clearTimeout(promoPreviewTimer);
                    promoPreviewTimer = setTimeout(function() {
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
                            url: '{{ route("transaction.pos.preview-promo") }}',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                                'Accept': 'application/json'
                            },
                            contentType: 'application/json',
                            data: JSON.stringify({ items: items }),
                            success: function(res) {
                                if (res && res.success) {
                                    renderPromoFreeLines(res.free_items || []);
                                    updateCartBadgeOnly();
                                }
                            },
                            error: function(xhr) {
                                if (xhr.statusText === 'abort') return;
                                clearPromoFreeLines();
                                updateCartBadgeOnly();
                            }
                        });
                    }, 280);
                }

                function updateCartBadgeOnly() {
                    var paidQty = 0;
                    var freeQty = 0;
                    $cartPaidItems().each(function() {
                        paidQty += parseInt($(this).find('.quantity-input').val(), 10) || 0;
                    });
                    $cartLineItems().filter('.is-promo-free').each(function() {
                        freeQty += parseInt($(this).find('.quantity-input').val(), 10) || 0;
                    });
                    $('#cartItemCount, #cartItemCountBadge, #cartMobileTabBadge').text(paidQty + freeQty);
                }

                var xenditPollTimer = null;
                var xenditPendingOrderId = null;
                var xenditPendingMethodName = null;
                var xenditPendingInvoiceUrl = null;

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

                    // Jika iframe diblokir X-Frame-Options → redirect di tab yang sama (tanpa popup/tab baru)
                    setTimeout(function() {
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
                    html += '<tr><td class="text-start py-1" style="color:#9aa4b8">Payment</td><td class="text-end py-1 fw-bold">' + methodName + '</td></tr>';
                    if (d.amount_paid !== undefined) {
                        html += '<tr><td class="text-start py-1" style="color:#9aa4b8">Paid</td><td class="text-end py-1 fw-bold">' + formatRp(d.amount_paid) + '</td></tr>';
                    }
                    if (d.change_amount > 0) {
                        html += '<tr><td class="text-start py-1" style="color:#9aa4b8">Change</td><td class="text-end py-1 fw-bold text-success">' + formatRp(d.change_amount) + '</td></tr>';
                    }
                    if (d.promo_free_count > 0) {
                        html += '<tr><td class="text-start py-1" style="color:#9aa4b8">Promo FREE</td><td class="text-end py-1 fw-bold text-success">' + d.promo_free_count + ' item(s)</td></tr>';
                    }
                    if (d.membership_points_redeemed > 0) {
                        html += '<tr><td class="text-start py-1" style="color:#9aa4b8">Poin dipakai</td><td class="text-end py-1 fw-bold text-warning">-' + Number(d.membership_points_redeemed).toLocaleString('id-ID') + '</td></tr>';
                    }
                    if (d.membership_points_earned > 0) {
                        html += '<tr><td class="text-start py-1" style="color:#9aa4b8">Poin didapat</td><td class="text-end py-1 fw-bold text-success">+' + Number(d.membership_points_earned).toLocaleString('id-ID') + '</td></tr>';
                    }
                    html += '</table>';

                    // Sync local customer option balance after redeem/earn
                    var $opt = $('#customerSelect option:selected');
                    if ($opt.val()) {
                        var bal = parseInt($opt.data('points-balance'), 10) || 0;
                        bal = bal - (parseInt(d.membership_points_redeemed, 10) || 0) + (parseInt(d.membership_points_earned, 10) || 0);
                        if (bal < 0) bal = 0;
                        $opt.attr('data-points-balance', bal);
                    }

                    resetPosUiAfterPayment();
                    updateMembershipPointsUi();

                    if (d.sales_number) {
                        $('#posTrxNumber').text(d.sales_number);
                        $('#posTrxNumberWrap').show();
                    }

                    Swal.fire({
                        title: 'Transaction Complete!',
                        html: html,
                        icon: 'success',
                        confirmButtonText: 'OK',
                        customClass: { confirmButton: 'btn btn-success' },
                        buttonsStyling: false
                    });
                }

                function pollXenditPayment(orderId, methodName) {
                    clearXenditPoll();
                    var attempts = 0;
                    var maxAttempts = 200;

                    xenditPollTimer = setInterval(function() {
                        attempts++;
                        if (attempts > maxAttempts) {
                            clearXenditPoll();
                            return;
                        }

                        $.get('{{ url("transaction/pos/payment") }}/' + orderId + '/status', { sync: 1 }).done(function(res) {
                            if (res.success && res.data && res.data.is_paid) {
                                clearXenditPoll();
                                closeXenditCheckout();
                                showPaymentSuccess({
                                    sales_number: res.data.sales_number,
                                    total: res.data.total,
                                    change_amount: 0
                                }, methodName);
                            }
                        });
                    }, 3000);
                }

                function openXenditCheckout(res, methodName) {
                    paymentModal.hide();
                    showXenditCheckout(res.data, methodName);
                }

                $('#btnXenditClose').on('click', function() {
                    if (!xenditPendingOrderId) {
                        closeXenditCheckout();
                        return;
                    }
                    Swal.fire({
                        title: 'Tutup pembayaran?',
                        text: 'Order masih menunggu pembayaran. Anda bisa melanjutkan nanti dari riwayat transaksi.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, tutup',
                        cancelButtonText: 'Lanjut bayar',
                        customClass: {
                            confirmButton: 'btn btn-label-secondary me-2',
                            cancelButton: 'btn btn-primary'
                        },
                        buttonsStyling: false
                    }).then(function(r) {
                        if (r.isConfirmed) {
                            closeXenditCheckout();
                        }
                    });
                });

                $('#btnXenditContinue').on('click', function() {
                    if (xenditPendingInvoiceUrl) {
                        window.location.href = xenditPendingInvoiceUrl;
                    }
                });

                function processPayment(methodId, methodName, amountPaid, useXendit, xenditChannel) {
                    var priceListId = $('#priceListWrapper').attr('data-selected-id') || $('#priceListSelect').val() || '';
                    var items = collectCartItems();
                    var total = getCartTotal();

                    // Disable all payment buttons in modal
                    $('#paymentModal .pay-denom-cash-pay, #paymentModal .pay-other-btn, #paymentModal .pay-channel-btn').prop('disabled', true);
                    $('#payCashPay').html('<span class="spinner-border spinner-border-sm me-1"></span>Processing...');

                    var payload = {
                        price_list_id: priceListId,
                        items: items,
                        payment_method_id: methodId,
                        customer_id: $('#customerSelect').val() || null,
                        tax_rate: {{ $taxRate ?? 11 }},
                        tax_enabled: $('#taxToggle').is(':checked'),
                        discount_type: discountType,
                        discount_value: parseDiscValue($('#discountInput').val(), discountType),
                        redeem_points: getRedeemPointsRequested(),
                        amount_paid: amountPaid,
                        notes: null
                    };
                    if (useXendit && xenditChannel) {
                        payload.xendit_channel = xenditChannel;
                    }

                    $.ajax({
                        url: '{{ route("transaction.pos.payment") }}',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                            'Accept': 'application/json'
                        },
                        contentType: 'application/json',
                        data: JSON.stringify(payload),
                        success: function(res) {
                            if (res.success && res.xendit && res.data && res.data.invoice_url) {
                                openXenditCheckout(res, methodName);
                                return;
                            }

                            paymentModal.hide();
                            if (res.success) {
                                var d = res.data;
                                showPaymentSuccess(d, methodName);
                            } else {
                                Swal.fire({ title: 'Error', text: res.message || 'Unknown error', icon: 'error', customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
                            }
                        },
                        error: function(xhr) {
                            paymentModal.hide();
                            var msg = 'Payment failed';
                            if (xhr.responseJSON) {
                                if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
                                if (xhr.responseJSON.errors) {
                                    var errs = xhr.responseJSON.errors;
                                    var errList = [];
                                    for (var k in errs) { errList.push(errs[k].join(', ')); }
                                    msg += '<br>' + errList.join('<br>');
                                }
                            }
                            Swal.fire({ title: 'Error', html: msg, icon: 'error', customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
                        },
                        complete: function() {
                            $('#paymentModal .pay-denom-cash-pay, #paymentModal .pay-other-btn, #paymentModal .pay-channel-btn').prop('disabled', false);
                            $('#payCashPay').html('Cash');
                        }
                    });
                }

                // ── addToCart ──────────────────────────────────────────
                function addToCart(variantId, name, price, image, unitId, unitLabel) {
                    $('#emptyCart').hide();
                    var existing = $cartPaidItems().filter('[data-variant-id="'+variantId+'"]');
                    if (existing.length > 0) {
                        var inp = existing.find('.quantity-input');
                        inp.val(parseInt(inp.val()) + 1);
                    } else {
                        var item = $('#sampleCartItem').clone().removeAttr('id').show();
                        item.attr('data-variant-id', variantId);
                        item.attr('data-unit-id', unitId || '');
                        item.attr('data-unit-label', unitLabel || '');
                        item.find('.ci-img').attr('src', image || 'https://placehold.co/44x44/f8f9fa/b0b7c3?text=?');
                        item.find('.ci-name').text(name);
                        item.find('.ci-price').text(
                            'Rp ' + Number(price).toLocaleString('id-ID') + (unitLabel ? ' / ' + unitLabel : '')
                        );
                        item.find('.ci-qty-unit').text(unitLabel || '');
                        item.find('.quantity-input').data('unit-price', price);
                        $('#cartItems').append(item);
                    }
                    if (isPosMobile()) {
                        setPosMobileView('cart');
                    }
                    updateCartTotals();
                    checkEmptyCart();
                }

                // ── updateCartTotals ──────────────────────────────────
                function updateCartTotals() {
                    var subtotalGross = 0, subtotalNet = 0, totalItemDisc = 0, itemCount = 0;
                    $cartPaidItems().each(function() {
                        var $el = $(this);
                        var inp = $el.find('.quantity-input');
                        var unitPrice = parseFloat(inp.data('unit-price')) || (parseInt($el.find('.ci-price').text().replace(/\D/g,''))||0);
                        if (!inp.data('unit-price')) inp.data('unit-price', unitPrice);
                        var qty = parseInt(inp.val()) || 1;
                        var lineTotal = unitPrice * qty;
                        subtotalGross += lineTotal;
                        itemCount += qty;

                        var itemDiscAmt = 0;
                        if ($el.hasClass('has-discount')) {
                            var dt = $el.find('.item-disc-type.active').data('type') || 'percent';
                            var dv = parseDiscValue($el.find('.item-disc-input').val(), dt);
                            if (dt === 'percent') {
                                itemDiscAmt = Math.round(lineTotal * dv / 100);
                            } else {
                                itemDiscAmt = Math.round(dv);
                                if (itemDiscAmt > lineTotal) itemDiscAmt = lineTotal;
                            }
                            $el.find('.item-disc-display').text('Rp ' + itemDiscAmt.toLocaleString('id-ID'));
                        }
                        totalItemDisc += itemDiscAmt;
                        subtotalNet += (lineTotal - itemDiscAmt);
                    });

                    var taxRate = {{ $taxRate ?? 11 }};
                    var useTax = $('#taxToggle').is(':checked');
                    var tax = useTax ? Math.round(subtotalNet * taxRate / 100) : 0;

                    var discVal = parseDiscValue($('#discountInput').val(), discountType);
                    var txnDiscAmt = 0;
                    if (discountType === 'percent') {
                        txnDiscAmt = Math.round(subtotalNet * discVal / 100);
                    } else {
                        txnDiscAmt = Math.round(discVal);
                        if (txnDiscAmt > subtotalNet) txnDiscAmt = subtotalNet;
                    }

                    var totalBeforeRedeem = subtotalNet + tax - txnDiscAmt;
                    if (totalBeforeRedeem < 0) totalBeforeRedeem = 0;

                    var redeemPts = getRedeemPointsRequested();
                    var maxRedeem = calcMaxRedeemPoints(totalBeforeRedeem);
                    if (redeemPts > maxRedeem) {
                        redeemPts = maxRedeem;
                        $('#redeemPointsInput').val(redeemPts);
                    }
                    var redeemDiscAmt = redeemValuePerPoint > 0 ? redeemPts * redeemValuePerPoint : 0;
                    if (redeemDiscAmt > totalBeforeRedeem) redeemDiscAmt = totalBeforeRedeem;

                    var total = totalBeforeRedeem - redeemDiscAmt;
                    if (total < 0) total = 0;

                    $('#subtotal').text('Rp ' + subtotalNet.toLocaleString('id-ID'));
                    $('#itemDiscTotal').text('Rp ' + totalItemDisc.toLocaleString('id-ID'));
                    if (totalItemDisc > 0) { $('#itemDiscRow').show(); } else { $('#itemDiscRow').hide(); }
                    $('#tax').text('Rp ' + tax.toLocaleString('id-ID'));
                    $('#discountDisplay').text('Rp ' + txnDiscAmt.toLocaleString('id-ID'));
                    $('#redeemDiscountDisplay').text('Rp ' + redeemDiscAmt.toLocaleString('id-ID'));
                    $('#total').text('Rp ' + total.toLocaleString('id-ID'));

                    updateCartBadgeOnly();
                    refreshPromoPreview();
                }

                function checkEmptyCart() {
                    var hasItems = $cartLineItems().length > 0;
                    if (hasItems) {
                        $('#emptyCart').hide();
                    } else {
                        $('#emptyCart').show();
                        $('#promoHintRow').hide();
                    }
                }

                updateCartTotals();
                checkEmptyCart();

                // Kembali dari redirect Xendit (same-tab fallback)
                (function handleXenditReturn() {
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
                            buttonsStyling: false
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
                        $.get('{{ url("transaction/pos/payment") }}/' + orderId + '/status', { sync: 1 }).done(function(res) {
                            if (res.success && res.data && res.data.is_paid) {
                                showPaymentSuccess({
                                    sales_number: res.data.sales_number,
                                    total: res.data.total,
                                    change_amount: 0
                                }, methodName);
                            } else {
                                pollXenditPayment(orderId, methodName);
                            }
                        }).fail(function() {
                            pollXenditPayment(orderId, methodName);
                        });
                    }
                })();
            });
        </script>
    @endpush

</x-app-layout>
