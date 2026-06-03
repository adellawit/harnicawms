<x-app-layout>

    @section('title', 'Product Variants | ')

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => route('product.index.view')],
                ['label' => 'Variants', 'active' => true]
            ]"
        />

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Variants: {{ $product->name }}</h5>
                <div class="card-actions">
                    <a href="{{ route('product.index.view') }}" class="btn btn-sm btn-label-secondary">
                        <i class="ti ti-arrow-left me-1"></i> Back
                    </a>
                    <a href="{{ route('product.edit.view', $product->id) }}" class="btn btn-sm btn-primary">
                        <i class="ti ti-pencil me-1"></i> Edit Product
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Product Info Summary -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <small class="text-muted">SKU</small>
                        <div class="fw-bold">{{ $product->sku ?? '-' }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Code</small>
                        <div class="fw-bold">{{ $product->code ?? '-' }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Default Unit</small>
                        <div class="fw-bold">{{ $product->defaultUnit->symbol ?? $product->defaultUnit->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Product Type</small>
                        <div class="fw-bold">{{ $product->nature->name ?? '-' }}</div>
                    </div>
                </div>

                <!-- Variants Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="variantsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th style="width: 120px;">SKU</th>
                                <th style="width: 120px;">Barcode</th>
                                <th>Attributes</th>
                                <th style="width: 120px;">Purchase Price</th>
                                <th style="width: 120px;">Selling Price</th>
                                <th style="width: 80px;">Active</th>
                                <th class="text-end" style="width: 80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($product->variants as $index => $variant)
                                <tr data-variant-id="{{ $variant->id }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <span class="font-monospace">{{ $variant->sku ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="font-monospace">{{ $variant->barcode ?? '-' }}</span>
                                    </td>
                                    <td>
                                        @if($variant->variantAttributes->isNotEmpty())
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach($variant->variantAttributes as $attr)
                                                    <span class="badge bg-label-secondary">
                                                        {{ $attr->attributeDefinition->name ?? '' }}:
                                                        <strong>{{ $attr->attributeValue->value ?? '' }}</strong>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($variant->prices->isNotEmpty())
                                            <div class="d-flex flex-wrap gap-1">
                                                @php
                                                    $displayPrices = $variant->prices->take(5);
                                                    $remainingCount = $variant->prices->count() - 5;
                                                @endphp
                                                @foreach($displayPrices as $price)
                                                    @php
                                                        // Convert from smallest unit to display unit
                                                        $displayPrice = ($price->purchase_price ?? 0) * ($factor ?? 1);
                                                        // Format: 50.000,00 (dot for thousand, comma for decimal)
                                                        $formattedPrice = number_format($displayPrice, 2, ',', '.');
                                                        $priceListName = $price->priceList->name ?? '-';
                                                    @endphp
                                                    <span class="badge bg-label-secondary">
                                                        {{ $priceListName }}: {{ $formattedPrice }}
                                                    </span>
                                                @endforeach
                                                @if($remainingCount > 0)
                                                    <span class="badge bg-label-secondary">+{{ $remainingCount }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($variant->prices->isNotEmpty())
                                            <div class="d-flex flex-wrap gap-1">
                                                @php
                                                    $displayPrices = $variant->prices->take(5);
                                                    $remainingCount = $variant->prices->count() - 5;
                                                @endphp
                                                @foreach($displayPrices as $price)
                                                    @php
                                                        // Convert from smallest unit to display unit
                                                        $displayPrice = ($price->selling_price ?? 0) * ($factor ?? 1);
                                                        // Format: 50.000,00 (dot for thousand, comma for decimal)
                                                        $formattedPrice = number_format($displayPrice, 2, ',', '.');
                                                        $priceListName = $price->priceList->name ?? '-';
                                                    @endphp
                                                    <span class="badge bg-label-primary">
                                                        {{ $priceListName }}: {{ $formattedPrice }}
                                                    </span>
                                                @endforeach
                                                @if($remainingCount > 0)
                                                    <span class="badge bg-label-primary">+{{ $remainingCount }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($variant->is_active)
                                            <span class="badge bg-label-success">Active</span>
                                        @else
                                            <span class="badge bg-label-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-icon btn-outline-warning" onclick="openEditModal('{{ $variant->id }}');">
                                                <i class="ti ti-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-icon btn-outline-danger" onclick="openDeleteModal('{{ $variant->id }}');">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="ti ti-package text-muted" style="font-size: 3rem;"></i>
                                        <p class="text-muted mt-2 mb-0">No variants found for this product.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Add Variant Button -->
                <div class="mt-3">
                    <button type="button" class="btn btn-sm btn-primary" onclick="openAddModal();">
                        <i class="ti ti-plus me-1"></i> Add Variant
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Variant Modal -->
    <div class="modal fade" id="variantModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="variantForm">
                    @csrf
                    <input type="hidden" id="variantId" name="variant_id">
                    <input type="hidden" id="productId" name="product_id" value="{{ $product->id }}">

                    <div class="modal-header">
                        <h5 class="modal-title" id="variantModalTitle">Add Variant</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <!-- Barcode -->
                        <div class="mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" class="form-control" id="variantBarcode" name="barcode" placeholder="Optional">
                        </div>

                        <!-- Attributes -->
                        @if($attributeDefinitions->count() > 0)
                        <div class="mb-3">
                            <label class="form-label">Attributes</label>
                            <div id="attributesContainer" class="row g-2">
                                @foreach($attributeDefinitions as $attr)
                                <div class="col-md-6">
                                    <label class="form-label text-muted">{{ $attr->name }}</label>
                                    <select class="select2 form-select" name="attributes[{{ $loop->index }}][attribute_definition_id]" data-attribute-def-id="{{ $attr->id }}">
                                        <option value="">-- Select --</option>
                                        @foreach($attr->attributeValues as $value)
                                            <option value="{{ $value->id }}">{{ $value->value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Price Lists -->
                        <div class="mb-3">
                            <label class="form-label">Prices by Price List</label>
                            <div id="addedPricesList" class="mb-2">
                                <!-- Added prices will be displayed here -->
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label text-muted">Price List</label>
                                    <select class="select2 form-select" id="priceListSelect">
                                        <option value="">-- Select Price List --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label text-muted">Purchase Price</label>
                                    <input type="text" class="form-control price-input" id="purchasePriceInput" placeholder="0" onkeyup="formatNumberInput(this)">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label text-muted">Selling Price</label>
                                    <input type="text" class="form-control price-input" id="sellingPriceInput" placeholder="0" onkeyup="formatNumberInput(this)">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-primary w-100" onclick="addPriceListItem()">
                                        <i class="ti ti-plus me-1"></i> Add
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Weight -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Weight</label>
                                <input type="number" class="form-control" id="variantWeight" name="weight" step="0.01" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sort Order</label>
                                <input type="number" class="form-control" id="variantSortOrder" name="sort_order" value="0">
                            </div>
                        </div>

                        <!-- Active Status -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="variantActive" name="is_active" checked>
                                <label class="form-check-label" for="variantActive">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-save me-1"></i> Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form id="deleteForm">
                    @csrf
                    <input type="hidden" id="deleteVariantId">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Variant</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this variant?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('page-css')
        <style>
            .font-monospace {
                font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
                font-size: 0.875rem;
            }
            .price-list-item {
                position: relative;
                padding-right: 25px;
            }
            .price-list-item .remove-price {
                position: absolute;
                top: 50%;
                right: 5px;
                transform: translateY(-50%);
                color: #dc3545;
                cursor: pointer;
                font-size: 18px;
                line-height: 1;
            }
        </style>
    @endpush

    @push('page-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            let priceLists = [];
            let addedPriceLists = {}; // { price_list_id: { purchase_price, selling_price, name } }
            let editingPriceListId = null; // Track which price list is being edited
            let attributeDefinitions = @js($attributeDefinitions);
            const productId = '{{ $product->id }}';

            // Load price lists on page load
            document.addEventListener('DOMContentLoaded', function() {
                loadPriceLists();
            });

            function loadPriceLists() {
                fetch('{{ route("product.price-list.active") }}')
                    .then(response => response.json())
                    .then(data => {
                        priceLists = data;
                        populatePriceListDropdown();
                    })
                    .catch(error => {
                        console.error('Error loading price lists:', error);
                    });
            }

            function populatePriceListDropdown() {
                const select = document.getElementById('priceListSelect');
                select.innerHTML = '<option value="">-- Select Price List --</option>';
                priceLists.forEach(list => {
                    // Exclude already added price lists from dropdown
                    if (!addedPriceLists[list.id] || list.id === editingPriceListId) {
                        select.innerHTML += `<option value="${list.id}">${list.name}</option>`;
                    }
                });
            }

            function formatNumberInput(input) {
                // For Indonesian format: dot (.) is thousand separator, comma (,) is decimal
                // Remove all non-digit except comma (for decimal)
                let value = input.value.replace(/[^\d,]/g, '');

                if (value) {
                    // Split by comma to handle decimal
                    let parts = value.split(',');
                    let integerPart = parts[0].replace(/\D/g, ''); // Keep only digits
                    let decimalPart = parts[1] || '';

                    if (integerPart) {
                        // Format integer part with thousand separator (dot)
                        let formatted = parseInt(integerPart || 0).toLocaleString('id-ID');
                        if (decimalPart) {
                            formatted += ',' + decimalPart;
                        }
                        input.value = formatted;
                    }
                }
            }

            function parseFormattedNumber(value) {
                // For Indonesian format: 10.000 = 10000, 10.500,50 = 10500.50
                if (!value) return 0;

                // Replace dots (thousand separator) with empty, replace comma (decimal) with dot
                let normalized = value.replace(/\./g, '').replace(/,/g, '.');
                return parseFloat(normalized) || 0;
            }

            function formatCurrency(num) {
                // Format number as Indonesian currency: 50.000,00 (dot for thousand, comma for decimal)
                if (!num || num === 0) return '0,00';

                // Round to 2 decimal places
                let rounded = Math.round(num * 100) / 100;

                // Separate integer and decimal parts
                let parts = rounded.toString().split('.');
                let integerPart = parseInt(parts[0]).toLocaleString('en-US'); // 50,000
                let decimalPart = parts[1] || '00';

                // Swap thousand separator from comma to dot
                integerPart = integerPart.replace(/,/g, '.'); // 50.000

                return integerPart + ',' + decimalPart.padEnd(2, '0');
            }

            function addPriceListItem() {
                const select = document.getElementById('priceListSelect');
                const listId = select.value;
                const purchasePrice = parseFormattedNumber(document.getElementById('purchasePriceInput').value);
                const sellingPrice = parseFormattedNumber(document.getElementById('sellingPriceInput').value);

                if (!listId) {
                    alert('Please select a price list');
                    return;
                }

                const list = priceLists.find(l => l.id === listId);
                if (list) {
                    addedPriceLists[listId] = {
                        purchase_price: purchasePrice,
                        selling_price: sellingPrice,
                        name: list.name
                    };
                    renderAddedPriceLists();
                    resetPriceInputs();
                }
            }

            function resetPriceInputs() {
                document.getElementById('purchasePriceInput').value = '';
                document.getElementById('sellingPriceInput').value = '';
                editingPriceListId = null;
                populatePriceListDropdown();

                // Reset dropdown to "-- Select Price List --"
                const select = document.getElementById('priceListSelect');
                select.value = '';
                $(select).trigger('change.select2');
            }

            function editPriceListItem(listId) {
                const item = addedPriceLists[listId];
                if (item) {
                    editingPriceListId = listId;
                    populatePriceListDropdown();
                    const select = document.getElementById('priceListSelect');
                    select.value = listId;
                    $(select).trigger('change.select2');
                    document.getElementById('purchasePriceInput').value = formatCurrency(item.purchase_price);
                    document.getElementById('sellingPriceInput').value = formatCurrency(item.selling_price);
                }
            }

            function removePriceList(listId) {
                if (confirm('Are you sure you want to remove this price?')) {
                    delete addedPriceLists[listId];
                    if (editingPriceListId === listId) {
                        resetPriceInputs();
                    }
                    renderAddedPriceLists();
                    populatePriceListDropdown();
                }
            }

            function renderAddedPriceLists() {
                const container = document.getElementById('addedPricesList');
                const listIds = Object.keys(addedPriceLists);

                if (listIds.length === 0) {
                    container.innerHTML = '';
                    return;
                }

                let html = `
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Price List</th>
                                <th>Purchase Price</th>
                                <th>Selling Price</th>
                                <th class="text-end" style="width: 100px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                listIds.forEach(listId => {
                    const item = addedPriceLists[listId];
                    html += `
                        <tr>
                            <td><span class="badge bg-label-secondary">${item.name}</span></td>
                            <td>Rp ${formatCurrency(item.purchase_price)}</td>
                            <td>Rp ${formatCurrency(item.selling_price)}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-warning" onclick="editPriceListItem('${listId}')">
                                        <i class="ti ti-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger" onclick="removePriceList('${listId}')">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                html += `
                        </tbody>
                    </table>
                `;
                container.innerHTML = html;
            }

            // Add Variant Modal
            function openAddModal() {
                document.getElementById('variantModalTitle').textContent = 'Add Variant';
                document.getElementById('variantForm').reset();
                document.getElementById('variantId').value = '';
                document.getElementById('variantActive').checked = true;
                document.getElementById('variantSortOrder').value = '0';

                // Reset attributes
                document.querySelectorAll('#attributesContainer select').forEach(select => {
                    select.value = '';
                });

                // Clear added price lists
                addedPriceLists = {};
                renderAddedPriceLists();
                resetPriceInputs();

                const modalElement = document.getElementById('variantModal');
                const modal = new bootstrap.Modal(modalElement);

                // Initialize select2 after modal is shown
                modalElement.addEventListener('shown.bs.modal', function() {
                    $('#attributesContainer select.select2, #priceListSelect').select2({
                        dropdownParent: $('#variantModal'),
                        width: '100%'
                    });
                }, { once: true });

                modal.show();
            }

            // Edit Variant Modal
            function openEditModal(variantId) {
                fetch(`{{ url('/product/items/variants') }}/${variantId}/edit`)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('variantModalTitle').textContent = 'Edit Variant';
                        document.getElementById('variantId').value = variantId;

                        // Basic info
                        document.getElementById('variantBarcode').value = data.variant.barcode || '';
                        document.getElementById('variantWeight').value = data.variant.weight || '';
                        document.getElementById('variantSortOrder').value = data.variant.sort_order || 0;
                        document.getElementById('variantActive').checked = data.variant.is_active;

                        // Reset attributes first
                        document.querySelectorAll('#attributesContainer select').forEach(select => {
                            select.value = '';
                            select.setAttribute('data-attribute-value-id', '');
                        });
                        // Set attributes
                        data.attributes.forEach(attr => {
                            const select = document.querySelector(`#attributesContainer select[data-attribute-def-id="${attr.attribute_definition_id}"]`);
                            if (select) {
                                select.value = attr.attribute_value_id;
                                select.setAttribute('data-attribute-value-id', attr.attribute_value_id);
                            }
                        });

                        // Load price lists
                        addedPriceLists = {};
                        if (data.prices && Object.keys(data.prices).length > 0) {
                            Object.entries(data.prices).forEach(([listId, price]) => {
                                const list = priceLists.find(l => l.id === listId);
                                if (list) {
                                    addedPriceLists[listId] = {
                                        purchase_price: price.purchase_price || 0,
                                        selling_price: price.selling_price || 0,
                                        name: list.name
                                    };
                                }
                            });
                        }
                        renderAddedPriceLists();
                        resetPriceInputs();

                        const modalElement = document.getElementById('variantModal');
                        const modal = new bootstrap.Modal(modalElement);

                        // Initialize select2 after modal is shown
                        modalElement.addEventListener('shown.bs.modal', function() {
                            $('#attributesContainer select.select2, #priceListSelect').select2({
                                dropdownParent: $('#variantModal'),
                                width: '100%'
                            });
                        }, { once: true });

                        modal.show();
                    })
                    .catch(error => {
                        console.error('Error loading variant:', error);
                        alert('Failed to load variant data');
                    });
            }

            // Delete Variant Modal
            function openDeleteModal(variantId) {
                document.getElementById('deleteVariantId').value = variantId;
                const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
                modal.show();
            }

            // Form Submit
            document.getElementById('variantForm').addEventListener('submit', function(e) {
                e.preventDefault();

                const variantId = document.getElementById('variantId').value;
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                // Prepare attributes array
                const attributes = [];
                document.querySelectorAll('#attributesContainer select').forEach(select => {
                    const attrDefId = select.dataset.attributeDefId;
                    const valueId = select.value;
                    if (valueId) {
                        attributes.push({
                            attribute_definition_id: attrDefId,
                            attribute_value_id: valueId
                        });
                    }
                });

                const requestData = {
                    _token: csrfToken,
                    barcode: document.getElementById('variantBarcode').value,
                    weight: document.getElementById('variantWeight').value || '',
                    sort_order: document.getElementById('variantSortOrder').value || 0,
                    is_active: document.getElementById('variantActive').checked ? 1 : 0,
                    attributes: attributes,
                    prices: []
                };

                // Collect added price lists
                Object.entries(addedPriceLists).forEach(([listId, item]) => {
                    requestData.prices.push({
                        price_list_id: listId,
                        purchase_price: item.purchase_price,
                        selling_price: item.selling_price
                    });
                });

                const url = variantId
                    ? `{{ url('/product/items/variants') }}/${variantId}/update`
                    : `{{ url('/product/items/variants') }}/${productId}/store`;

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(requestData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal and reload page
                        bootstrap.Modal.getInstance(document.getElementById('variantModal')).hide();
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to save variant');
                });
            });

            // Delete Form Submit
            document.getElementById('deleteForm').addEventListener('submit', function(e) {
                e.preventDefault();

                const variantId = document.getElementById('deleteVariantId').value;
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                fetch(`{{ url('/product/items/variants') }}/${variantId}/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        _token: csrfToken
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal and reload page
                        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to delete variant');
                });
            });
        </script>
    @endpush

</x-app-layout>
