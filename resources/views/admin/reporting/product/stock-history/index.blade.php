<x-app-layout>
    @section('title', 'Stock Position | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
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

            $isFilter = $filterSku !== '' || $filterProductId !== '' || $filterNatureId !== '' || $filterCategoryId !== '' || $filterVariant !== '' || $filterPerPage != 20 || $filterBranchId !== $defaultBranch;
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Reporting'],
                ['label' => 'Stock Position', 'active' => true]
            ]"
        />

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Date Selection</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('reporting.stock-history.index') }}" id="dateForm">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="text" name="date" class="form-control flatpickr-date" placeholder="Select date" value="{{ format_date_id($selectedDate) }}" required />
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="ti ti-search me-1"></i>Show</button>
                            <a href="{{ route('reporting.stock-history.index') }}" class="btn btn-label-dark"><i class="ti ti-x me-1"></i>Reset</a>
                        </div>
                    </div>
                    @if($filterSku)<input type="hidden" name="sku" value="{{ $filterSku }}">@endif
                    @if($filterProductId)<input type="hidden" name="product_id" value="{{ $filterProductId }}">@endif
                    @if($filterNatureId)<input type="hidden" name="nature_id" value="{{ $filterNatureId }}">@endif
                    @if($filterCategoryId)<input type="hidden" name="category_id" value="{{ $filterCategoryId }}">@endif
                    @if($filterVariant)<input type="hidden" name="variant_search" value="{{ $filterVariant }}">@endif
                    @if($filterPerPage != 20)<input type="hidden" name="per_page" value="{{ $filterPerPage }}">@endif
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    Stock Position per {{ \Carbon\Carbon::parse($selectedDate)->format('d/m/Y') }}
                </h5>
                <div class="d-flex align-items-center gap-2">
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
                            <th>Nature</th>
                            <th>Category</th>
                            <th class="text-end" style="width:180px">Stock Position</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $rowNum = 0; @endphp

                        @forelse($products as $item)
                            @php
                                $isParentRow = $item['is_first_variant'] && $item['variant_count'] > 1;
                                $isChildRow = $item['variant_count'] > 1;
                                $isSingleRow = $item['variant_count'] == 1;
                                $hasQty = $item['quantity'] !== null;
                                $displayQty = (float) ($item['quantity'] ?? 0);
                                $productQty = (float) ($item['product_quantity'] ?? $item['quantity'] ?? 0);
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
                                    <td class="text-end">
                                        @if($productQty != 0)
                                            <strong>{{ format_number($productQty, 2, true) }}</strong>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                        <small class="text-muted">{{ $item['unit'] }}</small>
                                    </td>
                                </tr>
                            @endif

                            @if($isSingleRow)
                                @php $rowNum++; @endphp
                            @endif

                            <tr class="{{ $isChildRow ? 'child-row' : '' }}"
                                @if($isChildRow) data-parent="{{ $item['product_id'] }}" @endif>
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
                                    @if($hasQty)
                                        @if($displayQty != 0)
                                            <strong>{{ format_number($displayQty, 2, true) }}</strong>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                        <small class="text-muted">{{ $item['unit'] }}</small>
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No stock data found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($paginator && $paginator->hasPages())
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
                <label class="form-label">Nature</label>
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
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(document).ready(function() {
                $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y', allowInput: true });

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
                    var date = $('[name="date"]').val();
                    var branchId = $('#selectBranch').val();
                    var sku = $('#selectSku').val();
                    var productId = $('#selectProduct').val();
                    var natureId = $('#selectNature').val();
                    var categoryId = $('#selectCategory').val();
                    var variantSearch = $('#variantSearch').val();
                    var perPage = $('#selectPerPage').val();

                    if (date) params.push('date=' + encodeURIComponent(date));
                    if (branchId) params.push('branch_id=' + encodeURIComponent(branchId));
                    if (sku) params.push('sku=' + encodeURIComponent(sku));
                    if (productId) params.push('product_id=' + encodeURIComponent(productId));
                    if (natureId) params.push('nature_id=' + encodeURIComponent(natureId));
                    if (categoryId) params.push('category_id=' + encodeURIComponent(categoryId));
                    if (variantSearch) params.push('variant_search=' + encodeURIComponent(variantSearch));
                    if (perPage && perPage != 20) params.push('per_page=' + encodeURIComponent(perPage));

                    var url = '{{ route("reporting.stock-history.index") }}';
                    if (params.length > 0) url += '?' + params.join('&');
                    window.location = url;
                });

                $('#btnResetFilter').on('click', function() {
                    var date = $('[name="date"]').val();
                    var url = '{{ route("reporting.stock-history.index") }}';
                    if (date) url += '?date=' + encodeURIComponent(date);
                    window.location = url;
                });
            });
        </script>
    @endpush
</x-app-layout>
