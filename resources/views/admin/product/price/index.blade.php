<x-app-layout>
    @section('title', 'Product Price | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = session('permissions.Product Price.is_update', false) == 1;
            $filterPriceListId = request('price_list_id', '');
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
            $filterBranchId = request('branch_id', $defaultBranch);

            $isFilter = $filterPriceListId !== '' || $filterSku !== '' || $filterProductId !== '' || $filterNatureId !== '' || $filterCategoryId !== '' || $filterVariant !== '' || $filterPerPage != 20 || $filterBranchId !== $defaultBranch;
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Price', 'active' => true],
            ]"
        />

        <div id="alertArea"></div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="card-title mb-0">Product Price</h5>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0 text-nowrap">Price List:</label>
                        <select id="selectPriceList" class="form-select form-select-sm" style="width: auto; min-width: 180px;">
                            <option value="">Base (No Price List)</option>
                            @foreach($priceLists ?? [] as $pl)
                            <option value="{{ $pl->id }}" {{ $filterPriceListId == $pl->id ? 'selected' : '' }}>{{ $pl->name }} ({{ $pl->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    @if($hasUpdatePermission)
                        <button type="button" class="btn btn-success btn-sm btn-save-all">
                            <i class="ti ti-device-floppy me-1"></i> Save All
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
                            <th class="text-end" style="width:200px">Purchase Price / Unit</th>
                            <th class="text-end" style="width:200px">Selling Price / Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $rowNum = 0; @endphp

                        @forelse($products as $item)
                            @php
                                $isParentRow = $item['is_first_variant'] && $item['variant_count'] > 1;
                                $isChildRow = $item['variant_count'] > 1;
                                $isSingleRow = $item['variant_count'] == 1;
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
                                    <td class="text-center" colspan="2">
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
                                data-unit-id="{{ $item['unit_id'] }}">
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
                                    @if($hasUpdatePermission)
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                   class="form-control form-control-sm number-format price-input text-end"
                                                   data-field="purchase_price"
                                                   value="{{ $item['purchase_price'] > 0 ? format_number($item['purchase_price'], 2, false) : '' }}" />
                                            <span class="input-group-text">/ {{ $item['unit'] }}</span>
                                        </div>
                                    @else
                                        {{ $item['purchase_price'] > 0 ? format_number($item['purchase_price'], 2, true) : '-' }}
                                        <small class="text-muted">/ {{ $item['unit'] }}</small>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($hasUpdatePermission)
                                        <div class="input-group input-group-sm">
                                            <input type="text"
                                                   class="form-control form-control-sm number-format price-input text-end"
                                                   data-field="selling_price"
                                                   value="{{ $item['selling_price'] > 0 ? format_number($item['selling_price'], 2, false) : '' }}" />
                                            <span class="input-group-text">/ {{ $item['unit'] }}</span>
                                        </div>
                                    @else
                                        {{ $item['selling_price'] > 0 ? format_number($item['selling_price'], 2, true) : '-' }}
                                        <small class="text-muted">/ {{ $item['unit'] }}</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No products found.</td>
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
    </div>

    <x-modal id="filterModal" title="Filter">
        <div class="mb-3">
            <label class="form-label">Branch</label>
            <select id="selectBranch" class="select2-modal form-select" data-allow-clear="true">
                <option value="">All Branch</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @if($filterBranchId === $b->id) selected @endif>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Price List</label>
            <select id="selectPriceListFilter" class="select2-modal form-select" data-allow-clear="true">
                <option value="">Base (No Price List)</option>
                @foreach($priceLists ?? [] as $pl)
                <option value="{{ $pl->id }}" {{ $filterPriceListId == $pl->id ? 'selected' : '' }}>{{ $pl->name }} ({{ $pl->code }})</option>
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

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(document).ready(function() {
                $('#selectPriceList').on('change', function() {
                    var priceListId = $(this).val();
                    var url = '{{ route("product.price.index.view") }}';
                    var params = new URLSearchParams(window.location.search);
                    if (priceListId) params.set('price_list_id', priceListId);
                    else params.delete('price_list_id');
                    var qs = params.toString();
                    window.location = url + (qs ? '?' + qs : '');
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
                    var params = [];
                    var branchId = $('#selectBranch').val();
                    var priceListId = $('#selectPriceListFilter').val();
                    var sku = $('#selectSku').val();
                    var productId = $('#selectProduct').val();
                    var natureId = $('#selectNature').val();
                    var categoryId = $('#selectCategory').val();
                    var variantSearch = $('#variantSearch').val();
                    var perPage = $('#selectPerPage').val();

                    if (branchId) params.push('branch_id=' + encodeURIComponent(branchId));
                    if (priceListId) params.push('price_list_id=' + encodeURIComponent(priceListId));
                    if (sku) params.push('sku=' + encodeURIComponent(sku));
                    if (productId) params.push('product_id=' + encodeURIComponent(productId));
                    if (natureId) params.push('nature_id=' + encodeURIComponent(natureId));
                    if (categoryId) params.push('category_id=' + encodeURIComponent(categoryId));
                    if (variantSearch) params.push('variant_search=' + encodeURIComponent(variantSearch));
                    if (perPage && perPage != 20) params.push('per_page=' + encodeURIComponent(perPage));

                    var url = '{{ route("product.price.index.view") }}';
                    if (params.length > 0) url += '?' + params.join('&');
                    window.location = url;
                });

                $('#btnResetFilter').on('click', function() {
                    window.location = '{{ route("product.price.index.view") }}';
                });

                function showAlert(type, msg) {
                    var html = '<div class="alert alert-'+type+' alert-dismissible">'+msg+'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    $('#alertArea').html(html);
                    setTimeout(function() { $('#alertArea .alert').alert('close'); }, 4000);
                }

                function unformatPrice(val) {
                    if (!val || String(val).trim() === '') return '';
                    return String(val).replace(/\./g, '').replace(',', '.');
                }

                $('.btn-save-all').on('click', function() {
                    var items = [];

                    $('#table tbody tr:not(.parent-row)').each(function() {
                        var $row = $(this);
                        var variantId = $row.data('variant-id');
                        var productId = $row.data('product-id');
                        var unitId = $row.data('unit-id');
                        if (!variantId || !unitId) return;

                        var $ppInput = $row.find('[data-field="purchase_price"]');
                        var $spInput = $row.find('[data-field="selling_price"]');
                        if ($ppInput.length === 0) return;

                        var ppRaw = $ppInput.val() ? unformatPrice(String($ppInput.val()).trim()) : '';
                        var spRaw = $spInput.val() ? unformatPrice(String($spInput.val()).trim()) : '';

                        if (ppRaw !== '' || spRaw !== '') {
                            items.push({
                                variant_id: variantId,
                                product_id: productId,
                                unit_id: unitId,
                                purchase_price: ppRaw || '0',
                                selling_price: spRaw || ''
                            });
                        }
                    });

                    if (items.length === 0) {
                        showAlert('warning', 'No prices to save. Please fill in at least one purchase or selling price.');
                        return;
                    }

                    var $btn = $('.btn-save-all');
                    $btn.prop('disabled', true).html('<i class="ti ti-loader ti-spin me-1"></i> Saving...');

                    var priceListId = $('#selectPriceList').val();
                    var postData = {
                        _token: "{{ csrf_token() }}",
                        items: items
                    };
                    if (priceListId && priceListId !== '') {
                        postData.price_list_id = priceListId;
                    }

                    $.ajax({
                        url: "{{ route('product.price.save.data') }}",
                        type: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        data: postData,
                        success: function(res) {
                            if (res.success) {
                                showAlert('success', res.message);
                            } else {
                                showAlert('danger', res.message || 'Failed to save.');
                            }
                        },
                        error: function(xhr) {
                            var msg = 'Failed to save.';
                            if (xhr.responseJSON) {
                                if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
                                else if (xhr.responseJSON.errors) {
                                    var errs = xhr.responseJSON.errors;
                                    msg = Object.values(errs).flat().join(' ');
                                }
                            }
                            showAlert('danger', msg);
                        },
                        complete: function() {
                            $btn.prop('disabled', false).html('<i class="ti ti-device-floppy me-1"></i> Save All');
                        }
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
