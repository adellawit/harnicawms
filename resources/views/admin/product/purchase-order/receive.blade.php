<x-app-layout>
    @section('title', 'Receive Purchase Order | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

    @push('page-css')
        <style>
            .receive-row { border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 15px; background-color: #f8f9fa; }
            .receive-row.fully-received { opacity: 0.5; }
            .qty-info { font-size: 0.85rem; }
            .qty-info .badge { font-size: 0.8rem; }
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
            <div class="card-header">
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
                            <small class="text-muted">Bahan baku (<code>RAW_MATERIAL</code>) otomatis mengarah ke <strong>Gudang WIP</strong>.</small>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="notes">Notes</label>
                            <input type="text" id="notes" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional receive notes..." />
                        </div>
                    </div>

                    <h6 class="mb-3">Items to Receive</h6>

                    @foreach($purchase->items as $idx => $item)
                    @php
                        $ordered = (float) $item->quantity;
                        $received = (float) $item->quantity_received;
                        $remaining = (float) $item->quantity_remaining;
                        $fullyReceived = $remaining <= 0;
                    @endphp
                    <div class="receive-row {{ $fullyReceived ? 'fully-received' : '' }}">
                        <input type="hidden" name="items[{{ $idx }}][purchase_order_item_id]" value="{{ $item->id }}" />
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label fw-bold mb-1">
                                    {{ $item->product?->name ?? '-' }}
                                    @if($item->product?->code) <small class="text-muted">({{ $item->product->code }})</small> @endif
                                </label>
                                @if($item->variant)
                                    <div class="text-muted" style="font-size: 0.85rem;">
                                        Variant: {{ $item->variant->sku }}
                                        @php
                                            $variantAttrs = $item->variant->variantAttributes->map(fn($va) => ($va->attributeDefinition->name ?? '') . ': ' . ($va->attributeValue->value ?? ''))->join(', ');
                                        @endphp
                                        @if($variantAttrs) ({{ $variantAttrs }}) @endif
                                    </div>
                                @endif
                                <div class="text-muted" style="font-size: 0.85rem;">
                                    Unit: {{ $item->unit ? ($item->unit->symbol ?: $item->unit->name) : '-' }}
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="qty-info">
                                    <div>Ordered: <strong>{{ format_number($ordered, 2, true) }}</strong></div>
                                    <div>Received: <span class="text-success fw-bold">{{ format_number($received, 2, true) }}</span></div>
                                    <div>Remaining: <span class="badge bg-label-{{ $fullyReceived ? 'success' : 'warning' }}">{{ format_number($remaining, 2, true) }}</span></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Qty to Receive</label>
                                @if($fullyReceived)
                                    <input type="text" class="form-control bg-light" value="Fully Received" readonly disabled />
                                    <input type="hidden" name="items[{{ $idx }}][quantity_received]" value="0" />
                                @else
                                    <input type="number" name="items[{{ $idx }}][quantity_received]" class="form-control item-receive-qty" value="{{ old('items.'.$idx.'.quantity_received', 0) }}" min="0" max="{{ $remaining }}" step="any" data-max="{{ $remaining }}" />
                                @endif
                            </div>
                            <div class="col-md-3">
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
                    $('.item-receive-qty').each(function() {
                        if (parseFloat($(this).val()) > 0) hasQty = true;
                    });
                    if (!hasQty) {
                        alert('Minimal 1 item harus memiliki quantity > 0');
                        return;
                    }
                    $('#receiveForm').submit();
                });

                $('#receiveForm').submit(function() {
                    $(this).block({ message: '<div class="spinner-border text-primary"></div>', timeout: 1000, css: { backgroundColor: "transparent", border: 0 }, overlayCSS: { backgroundColor: "#fff", opacity: .8 } });
                });
            });
        </script>
    @endpush
</x-app-layout>
