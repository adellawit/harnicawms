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

        @php
            $stockPageStats = $stockPageStats ?? [
                'sku_count' => 0,
                'attention_count' => 0,
                'serial_ready' => 0,
                'serial_dispatched' => 0,
            ];
        @endphp

        <div class="row g-3 mb-4 stock-kpi-row">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100 stock-kpi-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar stock-kpi-avatar">
                            <span class="avatar-initial rounded bg-label-primary"><i class="ti ti-package"></i></span>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <small class="text-muted d-block">Produk</small>
                            <div class="fw-semibold fs-4 lh-1">{{ format_number($stockPageStats['sku_count'], 0, true) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100 stock-kpi-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar stock-kpi-avatar">
                            <span class="avatar-initial rounded bg-label-danger"><i class="ti ti-alert-triangle"></i></span>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <small class="text-muted d-block">Perlu perhatian</small>
                            <div class="fw-semibold fs-4 lh-1 {{ ($stockPageStats['attention_count'] ?? 0) > 0 ? 'text-danger' : '' }}">
                                {{ format_number($stockPageStats['attention_count'], 0, true) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100 stock-kpi-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar stock-kpi-avatar">
                            <span class="avatar-initial rounded bg-label-success"><i class="ti ti-barcode"></i></span>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <small class="text-muted d-block">Serial Ready</small>
                            <div class="fw-semibold fs-4 lh-1 text-success">{{ format_number($stockPageStats['serial_ready'], 0, true) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100 stock-kpi-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar stock-kpi-avatar">
                            <span class="avatar-initial rounded bg-label-warning"><i class="ti ti-truck-delivery"></i></span>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <small class="text-muted d-block">Serial Keluar</small>
                            <div class="fw-semibold fs-4 lh-1 text-warning">{{ format_number($stockPageStats['serial_dispatched'], 0, true) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <small class="text-muted">Ringkasan halaman ini (ikut filter)</small>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">
                        <i class="ti ti-building-warehouse me-1"></i>Product Stock
                    </h5>
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
                            <th class="text-end">Serial Ready</th>
                            <th class="text-end">Serial Keluar</th>
                            <th class="text-center" style="width:90px">Barcode</th>
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
                                    <td class="text-center" colspan="8">
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
                                    <div class="stock-qty-chip {{ $displayQty < 0 ? 'stock-qty-chip--danger' : '' }}">
                                        <span class="fw-semibold">{{ format_number($displayQty, 2, true) }}</span>
                                        <small class="text-muted">{{ $item['unit'] }}</small>
                                    </div>
                                    @if(!empty($item['has_smallest_display']) && ($displayUnitMode ?? 'large') === 'large' && (float) ($item['smallest_quantity'] ?? 0) > 0)
                                        <small class="text-primary d-block mt-1">
                                            = {{ format_number((float) $item['smallest_quantity'], 2, true) }} {{ $item['smallest_unit'] }}
                                        </small>
                                    @endif
                                    @if(!empty($item['packaging_hint']))
                                        <small class="text-muted d-block mt-1">
                                            Breakdown: {{ $item['packaging_hint'] }}
                                        </small>
                                    @endif
                                    @if(!empty($item['show_unit_detail']) && !empty($item['stock_by_units']))
                                        <div class="stock-unit-detail mt-1">
                                            <small class="text-muted d-block fw-semibold">Tersimpan per satuan:</small>
                                            @foreach($item['stock_by_units'] as $unitStock)
                                                <small class="text-muted d-block">
                                                    {{ format_number((float) $unitStock['quantity'], 2, true) }} {{ $unitStock['unit'] }}
                                                    @if(
                                                        ($displayUnitMode ?? 'large') === 'large'
                                                        && isset($unitStock['smallest_quantity'])
                                                        && $unitStock['smallest_quantity'] !== null
                                                        && ($unitStock['unit_id'] ?? null) !== ($item['smallest_unit_id'] ?? null)
                                                    )
                                                        <span class="text-primary">(= {{ format_number((float) $unitStock['smallest_quantity'], 2, true) }} {{ $unitStock['smallest_unit'] }})</span>
                                                    @endif
                                                </small>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if(!empty($item['conversion_chain_hint']))
                                        <small class="text-muted d-block fst-italic mt-1">{{ $item['conversion_chain_hint'] }}</small>
                                    @elseif(!empty($item['conversion_hint']))
                                        <small class="text-muted d-block fst-italic mt-1">{{ $item['conversion_hint'] }}</small>
                                    @endif
                                    @if(!empty($item['has_batch_stocks']) && !empty($item['batch_stocks']))
                                        <div class="stock-batch-detail mt-2">
                                            <button type="button"
                                                    class="btn btn-xs btn-label-info btn-sm py-0 px-2 stock-batch-toggle"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#batch-{{ $item['product_id'] }}-{{ $item['variant_id'] }}"
                                                    aria-expanded="false">
                                                <i class="ti ti-packages me-1"></i>{{ count($item['batch_stocks']) }} batch · FEFO
                                            </button>
                                            <div class="collapse mt-2" id="batch-{{ $item['product_id'] }}-{{ $item['variant_id'] }}">
                                                <table class="table table-sm table-bordered mb-0 stock-batch-table">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Batch</th>
                                                            <th>Expiry</th>
                                                            <th class="text-end">Stok</th>
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
                                                                    @if(
                                                                        isset($batchRow['smallest_quantity'])
                                                                        && $batchRow['smallest_quantity'] !== null
                                                                        && !empty($batchRow['smallest_unit'])
                                                                        && abs((float) $batchRow['smallest_quantity'] - (float) $batchRow['quantity']) > 1e-6
                                                                    )
                                                                        <div class="text-primary small">
                                                                            (= {{ format_number((float) $batchRow['smallest_quantity'], 2, true) }} {{ $batchRow['smallest_unit'] }})
                                                                        </div>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                                <small class="text-muted d-block mt-1">
                                                    Urutan FEFO: expiry paling dekat dipakai dulu saat produksi/outbound.
                                                </small>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if (!empty($item['is_finished_good']))
                                        <span class="badge bg-label-success stock-serial-pill">{{ format_number($item['serial_ready'] ?? 0, 0, true) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if (!empty($item['is_finished_good']))
                                        <span class="badge bg-label-warning stock-serial-pill">{{ format_number($item['serial_dispatched'] ?? 0, 0, true) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if (!empty($item['is_finished_good']))
                                        <button type="button"
                                            class="btn btn-sm btn-label-primary btn-stock-barcode-detail"
                                            title="Detail barcode"
                                            data-product-id="{{ $item['product_id'] }}"
                                            data-variant-id="{{ $item['variant_id'] }}"
                                            data-title="{{ $item['product_name'] }} · {{ $item['variant_name'] ?: ($item['sku'] ?: '') }}">
                                            <i class="ti ti-barcode"></i>
                                        </button>
                                    @else
                                        <span class="text-muted">—</span>
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
                                        <span class="badge bg-label-danger"><i class="ti ti-arrow-down me-1"></i>Negative</span>
                                    @elseif($displayMinStock > 0)
                                        @if($displayQty < $displayMinStock)
                                            <span class="badge bg-label-danger"><i class="ti ti-alert-triangle me-1"></i>Low Stock</span>
                                        @else
                                            <span class="badge bg-label-success"><i class="ti ti-circle-check me-1"></i>OK</span>
                                        @endif
                                    @else
                                        <span class="badge bg-label-secondary">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center text-muted py-5">
                                    <div class="stock-empty">
                                        <i class="ti ti-package-off mb-2 d-block" style="font-size:2rem;opacity:.45"></i>
                                        <div class="fw-medium">Tidak ada data stok untuk filter ini.</div>
                                        <small>Ubah filter gudang / produk, atau reset filter.</small>
                                    </div>
                                </td>
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

            .stock-kpi-card .card-body {
                padding: 1rem 1.15rem;
            }

            .stock-kpi-avatar {
                flex-shrink: 0;
                width: 2.5rem;
                height: 2.5rem;
            }

            .stock-kpi-avatar .avatar-initial {
                width: 2.5rem;
                height: 2.5rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 1.15rem;
            }

            .stock-qty-chip {
                display: inline-flex;
                align-items: baseline;
                gap: 0.35rem;
                padding: 0.3rem 0.55rem;
                border-radius: 0.45rem;
                background: rgba(67, 89, 113, 0.06);
                white-space: nowrap;
            }

            .stock-qty-chip--danger {
                background: rgba(255, 62, 29, 0.1);
                color: #ff3e1d;
            }

            .stock-serial-pill {
                font-variant-numeric: tabular-nums;
                min-width: 2.25rem;
            }

            .stock-empty {
                max-width: 280px;
                margin: 0 auto;
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

            .barcode-tree-node .barcode-tree-toggle:hover {
                opacity: 0.85;
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

    <div class="modal fade" id="stockBarcodeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockBarcodeModalTitle">Detail Barcode</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="stockBarcodeLoading" class="text-center text-muted py-4">Memuat…</div>
                    <div id="stockBarcodeContent" class="d-none">
                        <div class="row g-3 mb-3" id="stockBarcodeKpis"></div>
                        <div class="mb-3" id="stockBarcodeConversion"></div>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Unit</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Ready</th>
                                        <th class="text-end">Keluar</th>
                                    </tr>
                                </thead>
                                <tbody id="stockBarcodeSummary"></tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <h6 class="mb-0">Hierarki barcode</h6>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" id="stockBarcodeExpandAll">Expand all</button>
                                <button type="button" class="btn btn-outline-secondary" id="stockBarcodeCollapseAll">Collapse</button>
                            </div>
                        </div>
                        <p class="small text-muted mb-2">Tampil per Karton. Klik untuk buka Pack / Box di dalamnya.</p>
                        <div id="stockBarcodeTree" class="barcode-tree border rounded p-2" style="max-height: 420px; overflow: auto;"></div>
                        <small class="text-muted d-block mt-2" id="stockBarcodeNote"></small>
                    </div>
                    <div id="stockBarcodeEmpty" class="text-center text-muted py-4 d-none">Belum ada barcode untuk item ini.</div>
                    <div id="stockBarcodeError" class="alert alert-danger d-none mb-0"></div>
                </div>
            </div>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(document).ready(function() {
                var barcodesUrl = @json($barcodesDetailUrl ?? route('product.stock.barcodes'));
                var barcodeModalEl = document.getElementById('stockBarcodeModal');
                var barcodeModal = barcodeModalEl ? new bootstrap.Modal(barcodeModalEl) : null;

                function barcodeStatusBadge(status) {
                    return status === 'dispatched'
                        ? '<span class="badge bg-label-warning">Keluar</span>'
                        : '<span class="badge bg-label-success">Ready</span>';
                }

                function renderBarcodeTreeNode(node, depth) {
                    var hasChildren = node.children && node.children.length > 0;
                    var pad = Math.min(depth, 6) * 14;
                    var html = '<div class="barcode-tree-node py-1" style="padding-left:' + pad + 'px">';

                    if (hasChildren) {
                        var childId = 'stock-bc-' + node.id;
                        html += '<button type="button" class="btn btn-sm btn-link text-body text-start text-decoration-none p-0 barcode-tree-toggle" data-target="#' + childId + '" aria-expanded="false">' +
                            '<i class="ti ti-chevron-right barcode-tree-icon align-middle"></i> ' +
                            '<span class="badge bg-label-secondary me-1">' + node.unit_label + '</span>' +
                            '<code class="font-monospace">' + node.serial + '</code> ' +
                            barcodeStatusBadge(node.status) +
                            ' <small class="text-muted">(' + node.children.length + ')</small>' +
                            '</button>';
                        html += '<div id="' + childId + '" class="barcode-tree-children d-none mt-1">';
                        node.children.forEach(function(child) {
                            html += renderBarcodeTreeNode(child, depth + 1);
                        });
                        html += '</div>';
                    } else {
                        html += '<div class="d-flex flex-wrap align-items-center gap-1 py-1">' +
                            '<span class="badge bg-label-secondary">' + node.unit_label + '</span>' +
                            '<code class="font-monospace">' + node.serial + '</code> ' +
                            barcodeStatusBadge(node.status) +
                            '</div>';
                    }

                    html += '</div>';
                    return html;
                }

                function setBarcodeTreeExpanded($children, expanded) {
                    $children.toggleClass('d-none', !expanded);
                    var $btn = $children.prev('.barcode-tree-toggle');
                    $btn.attr('aria-expanded', expanded ? 'true' : 'false');
                    $btn.find('.barcode-tree-icon')
                        .toggleClass('ti-chevron-right', !expanded)
                        .toggleClass('ti-chevron-down', expanded);
                }

                $(document).on('click', '#stockBarcodeTree .barcode-tree-toggle', function(e) {
                    e.preventDefault();
                    var $children = $($(this).data('target'));
                    setBarcodeTreeExpanded($children, $children.hasClass('d-none'));
                });

                $('#stockBarcodeExpandAll').on('click', function() {
                    $('#stockBarcodeTree .barcode-tree-children').each(function() {
                        setBarcodeTreeExpanded($(this), true);
                    });
                });

                $('#stockBarcodeCollapseAll').on('click', function() {
                    $('#stockBarcodeTree .barcode-tree-children').each(function() {
                        setBarcodeTreeExpanded($(this), false);
                    });
                });

                $(document).on('click', '.btn-stock-barcode-detail', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (!barcodeModal) return;

                    var productId = $(this).data('product-id');
                    var variantId = $(this).data('variant-id') || '';
                    var title = $(this).data('title') || 'Detail Barcode';

                    $('#stockBarcodeModalTitle').text(title);
                    $('#stockBarcodeLoading').removeClass('d-none');
                    $('#stockBarcodeContent, #stockBarcodeEmpty, #stockBarcodeError').addClass('d-none');
                    barcodeModal.show();

                    $.get(barcodesUrl, { product_id: productId, variant_id: variantId || null })
                        .done(function(res) {
                            $('#stockBarcodeLoading').addClass('d-none');
                            if (!res.success) {
                                $('#stockBarcodeError').removeClass('d-none').text(res.message || 'Gagal memuat barcode.');
                                return;
                            }

                            var totals = res.totals || {};
                            if (!totals.total) {
                                $('#stockBarcodeEmpty').removeClass('d-none');
                                return;
                            }

                            $('#stockBarcodeKpis').html(
                                '<div class="col-md-4"><small class="text-muted d-block">Total</small><div class="fw-semibold fs-5">' + (totals.total || 0) + '</div></div>' +
                                '<div class="col-md-4"><small class="text-muted d-block">Ready</small><div class="fw-semibold fs-5 text-success">' + (totals.ready || 0) + '</div></div>' +
                                '<div class="col-md-4"><small class="text-muted d-block">Keluar (terjual)</small><div class="fw-semibold fs-5 text-warning">' + (totals.dispatched || 0) + '</div></div>'
                            );

                            if (res.conversion_chain && res.conversion_chain.length) {
                                $('#stockBarcodeConversion').html(
                                    '<small class="text-muted d-block">Aturan konversi</small><div class="fw-medium">' +
                                    res.conversion_chain.join(' · ') + '</div>'
                                );
                            } else {
                                $('#stockBarcodeConversion').empty();
                            }

                            var summaryHtml = '';
                            (res.summary || []).forEach(function(row) {
                                summaryHtml += '<tr>' +
                                    '<td>L' + row.unit_level + ' · ' + row.unit_label + '</td>' +
                                    '<td class="text-end">' + row.total + '</td>' +
                                    '<td class="text-end text-success">' + row.ready + '</td>' +
                                    '<td class="text-end text-warning">' + row.dispatched + '</td>' +
                                    '</tr>';
                            });
                            $('#stockBarcodeSummary').html(summaryHtml || '<tr><td colspan="4" class="text-muted text-center">—</td></tr>');

                            var tree = res.tree || [];
                            var treeHtml = '';
                            tree.forEach(function(node) {
                                treeHtml += renderBarcodeTreeNode(node, 0);
                            });
                            $('#stockBarcodeTree').html(
                                treeHtml || '<div class="text-muted text-center py-3">Tidak ada hierarki Karton/Pack/Box.</div>'
                            );
                            $('#stockBarcodeNote').text(
                                tree.length
                                    ? ('Menampilkan ' + tree.length + ' group level atas. Klik untuk membuka isi.')
                                    : ''
                            );
                            $('#stockBarcodeContent').removeClass('d-none');
                        })
                        .fail(function(xhr) {
                            $('#stockBarcodeLoading').addClass('d-none');
                            $('#stockBarcodeError').removeClass('d-none').text(
                                (xhr.responseJSON && xhr.responseJSON.message) || 'Gagal memuat barcode.'
                            );
                        });
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
