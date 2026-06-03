<x-app-layout>

    @section('title', 'Add Product | ')

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <style>
            .breadcrumb-item a:hover {
                color: #212529 !important;
            }
            .font-monospace {
                font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
                font-size: 0.875rem;
            }
        </style>
    @endpush

    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => route('product.index.view')],
                ['label' => 'Add Product', 'active' => true]
            ]"
        />

        <!-- Progress Steps -->
        <div class="card mb-3">
            <div class="card-body">
                <ul class="list-group list-group-flush list-group-horizontal d-flex justify-content-center mb-0">
                    <li class="list-group-item d-flex align-items-center border-end-0 pe-0">
                        <span class="badge bg-label-success rounded-circle me-2"><i class="ti ti-check"></i></span>
                        <span class="text-muted">Product Info</span>
                    </li>
                    <li class="list-group-item d-flex align-items-center border-end-0 pe-0 ps-2">
                        <span class="badge bg-label-success rounded-circle me-2"><i class="ti ti-check"></i></span>
                        <span class="text-muted">Unit Conversions</span>
                    </li>
                    <li class="list-group-item d-flex align-items-center ps-2">
                        <span class="badge bg-label-primary rounded-circle me-2">3</span>
                        <span class="fw-bold">Variants & Prices</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Step 3 Form - Variants with Modal -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Step 3: Variants & Prices</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="ti ti-info-circle me-1"></i>
                    <strong>Product with variants:</strong> Add variants for this product. Each variant can have different prices for different price lists (e.g., Offline, Marketplace, Online Store, etc.).
                </div>

                <!-- Variants Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="variantsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th style="width: 150px;">SKU</th>
                                <th style="width: 150px;">Barcode</th>
                                <th>Attributes</th>
                                <th style="width: 150px;">Prices</th>
                                <th style="width: 80px;">Active</th>
                                <th class="text-end" style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="variants-body">
                            <!-- Variants will be rendered here -->
                        </tbody>
                    </table>
                </div>

                <!-- Add Variant Button -->
                <div class="mt-3">
                    <button type="button" class="btn btn-sm btn-primary" onclick="openAddModal();">
                        <i class="ti ti-plus me-1"></i> Add Variant
                    </button>
                </div>

                <div class="col-12 mt-4 text-end">
                    <a href="{{ route('product.insert.view.step2') }}" class="btn btn-label-secondary">Back</a>
                    <button type="button" class="btn btn-primary" onclick="submitVariants()">
                        <i class="ti ti-save me-1"></i> Save Product
                    </button>
                </div>
            </div>
        </div>

    </div>
    <!-- / Content -->

    <!-- Add/Edit Variant Modal -->
    <div class="modal fade" id="variantModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="variantForm">
                    @csrf
                    <input type="hidden" id="variantIndex" name="variant_index">

                    <div class="modal-header">
                        <h5 class="modal-title" id="variantModalTitle">Add Variant</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <!-- SKU (read-only, auto-generated) -->
                        <div class="mb-3">
                            <label class="form-label">SKU</label>
                            <input type="text" class="form-control" id="variantSku" name="sku" readonly>
                        </div>

                        <!-- Barcode -->
                        <div class="mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" class="form-control" id="variantBarcode" name="barcode" placeholder="Auto-generated">
                        </div>

                        <!-- Attributes -->
                        @if($attributeDefinitions->count() > 0)
                        <div class="mb-3">
                            <label class="form-label">Attributes</label>
                            <div id="attributesContainer" class="row g-2">
                                @foreach($attributeDefinitions as $attr)
                                    <div class="col-md-6">
                                        <label class="form-label text-muted">{{ $attr->name }}</label>
                                        <select class="select2 form-select attribute-select" data-attribute-def-id="{{ $attr->id }}">
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

                        <!-- Weight & Sort Order -->
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
                                <label class="form-check-label" for="variantActive">Active</label>
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
                <div class="modal-header">
                    <h5 class="modal-title">Delete Variant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this variant?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete</button>
                </div>
            </div>
        </div>
    </div>

    @push('page-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            let priceLists = [];
            let tempVariants = [];
            let addedPriceLists = {}; // { price_list_id: { purchase_price, selling_price, name } }
            let editingPriceListId = null;
            let deleteIndex = null;

            // Product SKU settings from server
            // Variant SKU format: V-YY-XXXXX
            const variantSkuPrefix = 'V-' + new Date().getFullYear().toString().slice(-2) + '-';
            let variantSkuSequence = 1;
            let barcodeSequence = 1;

            // Load price lists on page load
            document.addEventListener('DOMContentLoaded', function() {
                loadPriceLists();
                renderVariants();
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

            // Number formatting for Indonesian format
            function formatNumberInput(input) {
                let value = input.value.replace(/[^\d,]/g, '');
                if (value) {
                    let parts = value.split(',');
                    let integerPart = parts[0].replace(/\D/g, '');
                    let decimalPart = parts[1] || '';
                    if (integerPart) {
                        let formatted = parseInt(integerPart || 0).toLocaleString('id-ID');
                        if (decimalPart) {
                            formatted += ',' + decimalPart;
                        }
                        input.value = formatted;
                    }
                }
            }

            function parseFormattedNumber(value) {
                if (!value) return 0;
                let normalized = value.replace(/\./g, '').replace(/,/g, '.');
                return parseFloat(normalized) || 0;
            }

            function formatCurrency(num) {
                if (!num || num === 0) return '0,00';
                let rounded = Math.round(num * 100) / 100;
                let parts = rounded.toString().split('.');
                let integerPart = parseInt(parts[0]).toLocaleString('en-US');
                let decimalPart = parts[1] || '00';
                integerPart = integerPart.replace(/,/g, '.');
                return integerPart + ',' + decimalPart.padEnd(2, '0');
            }

            // Price list management
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

            // SKU/Barcode generation
            function generateSku(variantIndex) {
                // Format: V-YY-XXXXX (5-digit sequence)
                const sequence = String(variantSkuSequence++).padStart(5, '0');
                return variantSkuPrefix + sequence;
            }

            function generateBarcode() {
                // Format: YYMMDDXXXXXXX (7-digit sequence)
                const date = new Date();
                const yy = String(date.getFullYear()).slice(-2);
                const mm = String(date.getMonth() + 1).padStart(2, '0');
                const dd = String(date.getDate()).padStart(2, '0');
                const sequence = String(barcodeSequence++).padStart(7, '0');
                return yy + mm + dd + sequence;
            }

            // Get attribute display values
            function getAttributeDisplayValues(attributes) {
                if (!attributes || attributes.length === 0) return '-';

                return attributes.map(attr => {
                    const select = document.querySelector(`#attributesContainer select[data-attribute-def-id="${attr.attribute_definition_id}"]`);
                    if (select) {
                        const option = select.querySelector(`option[value="${attr.attribute_value_id}"]`);
                        return option ? option.text : attr.attribute_value_id;
                    }
                    return attr.attribute_value_id;
                }).join(', ');
            }

            // Modal functions
            function openAddModal() {
                document.getElementById('variantModalTitle').textContent = 'Add Variant';
                document.getElementById('variantForm').reset();
                document.getElementById('variantIndex').value = '';
                document.getElementById('variantActive').checked = true;
                document.getElementById('variantSortOrder').value = '0';

                // Auto-generate SKU and Barcode
                document.getElementById('variantSku').value = generateSku(tempVariants.length);
                document.getElementById('variantBarcode').value = generateBarcode();

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

            function openEditModal(index) {
                const variant = tempVariants[index];
                if (!variant) return;

                document.getElementById('variantModalTitle').textContent = 'Edit Variant';
                document.getElementById('variantIndex').value = index;

                // Basic info
                document.getElementById('variantSku').value = variant.sku || '';
                document.getElementById('variantBarcode').value = variant.barcode || '';
                document.getElementById('variantWeight').value = variant.weight || '';
                document.getElementById('variantSortOrder').value = variant.sort_order || 0;
                document.getElementById('variantActive').checked = variant.is_active;

                // Reset attributes first
                document.querySelectorAll('#attributesContainer select').forEach(select => {
                    select.value = '';
                });
                // Set attributes
                if (variant.attributes && variant.attributes.length > 0) {
                    variant.attributes.forEach(attr => {
                        const select = document.querySelector(`#attributesContainer select[data-attribute-def-id="${attr.attribute_definition_id}"]`);
                        if (select) {
                            select.value = attr.attribute_value_id;
                        }
                    });
                }

                // Load price lists
                addedPriceLists = {};
                if (variant.prices && Object.keys(variant.prices).length > 0) {
                    Object.entries(variant.prices).forEach(([listId, price]) => {
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
            }

            function openDeleteModal(index) {
                deleteIndex = index;
                const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
                modal.show();
            }

            function confirmDelete() {
                if (deleteIndex !== null) {
                    tempVariants.splice(deleteIndex, 1);
                    renderVariants();
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    deleteIndex = null;
                }
            }

            // Form submit
            document.getElementById('variantForm').addEventListener('submit', function(e) {
                e.preventDefault();

                const index = document.getElementById('variantIndex').value;
                const isNew = index === '';

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

                const variant = {
                    sku: document.getElementById('variantSku').value,
                    barcode: document.getElementById('variantBarcode').value,
                    weight: document.getElementById('variantWeight').value || '',
                    sort_order: parseInt(document.getElementById('variantSortOrder').value) || 0,
                    is_active: document.getElementById('variantActive').checked,
                    attributes: attributes,
                    prices: {...addedPriceLists}
                };

                if (isNew) {
                    tempVariants.push(variant);
                } else {
                    tempVariants[index] = variant;
                }

                bootstrap.Modal.getInstance(document.getElementById('variantModal')).hide();
                renderVariants();
            });

            // Render variants table
            function renderVariants() {
                const tbody = document.getElementById('variants-body');
                tbody.innerHTML = '';

                if (tempVariants.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="ti ti-package text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2 mb-0">No variants added yet. Click "Add Variant" to start.</p>
                            </td>
                        </tr>
                    `;
                    return;
                }

                tempVariants.forEach((variant, index) => {
                    const row = document.createElement('tr');

                    // Build attributes display
                    let attributesDisplay = '-';
                    if (variant.attributes && variant.attributes.length > 0) {
                        const attrContainer = document.getElementById('attributesContainer');
                        if (attrContainer) {
                            attributesDisplay = variant.attributes.map(attr => {
                                const select = attrContainer.querySelector(`select[data-attribute-def-id="${attr.attribute_definition_id}"]`);
                                if (select) {
                                    const option = select.querySelector(`option[value="${attr.attribute_value_id}"]`);
                                    return option ? `<span class="badge bg-label-secondary">${option.text}</span>` : '';
                                }
                                return '';
                            }).filter(Boolean).join(' ');
                        }
                    }

                    // Build prices display
                    let pricesDisplay = '-';
                    if (variant.prices && Object.keys(variant.prices).length > 0) {
                        const priceBadges = Object.entries(variant.prices).map(([listId, price]) => {
                            const list = priceLists.find(l => l.id === listId);
                            const listName = list ? list.name : listId;
                            return `<span class="badge bg-label-secondary">${listName}: Rp ${formatCurrency(price.selling_price || 0)}</span>`;
                        });
                        pricesDisplay = `<div class="d-flex flex-wrap gap-1">${priceBadges.join('')}</div>`;
                    }

                    // Active status
                    const activeStatus = variant.is_active
                        ? '<span class="badge bg-label-success">Active</span>'
                        : '<span class="badge bg-label-danger">Inactive</span>';

                    row.innerHTML = `
                        <td>${index + 1}</td>
                        <td><span class="font-monospace">${variant.sku || '-'}</span></td>
                        <td><span class="font-monospace">${variant.barcode || '-'}</span></td>
                        <td>${attributesDisplay}</td>
                        <td>${pricesDisplay}</td>
                        <td>${activeStatus}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-icon btn-outline-warning" onclick="openEditModal(${index})">
                                <i class="ti ti-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-icon btn-outline-danger" onclick="openDeleteModal(${index})">
                                <i class="ti ti-trash"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }

            // Submit all variants
            function submitVariants() {
                if (tempVariants.length === 0) {
                    alert('Please add at least one variant');
                    return;
                }

                const formData = new FormData();
                formData.append('variants', JSON.stringify(tempVariants));
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                fetch('{{ route("product.insert.data.step3") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = '{{ route("product.index.view") }}';
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error submitting variants');
                });
            }
        </script>
    @endpush

</x-app-layout>
