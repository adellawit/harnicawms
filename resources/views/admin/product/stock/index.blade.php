<x-app-layout>
    @section('title', 'Product Stock | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $filterSku = request('sku', '');
            $filterProductId = request('product_id', '');
            $filterNatureId = request('nature_id', '');
            $filterCategoryId = request('category_id', '');
            $filterVariant = request('variant_search', '');
            $filterPerPage = request('per_page', 20);
            $displayUnitMode = $displayUnitMode ?? request('display_unit', 'large');

            $branches = $locations ?? collect();
            $filterWarehouseId = $filterWarehouseId ?? request('warehouse_id', request('branch_id', ''));
            $selectedWarehouseName = $selectedWarehouse?->name ?? null;

            $isFilter = $filterSku !== '' || $filterProductId !== '' || $filterNatureId !== '' || $filterCategoryId !== '' || $filterVariant !== '' || $filterPerPage != 20 || $filterWarehouseId !== '' || $displayUnitMode !== 'large';

            $unitToggleQuery = collect(request()->query())
                ->except('display_unit')
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->all();
            $largeUnitUrl = route('product.stock.index.view', array_merge($unitToggleQuery, ['display_unit' => 'large']));
            $smallUnitUrl = route('product.stock.index.view', array_merge($unitToggleQuery, ['display_unit' => 'small']));
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => 'javascript:void(0);'],
                ['label' => 'Inventory', 'url' => 'javascript:void(0);'],
                ['label' => 'Stock', 'active' => true]
            ]"
        />

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">Product Stock</h5>
                    @if($selectedWarehouseName)
                        <small class="text-muted">
                            Gudang: <strong>{{ $selectedWarehouse->code }} - {{ $selectedWarehouseName }}</strong>
                            @if($selectedWarehouse->warehouse_type_code === 'RAW_MATERIAL')
                                <span class="badge bg-label-info ms-1">Bahan Baku</span>
                            @elseif($selectedWarehouse->warehouse_type_code === 'FG')
                                <span class="badge bg-label-success ms-1">Barang Jadi</span>
                            @endif
                        </small>
                    @else
                        <small class="text-muted">Agregat semua gudang — gunakan Filter → Gudang untuk memisahkan Bahan Baku vs Barang Jadi</small>
                    @endif
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap stock-header-actions">
                    <div class="btn-group" role="group" aria-label="Tampilan satuan">
                        <a href="{{ $largeUnitUrl }}" class="btn btn-sm btn-{{ $displayUnitMode === 'large' ? 'primary' : 'outline-primary' }}">
                            Satuan Besar
                        </a>
                        <a href="{{ $smallUnitUrl }}" class="btn btn-sm btn-{{ $displayUnitMode === 'small' ? 'primary' : 'outline-primary' }}">
                            Satuan Kecil
                        </a>
                    </div>
                    <button type="button" class="btn btn-sm btn-{{ $isFilter ? 'warning' : 'primary' }}" data-bs-toggle="modal" data-bs-target="#filterModal">
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
                            <th class="text-end">Qty / Unit</th>
                            <th class="text-end">Min Stock</th>
                            <th class="text-end">HPP / Purchase</th>
                            <th class="text-end">Selling Price</th>
                            <th class="text-center" style="width:120px">Status</th>
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
                                $displayMinStock = (float) $item['min_stock'];
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
                                    <td class="text-center" colspan="5">
                                        <small class="text-muted">{{ $item['variant_count'] }} variants</small>
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
                                <td class="text-end {{ $displayQty < 0 ? 'text-danger fw-semibold' : '' }}">
                                    <div>
                                        {{ format_number($displayQty, 2, true) }}
                                        <small class="text-muted">{{ $item['unit'] }}</small>
                                    </div>
                                    @if(!empty($item['has_smallest_display']) && ($displayUnitMode ?? 'large') === 'large')
                                        <small class="text-primary d-block fw-semibold">
                                            = {{ format_number((float) $item['smallest_quantity'], 2, true) }} {{ $item['smallest_unit'] }}
                                        </small>
                                    @endif
                                    @if(!empty($item['show_unit_detail']) && !empty($item['stock_by_units']))
                                        <div class="stock-unit-detail mt-1">
                                            @foreach($item['stock_by_units'] as $unitStock)
                                                <small class="text-muted d-block">
                                                    {{ format_number((float) $unitStock['quantity'], 2, true) }} {{ $unitStock['unit'] }}
                                                    @if(($displayUnitMode ?? 'large') === 'large' && isset($unitStock['smallest_quantity']) && $unitStock['smallest_quantity'] !== null)
                                                        <span class="text-primary">(= {{ format_number((float) $unitStock['smallest_quantity'], 2, true) }} {{ $unitStock['smallest_unit'] }})</span>
                                                    @endif
                                                </small>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if(!empty($item['conversion_hint']))
                                        <small class="text-muted d-block fst-italic">{{ $item['conversion_hint'] }}</small>
                                    @endif
                                    @if(!empty($item['has_batch_stocks']) && !empty($item['batch_stocks']))
                                        <div class="stock-batch-detail mt-2">
                                            <button type="button"
                                                    class="btn btn-xs btn-label-info btn-sm py-0 px-2 stock-batch-toggle"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#batch-{{ $item['product_id'] }}-{{ $item['variant_id'] }}"
                                                    aria-expanded="false">
                                                <i class="ti ti-packages me-1"></i>{{ count($item['batch_stocks']) }} batch
                                            </button>
                                            <div class="collapse mt-2" id="batch-{{ $item['product_id'] }}-{{ $item['variant_id'] }}">
                                                <table class="table table-sm table-bordered mb-0 stock-batch-table">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Batch</th>
                                                            <th>Expired</th>
                                                            <th class="text-end">Qty</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($item['batch_stocks'] as $batchRow)
                                                            <tr>
                                                                <td>
                                                                    <code class="small">{{ $batchRow['batch_number'] }}</code>
                                                                </td>
                                                                <td>
                                                                    @if(($batchRow['expiry_status'] ?? 'none') === 'expired')
                                                                        <span class="badge bg-label-danger">{{ $batchRow['expiry_label'] }}</span>
                                                                    @elseif(($batchRow['expiry_status'] ?? 'none') === 'near')
                                                                        <span class="badge bg-label-warning">{{ $batchRow['expiry_label'] }}</span>
                                                                    @elseif(($batchRow['expiry_status'] ?? 'none') === 'ok')
                                                                        <span class="badge bg-label-success">{{ $batchRow['expiry_label'] }}</span>
                                                                    @else
                                                                        <span class="text-muted">-</span>
                                                                    @endif
                                                                </td>
                                                                <td class="text-end">
                                                                    {{ format_number((float) $batchRow['quantity'], 2, true) }}
                                                                    <small class="text-muted">{{ $batchRow['unit'] }}</small>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    {{ format_number($displayMinStock, 2, true) }}
                                    <small class="text-muted">{{ $item['unit'] }}</small>
                                </td>
                                <td class="text-end">
                                    @if(($item['fifo_cost'] ?? 0) > 0)
                                        <span class="text-primary" title="HPP FIFO">{{ format_number($item['fifo_cost'], 2, true) }}</span>
                                    @elseif($item['purchase_price'] > 0)
                                        {{ format_number($item['purchase_price'], 2, true) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-end">
                                    {{ $item['selling_price'] > 0 ? format_number($item['selling_price'], 2, true) : '-' }}
                                </td>
                                <td class="text-center">
                                    @if($displayQty < 0)
                                        <x-badge color="danger">Negative</x-badge>
                                    @elseif($displayMinStock > 0)
                                        @if($displayQty < $displayMinStock)
                                            <x-badge color="danger">Low Stock</x-badge>
                                        @else
                                            <x-badge color="success">OK</x-badge>
                                        @endif
                                    @else
                                        <x-badge color="secondary">-</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No stock data found.</td>
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

    @push('page-css')
        <style>
            /* Samakan tinggi & padding tombol Satuan + Filter */
            .stock-header-actions .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                height: 32px;
                padding: 0.4375rem 0.875rem;
                font-size: 0.8125rem;
                line-height: 1;
            }

            .stock-header-actions .btn i {
                font-size: 0.875rem;
                line-height: 1;
            }

            .stock-batch-table {
                font-size: 0.75rem;
                min-width: 220px;
                background: #fff;
            }
            .stock-batch-table th,
            .stock-batch-table td {
                padding: 0.25rem 0.4rem;
                vertical-align: middle;
                white-space: nowrap;
            }
            .stock-batch-toggle {
                font-size: 0.72rem;
            }
        </style>
    @endpush

    <x-modal id="filterModal" title="Filter">
        <div class="mb-3">
            <label class="form-label">Gudang <span class="text-danger">*</span></label>
            <select id="selectWarehouse" class="select2-modal form-select" data-allow-clear="true">
                <option value="">Semua Gudang (Agregat)</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @if($filterWarehouseId === $b->id) selected @endif>
                        {{ $b->name }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted">Pilih gudang spesifik untuk melihat stok per lokasi.</small>
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
                    var warehouseId = $('#selectWarehouse').val();
                    var sku = $('#selectSku').val();
                    var productId = $('#selectProduct').val();
                    var natureId = $('#selectNature').val();
                    var categoryId = $('#selectCategory').val();
                    var variantSearch = $('#variantSearch').val();
                    var perPage = $('#selectPerPage').val();

                    if (warehouseId) params.push('warehouse_id=' + encodeURIComponent(warehouseId));
                    if (sku) params.push('sku=' + encodeURIComponent(sku));
                    if (productId) params.push('product_id=' + encodeURIComponent(productId));
                    if (natureId) params.push('nature_id=' + encodeURIComponent(natureId));
                    if (categoryId) params.push('category_id=' + encodeURIComponent(categoryId));
                    if (variantSearch) params.push('variant_search=' + encodeURIComponent(variantSearch));
                    if (perPage && perPage != 20) params.push('per_page=' + encodeURIComponent(perPage));
                    params.push('display_unit=' + encodeURIComponent('{{ $displayUnitMode }}'));

                    var url = '{{ route("product.stock.index.view") }}';
                    if (params.length > 0) url += '?' + params.join('&');
                    window.location = url;
                });

                $('#btnResetFilter').on('click', function() {
                    window.location = '{{ route("product.stock.index.view") }}';
                });
            });
        </script>
    @endpush
</x-app-layout>
