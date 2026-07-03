<x-app-layout>
    @section('title', 'Stock Adjustment | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = session('permissions.Stock Adjustment.is_update', false) == 1;
            $filterSku = request('sku', '');
            $filterProductId = request('product_id', '');
            $filterNatureId = request('nature_id', '');
            $filterCategoryId = request('category_id', '');
            $filterVariant = request('variant_search', '');
            $filterPerPage = request('per_page', 20);

            $branches = \App\Models\BusinessUnit::where('is_active', true)
                ->where('type_code', 'BRANCH')
                ->orderBy('name')
                ->get(['id', 'name']);
            $defaultBranch = auth('web')->user()->current_business_unit_id;
            $filterBranchId = request('branch_id', $filterBranchId ?? $defaultBranch);
            $filterWarehouseId = request('warehouse_id', $filterWarehouseId ?? '');
            $selectedWarehouseName = $selectedWarehouse?->name ?? null;
            $warehouseOptions = $warehouses ?? collect();

            $isFilter = $filterSku !== '' || $filterProductId !== '' || $filterNatureId !== '' || $filterCategoryId !== '' || $filterVariant !== '' || $filterPerPage != 20 || $filterBranchId !== $defaultBranch;
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Inventory'],
                ['label' => 'Stock Adjustment', 'active' => true],
            ]"
        />

        <div id="alertArea"></div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="card-title mb-0">Stock Adjustment</h5>
                    @unless($selectedWarehouseName)
                        <small class="text-warning">Select a warehouse to view and adjust stock</small>
                    @endunless
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <label for="selectWarehouse" class="form-label mb-0 text-nowrap small text-muted">Warehouse</label>
                        <select id="selectWarehouse" class="select2-warehouse form-select form-select-sm" data-allow-clear="true" style="min-width: 260px;">
                            <option value="">-- Select Warehouse --</option>
                            @foreach($warehouseOptions as $wh)
                                <option value="{{ $wh->id }}" @if($filterWarehouseId === $wh->id) selected @endif>
                                    {{ $wh->name }}
                                    @if($wh->warehouse_type_code) [{{ $wh->warehouse_type_code }}] @endif
                                    @if($wh->branch) - {{ $wh->branch->name }} @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @if($hasUpdatePermission)
                        <button type="button" class="btn btn-primary btn-sm btn-save">
                            <i class="ti ti-adjustments me-1"></i> Save Adjustment
                        </button>
                    @endif
                    <button type="button" class="btn btn-{{ $isFilter ? 'warning' : 'primary' }} btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                </div>
            </div>
            <div class="card-datatable text-nowrap table-responsive">
                <table class="table table-bordered table-hover" id="table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">No</th>
                            <th>SKU</th>
                            <th>Product / Variant</th>
                            <th>Product Type</th>
                            <th>Category</th>
                            <th class="text-end" style="width:170px">System Stock</th>
                            <th class="text-end" style="width:200px">Adjusted Stock</th>
                            <th class="text-end" style="width:150px">Difference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $rowNum = 0; @endphp

                        @forelse($products as $item)
                            @php
                                $isParentRow = $item['is_first_variant'] && $item['variant_count'] > 1;
                                $isChildRow = $item['variant_count'] > 1;
                                $isSingleRow = $item['variant_count'] == 1;
                                $displayQty = (float) $item['quantity'];
                            @endphp

                            @if($isParentRow)
                                @php $rowNum++; @endphp
                                <tr class="table-primary parent-row" data-product-id="{{ $item['product_id'] }}" style="cursor:pointer">
                                    <td>{{ $rowNum }}</td>
                                    <td><strong>{{ $item['sku'] ?: '-' }}</strong></td>
                                    <td>
                                        <i class="ti ti-chevron-down toggle-icon me-1"></i>
                                        <strong>{{ $item['product_name'] }}</strong>
                                    </td>
                                    <td>{{ $item['nature'] }}</td>
                                    <td>{{ $item['category'] }}</td>
                                    <td class="text-center" colspan="3">
                                        <small class="text-muted">{{ $item['variant_count'] }} variants</small>
                                    </td>
                                </tr>
                            @endif

                            @if($isSingleRow)
                                @php $rowNum++; @endphp
                            @endif

                            <tr class="{{ $isChildRow ? 'child-row' : '' }}"
                                @if($isChildRow) data-parent="{{ $item['product_id'] }}" @endif
                                data-variant-id="{{ $item['variant_id'] }}"
                                data-product-id="{{ $item['product_id'] }}"
                                data-unit-id="{{ $item['unit_id'] }}"
                                data-system-qty="{{ $displayQty }}">
                                <td>{{ $isSingleRow ? $rowNum : '' }}</td>
                                <td>{{ $item['sku'] ?: '-' }}</td>
                                <td>
                                    @if($isChildRow)
                                        <span class="ps-3 text-muted">
                                            <i class="ti ti-corner-down-right me-1" style="font-size:.75rem"></i>
                                            {{ $item['variant_name'] ?: $item['sku'] }}
                                        </span>
                                    @else
                                        {{ $item['product_name'] }}
                                    @endif
                                </td>
                                <td>{{ $isSingleRow ? $item['nature'] : '' }}</td>
                                <td>{{ $isSingleRow ? $item['category'] : '' }}</td>
                                <td class="text-end">
                                    {{ format_number($displayQty, 2, true) }}
                                    <small class="text-muted">{{ $item['unit'] }}</small>
                                </td>
                                <td class="text-end">
                                    @if($hasUpdatePermission)
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                   class="form-control form-control-sm number-format adj-input text-end"
                                                   data-field="physical_qty"
                                                   value="{{ format_number($displayQty, 2, false) }}" />
                                            <span class="input-group-text">{{ $item['unit'] }}</span>
                                        </div>
                                    @else
                                        {{ format_number($displayQty, 2, true) }}
                                        <small class="text-muted">{{ $item['unit'] }}</small>
                                    @endif
                                </td>
                                <td class="text-end diff-cell">
                                    <span class="diff-value"><span class="text-muted">0</span></span>
                                    <small class="text-muted">{{ $item['unit'] }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No stock items found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($paginator->hasPages())
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center">
                    <div class="text-muted small">
                        Showing {{ $paginator->firstItem() ?? 0 }} to {{ $paginator->lastItem() ?? 0 }}
                        of {{ $paginator->total() }} products
                    </div>
                    <div>
                        {{ $paginator->appends(request()->query())->links() }}
                    </div>
                </div>
            @endif
        </div>

        @if($hasUpdatePermission)
        <div class="card mt-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Adjustment Notes</label>
                        <input type="text" id="adjustmentNotes" class="form-control" placeholder="e.g. Stock correction for damaged goods" />
                    </div>
                </div>
            </div>
        </div>
        @endif

        <x-modal id="filterModal" title="Filter">
            <div class="mb-3">
                <label class="form-label">Branch (Filter Produk)</label>
                <select id="selectBranch" class="select2-modal form-select" data-allow-clear="true">
                    <option value="">All Branch</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @if($filterBranchId === $b->id) selected @endif>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Variant</label>
                <input type="text" id="variantSearch" class="form-control"
                       value="{{ $filterVariant }}"
                       placeholder="Search variant SKU...">
            </div>
            <div class="mb-3">
                <label class="form-label">SKU</label>
                <select id="selectSku" class="select2-modal form-select" data-allow-clear="true">
                    <option value="">All</option>
                    @foreach($allProducts->whereNotNull('sku')->unique('sku')->sortBy('sku') as $product)
                        <option value="{{ $product->sku }}" @if($filterSku == $product->sku) selected @endif>{{ $product->sku }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Product</label>
                <select id="selectProduct" class="select2-modal form-select" data-allow-clear="true">
                    <option value="">All</option>
                    @foreach($allProducts as $product)
                        <option value="{{ $product->id }}" @if($filterProductId == $product->id) selected @endif>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Product Type</label>
                <select id="selectNature" class="select2-modal form-select" data-allow-clear="true">
                    <option value="">All</option>
                    @foreach($allNatures as $nature)
                        <option value="{{ $nature->id }}" @if($filterNatureId == $nature->id) selected @endif>{{ $nature->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Category</label>
                <select id="selectCategory" class="select2-modal form-select" data-allow-clear="true">
                    <option value="">All</option>
                    @foreach($allCategories as $cat)
                        <option value="{{ $cat->id }}" @if($filterCategoryId == $cat->id) selected @endif>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-0">
                <label class="form-label">Per Page</label>
                <select id="selectPerPage" class="form-select">
                    @foreach([10, 20, 50, 100] as $pp)
                        <option value="{{ $pp }}" {{ $filterPerPage == $pp ? 'selected' : '' }}>{{ $pp }}</option>
                    @endforeach
                </select>
            </div>
            <x-slot name="footer">
                <x-button color="dark" variant="label" id="btnResetFilter">Reset</x-button>
                <x-button color="primary" id="btnFilter" data-bs-dismiss="modal">Filter</x-button>
            </x-slot>
        </x-modal>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(document).ready(function() {
                var listUrl = '{{ route("product.stock-adjustment.index") }}';

                $('#selectWarehouse').select2({
                    placeholder: '-- Select Warehouse --',
                    allowClear: true,
                    width: '100%'
                });

                function buildListUrl(options) {
                    options = options || {};
                    var params = [];
                    var warehouseId = options.warehouseId !== undefined
                        ? options.warehouseId
                        : $('#selectWarehouse').val();

                    if (warehouseId) {
                        params.push('warehouse_id=' + encodeURIComponent(warehouseId));
                    }

                    var branchId = options.branchId !== undefined ? options.branchId : $('#selectBranch').val();
                    var sku = options.sku !== undefined ? options.sku : $('#selectSku').val();
                    var productId = options.productId !== undefined ? options.productId : $('#selectProduct').val();
                    var natureId = options.natureId !== undefined ? options.natureId : $('#selectNature').val();
                    var categoryId = options.categoryId !== undefined ? options.categoryId : $('#selectCategory').val();
                    var variantSearch = options.variantSearch !== undefined ? options.variantSearch : $('#variantSearch').val();
                    var perPage = options.perPage !== undefined ? options.perPage : $('#selectPerPage').val();

                    if (branchId) params.push('branch_id=' + encodeURIComponent(branchId));
                    if (sku) params.push('sku=' + encodeURIComponent(sku));
                    if (productId) params.push('product_id=' + encodeURIComponent(productId));
                    if (natureId) params.push('nature_id=' + encodeURIComponent(natureId));
                    if (categoryId) params.push('category_id=' + encodeURIComponent(categoryId));
                    if (variantSearch) params.push('variant_search=' + encodeURIComponent(variantSearch));
                    if (perPage && perPage != 20) params.push('per_page=' + encodeURIComponent(perPage));

                    if (params.length > 0) {
                        return listUrl + '?' + params.join('&');
                    }

                    return listUrl;
                }

                $('#selectWarehouse').on('change', function() {
                    var urlParams = new URLSearchParams(window.location.search);
                    var url = buildListUrl({
                        warehouseId: $(this).val(),
                        branchId: urlParams.get('branch_id') || '',
                        sku: urlParams.get('sku') || '',
                        productId: urlParams.get('product_id') || '',
                        natureId: urlParams.get('nature_id') || '',
                        categoryId: urlParams.get('category_id') || '',
                        variantSearch: urlParams.get('variant_search') || '',
                        perPage: urlParams.get('per_page') || '20'
                    });
                    window.location = url;
                });

                $(document).on('click', '.parent-row', function() {
                    var productId = $(this).data('product-id');
                    var icon = $(this).find('.toggle-icon');
                    var childRows = $('tr[data-parent="' + productId + '"]');
                    if (childRows.is(':visible')) {
                        childRows.hide();
                        icon.removeClass('ti-chevron-down').addClass('ti-chevron-right');
                    } else {
                        childRows.show();
                        icon.removeClass('ti-chevron-right').addClass('ti-chevron-down');
                    }
                });

                $('#filterModal').on('shown.bs.modal', function() {
                    $('.select2-modal').each(function() {
                        if (!$(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2({
                                dropdownParent: $('#filterModal'),
                                placeholder: 'All',
                                allowClear: true,
                                width: '100%'
                            });
                        }
                    });
                });

                $('#btnFilter').on('click', function() {
                    window.location = buildListUrl();
                });

                $('#btnResetFilter').on('click', function() {
                    var warehouseId = $('#selectWarehouse').val();
                    window.location = warehouseId
                        ? listUrl + '?warehouse_id=' + encodeURIComponent(warehouseId)
                        : listUrl;
                });

                function parseDisplayNumber(str) {
                    if (!str || str.trim() === '') return null;
                    var cleaned = str.replace(/\./g, '').replace(/,/g, '.');
                    var num = parseFloat(cleaned);
                    return isNaN(num) ? null : num;
                }

                function formatDiff(val) {
                    if (val === 0) return '<span class="text-muted">0</span>';
                    var abs = Math.abs(val);
                    var formatted = abs.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 6 });
                    if (val > 0) return '<span class="text-success fw-bold">+' + formatted + '</span>';
                    return '<span class="text-danger fw-bold">-' + formatted + '</span>';
                }

                $(document).on('blur keyup', '.adj-input', function() {
                    var $row = $(this).closest('tr');
                    var systemQty = parseFloat($row.data('system-qty')) || 0;
                    var physicalQty = parseDisplayNumber($(this).val());
                    if (physicalQty === null) physicalQty = 0;
                    $row.find('.diff-value').html(formatDiff(physicalQty - systemQty));
                });

                $('.adj-input').trigger('blur');

                function showAlert(type, msg) {
                    var html = '<div class="alert alert-'+type+' alert-dismissible">'+msg+'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    $('#alertArea').html(html);
                    setTimeout(function() { $('#alertArea .alert').alert('close'); }, 4000);
                }

                $('.btn-save').on('click', function() {
                    var warehouseId = $('#selectWarehouse').val();
                    if (!warehouseId) {
                        showAlert('warning', 'Select a warehouse first.');
                        return;
                    }

                    var items = [];
                    $('#table tbody tr:not(.parent-row)').each(function() {
                        var $row = $(this);
                        var variantId = $row.data('variant-id');
                        var productId = $row.data('product-id');
                        var unitId = $row.data('unit-id');
                        if (!variantId) return;

                        var $input = $row.find('[data-field="physical_qty"]');
                        if (!$input.length) return;

                        var physicalQty = parseDisplayNumber($input.val());
                        var systemQty = parseFloat($row.data('system-qty')) || 0;

                        if (physicalQty !== null && Math.abs(physicalQty - systemQty) >= 0.000001) {
                            items.push({
                                variant_id: variantId,
                                product_id: productId,
                                unit_id: unitId,
                                physical_qty: physicalQty.toFixed(6).replace('.', ',')
                            });
                        }
                    });

                    if (items.length === 0) {
                        showAlert('info', 'No stock difference found. All quantities match.');
                        return;
                    }

                    if (!confirm('Save adjustment for ' + items.length + ' item(s)? This will update stock quantities.')) return;

                    var $btn = $('.btn-save');
                    $btn.prop('disabled', true).html('<i class="ti ti-loader ti-spin me-1"></i> Saving...');

                    $.ajax({
                        url: "{{ route('product.stock-adjustment.save') }}",
                        type: 'POST',
                        data: {
                            _token: "{{ csrf_token() }}",
                            warehouse_id: warehouseId,
                            items: items,
                            notes: $('#adjustmentNotes').val()
                        },
                        success: function(res) {
                            if (res.success) {
                                showAlert('success', res.message);
                                setTimeout(function() { window.location.reload(); }, 1500);
                            } else {
                                showAlert('danger', res.message || 'Failed to save.');
                            }
                        },
                        error: function(xhr) {
                            var msg = 'Failed to save.';
                            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                            showAlert('danger', msg);
                        },
                        complete: function() {
                            $btn.prop('disabled', false).html('<i class="ti ti-adjustments me-1"></i> Save Adjustment');
                        }
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
