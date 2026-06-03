@php /** @var \App\Models\ProductVariant $variant */ @endphp
@php /** @var \App\Models\Product $product */ @endphp
<x-app-layout>
    @section('title', 'Manage Recipe - ' . $variant->display_name . ' | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Bill of Material', 'url' => route('product.bom.index.view')],
                ['label' => $variant->display_name, 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        {{-- Variant Info --}}
        <x-card title="Variant Info" class="mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <p class="mb-1 text-muted small">Product</p>
                        <p class="fw-semibold">{{ $product->name }}</p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1 text-muted small">Variant SKU</p>
                        <p class="fw-semibold">{{ $variant->sku ?? '-' }}</p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1 text-muted small">Variant</p>
                        <p class="fw-semibold">
                            @php
                                $variantAttrs = $variant->variantAttributes->map(function ($va) {
                                    $def = $va->attributeDefinition?->name ?? '';
                                    $val = $va->attributeValue?->value ?? '';
                                    return $def ? "$def: $val" : $val;
                                })->filter()->implode(', ');
                            @endphp
                            {{ $variantAttrs ?: 'Default' }}
                        </p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-1 text-muted small">Product Type</p>
                        <p class="fw-semibold">{{ $product->nature?->name ?? '-' }}</p>
                    </div>
                </div>
        </x-card>

        @php
            $hasUpdatePermission = session('permissions.Bill of Material.is_update', false) == 1;
        @endphp

        {{-- HPP Summary --}}
        @php
            $cardBigLabel = $product->defaultUnit ? ($product->defaultUnit->symbol ?: $product->defaultUnit->name) : '-';
            $cardConv = $product->unitConversions->first(fn ($c) => $c->from_unit_id === $product->default_unit_id);
            $cardSmallLabel = $cardConv && $cardConv->toUnit ? ($cardConv->toUnit->symbol ?: $cardConv->toUnit->name) : null;
            $cardFactor = $cardConv ? (float) $cardConv->conversion_factor : null;

            $hppExistingSmall = ($hppExisting !== null && $cardFactor && $cardFactor > 0) ? (float)$hppExisting / $cardFactor : null;
            $hppNewSmall = ($hppNew > 0 && $cardFactor && $cardFactor > 0) ? $hppNew / $cardFactor : null;
        @endphp
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-label-primary me-2"><i class="ti ti-receipt"></i></span>
                            <h6 class="mb-0">HPP Existing</h6>
                        </div>
                        @if($hppExisting !== null)
                            <h4 class="fw-bold mb-0">Rp {{ format_number($hppExisting, 2, true) }} <small class="text-muted fw-normal">/ {{ $cardBigLabel }}</small></h4>
                            @if($cardSmallLabel && $hppExistingSmall !== null)
                                <h6 class="fw-semibold text-muted mb-1">Rp {{ format_number($hppExistingSmall, 2, true) }} <small>/ {{ $cardSmallLabel }}</small></h6>
                            @endif
                        @else
                            <h4 class="fw-bold mb-1"><span class="text-muted fs-6">Belum diset</span></h4>
                        @endif
                        <small class="text-muted">Harga beli saat ini untuk {{ $variant->display_name }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-label-success me-2"><i class="ti ti-calculator"></i></span>
                            <h6 class="mb-0">HPP New (Kalkulasi BOM)</h6>
                        </div>
                        @if($bom->items->isNotEmpty())
                            <h4 class="fw-bold mb-0">Rp {{ format_number($hppNew, 2, true) }} <small class="text-muted fw-normal">/ {{ $cardBigLabel }}</small></h4>
                            @if($cardSmallLabel && $hppNewSmall !== null)
                                <h6 class="fw-semibold text-muted mb-1">Rp {{ format_number($hppNewSmall, 2, true) }} <small>/ {{ $cardSmallLabel }}</small></h6>
                            @endif
                        @else
                            <h4 class="fw-bold mb-1"><span class="text-muted fs-6">Belum ada item</span></h4>
                        @endif
                        <small class="text-muted">Total harga beli seluruh komponen resep</small>
                        @if($hppExisting !== null && $hppNew > 0)
                            @php $diff = $hppNew - $hppExisting; @endphp
                            <div class="mt-2">
                                @if(abs($diff) < 0.0001)
                                    <x-badge color="secondary">Sama dengan HPP Existing</x-badge>
                                @elseif($diff > 0)
                                    <x-badge color="danger">+ Rp {{ format_number($diff, 2, true) }} dari HPP Existing</x-badge>
                                @else
                                    <x-badge color="success">- Rp {{ format_number(abs($diff), 2, true) }} dari HPP Existing</x-badge>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Recipe Items --}}
        <div class="card">
            <h5 class="card-header fw-bold d-flex justify-content-between align-items-center">
                <span>Recipe Items</span>
                @if($hasUpdatePermission)
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="ti ti-plus me-1"></i>Add Item
                </button>
                @endif
            </h5>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Raw Material / Service</th>
                                <th class="text-end">Quantity</th>
                                <th>Unit</th>
                                <th class="text-end">Harga Beli / Unit</th>
                                <th class="text-end">Subtotal</th>
                                @if($hasUpdatePermission)<th style="width:120px">Action</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @php $totalHpp = 0; @endphp
                            @forelse($bom->items as $i => $item)
                            @php
                                $priceData = $itemPrices[$item->id] ?? null;
                                $unitPrice = $priceData['unit_price'] ?? null;
                                $subtotal = $priceData['subtotal'] ?? 0;
                                $totalHpp += $subtotal;
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $item->product?->name ?? '-' }}{{ $item->product?->code ? ' (' . $item->product->code . ')' : '' }}</td>
                                <td class="text-end">{{ format_number($item->quantity, 10, true) }}</td>
                                <td>{{ $item->unit?->symbol ?? $item->unit?->name ?? '-' }}</td>
                                <td class="text-end">
                                    @if($unitPrice !== null)
                                        Rp {{ format_number($unitPrice, 2, true) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($unitPrice !== null)
                                        Rp {{ format_number($subtotal, 2, true) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                @if($hasUpdatePermission)
                                <td>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-warning btn-edit-item"
                                        data-id="{{ $item->id }}"
                                        data-product-id="{{ $item->product_id }}"
                                        data-unit="{{ $item->unit_id }}"
                                        data-qty="{{ format_number($item->quantity, 10, true) }}">
                                        <i class="ti ti-edit"></i>
                                    </button>
                                    <form method="POST" action="{{ route('product.bom.delete-item') }}" class="d-inline" onsubmit="return confirm('Delete this item?');">
                                        @csrf
                                        <input type="hidden" name="item_id" value="{{ $item->id }}" />
                                        <input type="hidden" name="bill_of_material_id" value="{{ $bom->id }}" />
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger"><i class="ti ti-trash"></i></button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $hasUpdatePermission ? 7 : 6 }}" class="text-center text-muted">No recipe items yet. Click "Add Item" to add ingredients.</td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if($bom->items->isNotEmpty())
                        @php
                            $bigUnitLabel = $product->defaultUnit ? ($product->defaultUnit->symbol ?: $product->defaultUnit->name) : '-';
                            $parentConv = $product->unitConversions->first(fn ($c) => $c->from_unit_id === $product->default_unit_id);
                            $smallUnitLabel = $parentConv && $parentConv->toUnit ? ($parentConv->toUnit->symbol ?: $parentConv->toUnit->name) : null;
                            $parentConvFactor = $parentConv ? (float) $parentConv->conversion_factor : null;
                            $hppPerSmall = ($parentConvFactor && $parentConvFactor > 0) ? $totalHpp / $parentConvFactor : null;
                        @endphp
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="5" class="text-end fw-bold">Total HPP (BOM) / {{ $bigUnitLabel }}</th>
                                <th class="text-end fw-bold">Rp {{ format_number($totalHpp, 2, true) }}</th>
                                @if($hasUpdatePermission)<th></th>@endif
                            </tr>
                            @if($smallUnitLabel && $hppPerSmall !== null)
                            <tr>
                                <th colspan="5" class="text-end fw-bold">Total HPP (BOM) / {{ $smallUnitLabel }}</th>
                                <th class="text-end fw-bold">Rp {{ format_number($hppPerSmall, 2, true) }}</th>
                                @if($hasUpdatePermission)<th></th>@endif
                            </tr>
                            @endif
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($hasUpdatePermission)
    {{-- Add Item Modal --}}
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('product.bom.add-item') }}">
                    @csrf
                    <input type="hidden" name="bill_of_material_id" value="{{ $bom->id }}" />
                    <div class="modal-header">
                        <h5 class="modal-title">Add Recipe Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Raw Material <span class="text-danger">*</span></label>
                            <select name="product_id" id="add_item_product" class="select2 form-select product-unit-trigger" data-target="#add_item_unit" required>
                                <option value="">-- Select --</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}{{ $p->code ? ' (' . $p->code . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <select name="unit_id" id="add_item_unit" class="select2 form-select" required>
                                <option value="">-- Select Product first --</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Harga Beli / Unit</label>
                            <div id="add_item_price" class="text-muted">--</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="text" name="quantity" class="form-control number-format" required />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Item Modal --}}
    <div class="modal fade" id="editItemModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('product.bom.edit-item') }}">
                    @csrf
                    <input type="hidden" name="item_id" id="edit_item_id" />
                    <input type="hidden" name="bill_of_material_id" value="{{ $bom->id }}" />
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Recipe Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Raw Material <span class="text-danger">*</span></label>
                            <select name="product_id" id="edit_item_product" class="form-select product-unit-trigger" data-target="#edit_item_unit" required>
                                <option value="">-- Select --</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}{{ $p->code ? ' (' . $p->code . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <select name="unit_id" id="edit_item_unit" class="form-select" required>
                                <option value="">-- Select Product first --</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Harga Beli / Unit</label>
                            <div id="edit_item_price" class="text-muted">--</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="text" name="quantity" id="edit_item_qty" class="form-control number-format" required />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('product.bom.index.view') }}" class="btn btn-outline-dark">Back to List</a>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(function() {
                @if($hasUpdatePermission)
                var productUnitMap = @json($productUnitMap);
                var productPrices = @json($productPrices);
                var productConversions = @json($productConversions);
                var allUnits = @json($units->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'symbol' => $u->symbol]));

                function getConvertedPrice(productId, targetUnitId) {
                    var prices = productPrices[productId] || {};
                    if (prices[targetUnitId]) {
                        return prices[targetUnitId];
                    }
                    var convs = productConversions[productId] || [];
                    for (var i = 0; i < convs.length; i++) {
                        var c = convs[i];
                        if (prices[c.to] && c.from === targetUnitId) {
                            return prices[c.to] * c.factor;
                        }
                        if (prices[c.from] && c.to === targetUnitId) {
                            return prices[c.from] / c.factor;
                        }
                    }
                    return null;
                }

                function populateUnits($unitSelect, productId, preselect) {
                    $unitSelect.empty();
                    var unitIds = productUnitMap[productId] || [];
                    if (unitIds.length === 0) {
                        $unitSelect.append('<option value="">-- No units available --</option>');
                    } else {
                        $unitSelect.append('<option value="">-- Select Unit --</option>');
                        allUnits.forEach(function(u) {
                            if (unitIds.indexOf(u.id) !== -1) {
                                var label = u.name + (u.symbol ? ' (' + u.symbol + ')' : '');
                                var selected = preselect && preselect === u.id ? ' selected' : '';
                                $unitSelect.append('<option value="' + u.id + '"' + selected + '>' + label + '</option>');
                            }
                        });
                    }
                    if ($unitSelect.hasClass('select2-hidden-accessible')) {
                        $unitSelect.trigger('change.select2');
                    }
                }

                function formatCurrency(num) {
                    if (!num || num === 0) return '0,00';
                    let rounded = Math.round(num * 100) / 100;
                    let parts = rounded.toString().split('.');
                    let integerPart = parseInt(parts[0]).toLocaleString('en-US');
                    let decimalPart = parts[1] || '00';
                    return integerPart + ',' + decimalPart;
                }

                function initSelect2InModal($modal) {
                    $modal.find('.select2').each(function() {
                        var $el = $(this);
                        if ($el.hasClass('select2-hidden-accessible')) {
                            $el.select2('destroy');
                        }
                        if (!$el.parent().hasClass('position-relative')) {
                            $el.wrap('<div class="position-relative"></div>');
                        }
                        $el.select2({ placeholder: 'Select value', dropdownParent: $el.parent() });
                    });
                }

                $(document).on('change', '.product-unit-trigger', function() {
                    var productId = $(this).val();
                    var $unitSelect = $($(this).data('target'));
                    if (productId) {
                        populateUnits($unitSelect, productId);
                    } else {
                        $unitSelect.empty().append('<option value="">-- Select Product first --</option>');
                    }
                    if ($unitSelect.hasClass('select2-hidden-accessible')) {
                        $unitSelect.trigger('change.select2');
                    }
                    $('#add_item_price').text('--');
                });

                $('#add_item_unit').change(function() {
                    var productId = $('#add_item_product').val();
                    var unitId = $(this).val();
                    var priceEl = $('#add_item_price');
                    if (productId && unitId) {
                        var price = getConvertedPrice(productId, unitId);
                        if (price !== null) {
                            priceEl.text('Rp ' + formatCurrency(price));
                        } else {
                            priceEl.text('-');
                        }
                    } else {
                        priceEl.text('--');
                    }
                });

                $('#addItemModal').on('shown.bs.modal', function() {
                    initSelect2InModal($(this));
                    $('#add_item_product').val('').trigger('change');
                    $('#add_item_unit').empty().append('<option value="">-- Select Product first --</option>');
                    $('#add_item_price').text('--');
                    $('#add_item_unit').trigger('change.select2');
                });

                $('.btn-edit-item').click(function() {
                    var btn = $(this);
                    var productId = btn.data('product-id');
                    var unitId = btn.data('unit');
                    $('#edit_item_id').val(btn.data('id'));
                    $('#edit_item_product').val(productId);
                    populateUnits($('#edit_item_unit'), productId, unitId);
                    var qtyInput = $('#edit_item_qty');
                    qtyInput.val(btn.data('qty'));
                    qtyInput.trigger('input');
                    
                    if (productId && unitId) {
                        var price = getConvertedPrice(productId, unitId);
                        if (price !== null) {
                            $('#edit_item_price').text('Rp ' + formatCurrency(price));
                        } else {
                            $('#edit_item_price').text('-');
                        }
                    } else {
                        $('#edit_item_price').text('-');
                    }
                    
                    var modal = new bootstrap.Modal('#editItemModal');
                    modal.show();
                });
                
                $('#edit_item_unit').change(function() {
                    var productId = $('#edit_item_product').val();
                    var unitId = $(this).val();
                    var priceEl = $('#edit_item_price');
                    if (productId && unitId) {
                        var price = getConvertedPrice(productId, unitId);
                        if (price !== null) {
                            priceEl.text('Rp ' + formatCurrency(price));
                        } else {
                            priceEl.text('-');
                        }
                    } else {
                        priceEl.text('--');
                    }
                });
                $('#editItemModal').on('shown.bs.modal', function() {
                    initSelect2InModal($(this));
                });
                @endif
            });
        </script>
    @endpush
</x-app-layout>
