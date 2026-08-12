<x-app-layout>
    @section('title', 'Receive Purchase Order | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

    @push('page-css')
        <style>
            .receive-row {
                border: 1px solid #e7e7e8;
                border-radius: 10px;
                padding: 1rem 1.25rem;
                margin-bottom: 0.85rem;
                background: #fff;
            }
            .receive-row.fully-received {
                opacity: 0.55;
                background: #f8f9fa;
            }
            .receive-row__head {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-start;
                justify-content: space-between;
                gap: 0.75rem 1.25rem;
                padding-bottom: 0.85rem;
                margin-bottom: 0.85rem;
                border-bottom: 1px solid #f0f0f1;
            }
            .receive-row__title {
                font-weight: 600;
                font-size: 0.95rem;
                margin: 0 0 0.2rem;
                line-height: 1.35;
            }
            .receive-row__meta {
                font-size: 0.8rem;
                color: #6f6b7d;
                display: flex;
                flex-wrap: wrap;
                gap: 0.15rem 0.75rem;
            }
            .receive-qty-chips {
                display: flex;
                flex-wrap: wrap;
                gap: 0.4rem;
                align-items: center;
            }
            .receive-qty-chips .chip {
                display: inline-flex;
                align-items: center;
                gap: 0.3rem;
                padding: 0.2rem 0.55rem;
                border-radius: 999px;
                font-size: 0.75rem;
                line-height: 1.3;
                background: #f1f1f2;
                color: #5d596c;
                white-space: nowrap;
            }
            .receive-qty-chips .chip strong { font-weight: 600; color: #2f2b3d; }
            .receive-qty-chips .chip-received { background: rgba(40, 199, 111, 0.12); color: #28c76f; }
            .receive-qty-chips .chip-received strong { color: #24b263; }
            .receive-qty-chips .chip-remain { background: rgba(255, 159, 67, 0.14); color: #ff9f43; }
            .receive-qty-chips .chip-remain.is-full { background: rgba(40, 199, 111, 0.12); color: #28c76f; }
            .receive-row__switch.form-check {
                display: inline-flex;
                align-items: center;
                gap: 0.55rem;
                margin: 0;
                padding: 0.4rem 0.8rem 0.4rem 0.65rem;
                min-height: 36px;
                border: 1px solid #e7e7e8;
                border-radius: 8px;
                background: #f8f9fa;
                padding-left: 0.65rem; /* override Bootstrap form-check padding */
            }
            .receive-row__switch .form-check-input {
                float: none;
                position: static;
                margin-top: 0;
                margin-left: 0;
                margin-right: 0;
                flex-shrink: 0;
                cursor: pointer;
            }
            .receive-row__switch.form-switch .form-check-input {
                margin-left: 0;
            }
            .receive-row__switch .form-check-label {
                font-size: 0.8rem;
                font-weight: 500;
                margin: 0;
                padding: 0;
                line-height: 1.2;
                cursor: pointer;
                white-space: nowrap;
            }
            .receive-row__fields .form-label {
                font-size: 0.78rem;
                margin-bottom: 0.3rem;
                color: #6f6b7d;
            }
            .receive-hint {
                font-size: 0.72rem;
                color: #a5a3ae;
                margin-top: 0.25rem;
                display: block;
            }
            .batch-fields.is-off {
                opacity: 0.45;
                pointer-events: none;
            }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => 'javascript:void(0);'],
                ['label' => 'Purchase Order', 'url' => route('product.purchase-order.index.view')],
                ['label' => $purchase->purchase_number, 'url' => route('product.purchase-order.detail.view', $purchase->id)],
                ['label' => 'Receive', 'active' => true]
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card mb-4">
            <div class="card-header py-3">
                <h5 class="mb-0">Receive Items - {{ $purchase->purchase_number }}</h5>
                <small class="text-muted">Supplier: {{ $purchase->supplier_name }} | Date: {{ $purchase->purchase_date?->format('d M Y') }}</small>
            </div>
            <form method="POST" action="{{ route('product.purchase-order.receive.data') }}" id="receiveForm">
                @csrf
                <input type="hidden" name="purchase_order_id" value="{{ $purchase->id }}" />

                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label" for="receive_date">Receive Date <span class="text-danger">*</span></label>
                            <input type="text" id="receive_date" name="receive_date" class="form-control flatpickr-date" placeholder="DD/MM/YYYY" value="{{ old('receive_date', date('d/m/Y')) }}" required />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="warehouse_id">Gudang Tujuan <span class="text-danger">*</span></label>
                            <select id="warehouse_id" name="warehouse_id" class="form-select" required>
                                @forelse ($warehouses as $warehouse)
                                    <option value="{{ $warehouse['id'] }}" @selected(old('warehouse_id', $defaultWarehouseId) === $warehouse['id'])>
                                        {{ $warehouse['label'] }}
                                    </option>
                                @empty
                                    <option value="">-- Tidak ada gudang --</option>
                                @endforelse
                            </select>
                            <small class="text-muted">Bahan baku (<code>RAW_MATERIAL</code>) otomatis ke <strong>Gudang WIP</strong>.</small>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="notes">Notes</label>
                            <input type="text" id="notes" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional receive notes..." />
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <h6 class="mb-0">Items to Receive</h6>
                    </div>
                    <div class="alert alert-info py-2 px-3 mb-3">
                        <i class="ti ti-info-circle me-1"></i>
                        Aktifkan <strong>Pakai Batch &amp; Expired</strong> untuk produk berbatch (mis. Foredi RM).
                        Matikan untuk Label, Plastik, atau Dus.
                    </div>

                    @foreach($purchase->items as $idx => $item)
                    @php
                        $ordered = (float) $item->quantity;
                        $received = (float) $item->quantity_received;
                        $remaining = (float) $item->quantity_remaining;
                        $fullyReceived = $remaining <= 0;
                        $useBatchDefault = old('items.'.$idx.'.use_batch', '1') == '1';
                        $variantAttrs = $item->variant
                            ? $item->variant->variantAttributes->map(fn ($va) => ($va->attributeDefinition->name ?? '').': '.($va->attributeValue->value ?? ''))->filter()->join(', ')
                            : '';
                    @endphp
                    <div class="receive-row {{ $fullyReceived ? 'fully-received' : '' }}" data-index="{{ $idx }}">
                        <input type="hidden" name="items[{{ $idx }}][purchase_order_item_id]" value="{{ $item->id }}" />

                        <div class="receive-row__head">
                            <div class="flex-grow-1" style="min-width: 200px;">
                                <p class="receive-row__title">
                                    {{ $item->product?->name ?? '-' }}
                                    @if($item->product?->code)
                                        <span class="text-muted fw-normal">({{ $item->product->code }})</span>
                                    @endif
                                </p>
                                <div class="receive-row__meta">
                                    @if($item->variant)
                                        <span>Variant: {{ $item->variant->sku }}@if($variantAttrs) ({{ $variantAttrs }})@endif</span>
                                    @endif
                                    <span>Unit: {{ $item->unit ? ($item->unit->symbol ?: $item->unit->name) : '-' }}</span>
                                </div>
                                <div class="receive-qty-chips mt-2">
                                    <span class="chip">Ordered <strong>{{ format_number($ordered, 2, true) }}</strong></span>
                                    <span class="chip chip-received">Received <strong>{{ format_number($received, 2, true) }}</strong></span>
                                    <span class="chip chip-remain {{ $fullyReceived ? 'is-full' : '' }}">Remaining <strong>{{ format_number($remaining, 2, true) }}</strong></span>
                                </div>
                            </div>

                            @if(! $fullyReceived)
                                <div class="form-check form-switch receive-row__switch mb-0">
                                    <input type="hidden" name="items[{{ $idx }}][use_batch]" value="0" />
                                    <input class="form-check-input item-use-batch" type="checkbox" role="switch"
                                           id="use_batch_{{ $idx }}" name="items[{{ $idx }}][use_batch]" value="1"
                                           {{ $useBatchDefault ? 'checked' : '' }}>
                                    <label class="form-check-label" for="use_batch_{{ $idx }}">Pakai Batch &amp; Expired</label>
                                </div>
                            @endif
                        </div>

                        <div class="row g-3 receive-row__fields align-items-start">
                            <div class="col-md-5 col-lg-4 batch-fields">
                                <label class="form-label">Kode Batch <span class="text-danger batch-required-mark">*</span></label>
                                @if($fullyReceived)
                                    <input type="text" class="form-control bg-light" value="{{ $item->batch_number ?: '-' }}" readonly disabled />
                                @else
                                    @php
                                        $defaultBatch = old(
                                            'items.'.$idx.'.batch_number',
                                            $item->batch_number ?: ($suggestedBatchNumber ?? ('BATCH-'.date('ymd').'01'))
                                        );
                                    @endphp
                                    <div class="input-group">
                                        <input type="text" name="items[{{ $idx }}][batch_number]" class="form-control item-batch" maxlength="100" value="{{ $defaultBatch }}" placeholder="BATCH-26071601" data-auto-batch="1" />
                                        <button type="button" class="btn btn-outline-secondary btn-gen-batch" title="Generate ulang kode batch" aria-label="Generate ulang kode batch">
                                            <i class="ti ti-refresh me-1"></i>Generate
                                        </button>
                                    </div>
                                    <span class="receive-hint">Format: BATCH-YYMMDD + no. unik</span>
                                @endif
                            </div>
                            <div class="col-md-2 col-lg-2 batch-fields">
                                <label class="form-label">Expired <span class="text-danger batch-required-mark">*</span></label>
                                @if($fullyReceived)
                                    <input type="text" class="form-control bg-light" value="{{ $item->expiry_date?->format('d/m/Y') ?: '-' }}" readonly disabled />
                                @else
                                    <input type="text" name="items[{{ $idx }}][expiry_date]" class="form-control flatpickr-date item-expiry" value="{{ old('items.'.$idx.'.expiry_date', $item->expiry_date?->format('d/m/Y')) }}" placeholder="dd/mm/yyyy" />
                                @endif
                            </div>
                            <div class="col-md-2 col-lg-2">
                                <label class="form-label">Qty Receive</label>
                                @if($fullyReceived)
                                    <input type="text" class="form-control bg-light" value="Full" readonly disabled />
                                    <input type="hidden" name="items[{{ $idx }}][quantity_received]" value="0" />
                                @else
                                    <input type="number" name="items[{{ $idx }}][quantity_received]" class="form-control item-receive-qty" value="{{ old('items.'.$idx.'.quantity_received', 0) }}" min="0" max="{{ $remaining }}" step="any" data-max="{{ $remaining }}" />
                                @endif
                            </div>
                            <div class="col-md-3 col-lg-4">
                                <label class="form-label">Notes</label>
                                <input type="text" name="items[{{ $idx }}][notes]" class="form-control" value="{{ old('items.'.$idx.'.notes') }}" placeholder="Optional..." {{ $fullyReceived ? 'disabled' : '' }} />
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </form>
        </div>
    </div>

    <div class="floating-footer d-flex justify-content-between align-items-center">
        <button type="button" class="btn btn-outline-info" id="btnReceiveAll"><i class="ti ti-checkbox me-1"></i>Receive All Remaining</button>
        <div>
            <a href="{{ route('product.purchase-order.detail.view', $purchase->id) }}" class="btn btn-outline-dark me-2">Cancel</a>
            <x-button color="success" id="btn-submit"><i class="ti ti-package me-1"></i>Save Receive</x-button>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/block-ui/block-ui.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-pickers.js') }}"></script>
        <script>
            $(document).ready(function() {
                $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y' });

                function parseReceiveDateParts(raw) {
                    raw = $.trim(raw || '');
                    var m = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                    if (m) {
                        return { y: m[3], m: String(m[2]).padStart(2, '0'), d: String(m[1]).padStart(2, '0') };
                    }
                    m = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                    if (m) {
                        return { y: m[1], m: m[2], d: m[3] };
                    }
                    var now = new Date();
                    return {
                        y: String(now.getFullYear()),
                        m: String(now.getMonth() + 1).padStart(2, '0'),
                        d: String(now.getDate()).padStart(2, '0')
                    };
                }

                function generateBatchCode(receiveDateRaw) {
                    var p = parseReceiveDateParts(receiveDateRaw);
                    var y = String(p.y).slice(-2);
                    return 'BATCH-' + y + p.m + p.d + '01';
                }

                function isAutoBatchPattern(val) {
                    return /^BATCH-\d{8}$/i.test($.trim(val || ''));
                }

                function fillBatchForRow($row, force) {
                    if (!$row.find('.item-use-batch').is(':checked')) return;
                    var $batch = $row.find('.item-batch');
                    if (!$batch.length) return;
                    var current = $.trim($batch.val() || '');
                    if (!force && current && !isAutoBatchPattern(current) && $batch.data('auto-batch') !== 1) {
                        return;
                    }
                    if (!force && current && !isAutoBatchPattern(current)) {
                        return;
                    }
                    $batch.val(generateBatchCode($('#receive_date').val()));
                    $batch.data('auto-batch', 1);
                }

                function syncBatchFields($row) {
                    var useBatch = $row.find('.item-use-batch').is(':checked');
                    var $batchFields = $row.find('.batch-fields');
                    $batchFields.toggleClass('is-off', !useBatch);
                    $row.find('.batch-required-mark').toggle(useBatch);
                    $row.find('.item-batch, .item-expiry, .btn-gen-batch').prop('disabled', !useBatch);
                    if (!useBatch) {
                        $row.find('.item-batch').val('').data('auto-batch', 0);
                        $row.find('.item-expiry').val('');
                    } else {
                        fillBatchForRow($row, false);
                    }
                }

                $('.receive-row').each(function() {
                    syncBatchFields($(this));
                });

                $(document).on('change', '.item-use-batch', function() {
                    syncBatchFields($(this).closest('.receive-row'));
                });

                $(document).on('input', '.item-batch', function() {
                    $(this).data('auto-batch', isAutoBatchPattern($(this).val()) ? 1 : 0);
                });

                $(document).on('click', '.btn-gen-batch', function() {
                    fillBatchForRow($(this).closest('.receive-row'), true);
                });

                $('#receive_date').on('change', function() {
                    $('.receive-row').each(function() {
                        var $row = $(this);
                        var $batch = $row.find('.item-batch');
                        if (!$batch.length) return;
                        if ($batch.data('auto-batch') === 1 || isAutoBatchPattern($batch.val()) || !$.trim($batch.val() || '')) {
                            fillBatchForRow($row, true);
                        }
                    });
                });

                $('.item-receive-qty').on('input change', function() {
                    var max = parseFloat($(this).data('max')) || 0;
                    var val = parseFloat($(this).val()) || 0;
                    if (val > max) $(this).val(max);
                    if (val < 0) $(this).val(0);
                });

                $('#btnReceiveAll').click(function() {
                    $('.item-receive-qty').each(function() {
                        $(this).val($(this).data('max'));
                    });
                });

                $('#btn-submit').click(function() {
                    if (!$('#warehouse_id').val()) {
                        alert('Pilih gudang tujuan penerimaan.');
                        return;
                    }
                    var hasQty = false;
                    var missingBatch = false;
                    $('.receive-row').each(function() {
                        var $row = $(this);
                        var $qty = $row.find('.item-receive-qty');
                        if (!$qty.length) return;
                        var qty = parseFloat($qty.val()) || 0;
                        if (qty <= 0) return;
                        hasQty = true;
                        if (!$row.find('.item-use-batch').is(':checked')) return;
                        fillBatchForRow($row, false);
                        var batch = $.trim($row.find('.item-batch').val() || '');
                        var expiry = $.trim($row.find('.item-expiry').val() || '');
                        if (!batch || !expiry) missingBatch = true;
                    });
                    if (!hasQty) {
                        alert('Minimal 1 item harus memiliki quantity > 0');
                        return;
                    }
                    if (missingBatch) {
                        alert('Kode Batch dan Expired wajib diisi untuk item yang mencentang Pakai Batch & Expired.');
                        return;
                    }
                    $('.item-batch, .item-expiry, .btn-gen-batch').prop('disabled', false);
                    $('#receiveForm').submit();
                });

                $('#receiveForm').submit(function() {
                    $(this).block({ message: '<div class="spinner-border text-primary"></div>', timeout: 1000, css: { backgroundColor: "transparent", border: 0 }, overlayCSS: { backgroundColor: "#fff", opacity: .8 } });
                });
            });
        </script>
    @endpush
</x-app-layout>
