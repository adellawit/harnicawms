<x-app-layout>

    @section('title', 'Add Product | ')

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <style>
            .breadcrumb-item a:hover {
                color: #212529 !important;
            }
            .select-with-action {
                display: flex;
                align-items: stretch;
            }
            .select-with-action__field {
                flex: 1 1 auto;
                min-width: 0;
            }
            .select-with-action__field .select2-container {
                width: 100% !important;
            }
            .select-with-action__field .select2-container .select2-selection--single {
                border-top-right-radius: 0 !important;
                border-bottom-right-radius: 0 !important;
                border-right: 0 !important;
                min-height: calc(2.25rem + 2px);
            }
            .select-with-action .btn-add-inline {
                border-top-left-radius: 0;
                border-bottom-left-radius: 0;
                flex-shrink: 0;
                display: inline-flex;
                align-items: center;
                white-space: nowrap;
                padding-left: 0.875rem;
                padding-right: 0.875rem;
            }
            .select-with-action.is-invalid .select2-selection--single {
                border-color: var(--bs-form-invalid-border-color, #ff4c51) !important;
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
                        <span class="badge bg-label-primary rounded-circle me-2">1</span>
                        <span class="fw-bold">Product Info</span>
                    </li>
                    <li class="list-group-item d-flex align-items-center border-end-0 pe-0 ps-2 opacity-50">
                        <span class="badge bg-label-secondary rounded-circle me-2">2</span>
                        <span class="text-muted">Unit Conversions</span>
                    </li>
                    <li class="list-group-item d-flex align-items-center ps-2 opacity-50">
                        <span class="badge bg-label-secondary rounded-circle me-2">3</span>
                        <span class="text-muted">Variants & Prices</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Step 1 Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Step 1: Product Information</h5>
            </div>
            <div class="card-body">
                @if ($errors->any())
                    <x-alert type="danger" class="mb-3">
                        <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </x-alert>
                @endif

                <form method="POST" action="{{ route('product.insert.data.step1') }}" class="row g-3">
                    @csrf

                    <div class="col-md-6">
                        <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') ?? $tempProduct['name'] ?? '' }}" required>
                        @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="code" class="form-label">Code</label>
                        <div class="input-group">
                            <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') ?? $tempProduct['code'] ?? $generatedCode ?? '' }}" readonly>
                            <button type="button" class="btn btn-outline-secondary" onclick="regenerateCode()" title="Regenerate Code">
                                <i class="ti ti-refresh"></i>
                            </button>
                        </div>
                        @error('code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="nature_id" class="form-label">Product Type</label>
                        <select id="nature_id" name="nature_id" class="select2 form-select @error('nature_id') is-invalid @enderror" data-allow-clear="true">
                            <option value="">Select Product Type</option>
                            @foreach ($natures as $nature)
                                <option value="{{ $nature->id }}" {{ (old('nature_id') ?? $tempProduct['nature_id'] ?? '') == $nature->id ? 'selected' : '' }}>{{ $nature->name }}</option>
                            @endforeach
                        </select>
                        @error('nature_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="category_id" class="form-label">Category</label>
                        <div class="select-with-action @error('category_id') is-invalid @enderror">
                            <div class="select-with-action__field">
                                <select id="category_id" name="category_id" class="select2 form-select @error('category_id') is-invalid @enderror" data-allow-clear="true">
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ (old('category_id') ?? $tempProduct['category_id'] ?? '') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" class="btn btn-primary btn-add-inline" data-bs-toggle="modal" data-bs-target="#quickCategoryModal" title="Add New Category">
                                <i class="ti ti-plus me-1"></i>Add New
                            </button>
                        </div>
                        @error('category_id') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="item_type_id" class="form-label">Item Type</label>
                        <select id="item_type_id" name="item_type_id" class="select2 form-select @error('item_type_id') is-invalid @enderror" data-allow-clear="true">
                            <option value="">Select Item Type</option>
                            @foreach ($itemTypes as $itemType)
                                <option value="{{ $itemType->id }}" @selected((old('item_type_id') ?? $tempProduct['item_type_id'] ?? $defaultItemTypeId ?? '') == $itemType->id)>
                                    {{ $itemType->value }}
                                </option>
                            @endforeach
                        </select>
                        @error('item_type_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="product_nature_id" class="form-label">Inventory Nature</label>
                        <select id="product_nature_id" name="product_nature_id" class="select2 form-select @error('product_nature_id') is-invalid @enderror" data-allow-clear="true">
                            <option value="">Select Nature</option>
                            @foreach ($productNatures as $productNature)
                                <option value="{{ $productNature->id }}" data-key="{{ $productNature->key }}" @selected((old('product_nature_id') ?? $tempProduct['product_nature_id'] ?? $defaultProductNatureId ?? '') == $productNature->id)>
                                    {{ $productNature->value }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Inventory item akan track stok dan HPP.</small>
                        @error('product_nature_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="procurement_type_id" class="form-label">Procurement Type</label>
                        <select id="procurement_type_id" name="procurement_type_id" class="select2 form-select @error('procurement_type_id') is-invalid @enderror" data-allow-clear="true">
                            <option value="">Select Procurement</option>
                            @foreach ($procurementTypes as $procurementType)
                                <option value="{{ $procurementType->id }}" data-key="{{ $procurementType->key }}" @selected((old('procurement_type_id') ?? $tempProduct['procurement_type_id'] ?? $defaultProcurementTypeId ?? '') == $procurementType->id)>
                                    {{ $procurementType->value }}
                                </option>
                            @endforeach
                        </select>
                        @error('procurement_type_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="default_unit_id" class="form-label">Default Unit <span class="text-danger">*</span></label>
                        <div class="select-with-action @error('default_unit_id') is-invalid @enderror">
                            <div class="select-with-action__field">
                                <select id="default_unit_id" name="default_unit_id" class="select2 form-select @error('default_unit_id') is-invalid @enderror" data-allow-clear="true" required>
                                    <option value="">Select Unit</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}" {{ (old('default_unit_id') ?? $tempProduct['default_unit_id'] ?? '') == $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->symbol }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" class="btn btn-primary btn-add-inline" data-bs-toggle="modal" data-bs-target="#quickUnitModal" title="Add New Unit">
                                <i class="ti ti-plus me-1"></i>Add New
                            </button>
                        </div>
                        @error('default_unit_id') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="has_variants" class="form-label">Has Variants?</label>
                        <select id="has_variants" name="has_variants" class="select2 form-select" onchange="toggleVariantOptions()">
                            <option value="0" {{ (old('has_variants') ?? $tempProduct['has_variants'] ?? 0) == 0 ? 'selected' : '' }}>No - Single product</option>
                            <option value="1" {{ (old('has_variants') ?? $tempProduct['has_variants'] ?? 0) == 1 ? 'selected' : '' }}>Yes - Product with variants</option>
                        </select>
                    </div>

                    @php
                        $isStockItem = filter_var(old('is_stock_item', $tempProduct['is_stock_item'] ?? true), FILTER_VALIDATE_BOOLEAN);
                        $isSaleItem = filter_var(old('is_sale_item', $tempProduct['is_sale_item'] ?? false), FILTER_VALIDATE_BOOLEAN);
                        $isPurchaseItem = filter_var(old('is_purchase_item', $tempProduct['is_purchase_item'] ?? true), FILTER_VALIDATE_BOOLEAN);
                    @endphp
                    <div class="col-md-4">
                        <label for="is_stock_item" class="form-label">Stock Item</label>
                        <select id="is_stock_item" name="is_stock_item" class="select2 form-select">
                            <option value="1" {{ $isStockItem ? 'selected' : '' }}>Yes — track stock & HPP</option>
                            <option value="0" {{ ! $isStockItem ? 'selected' : '' }}>No — non-stock/service</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="is_sale_item" class="form-label">Item Sales</label>
                        <select id="is_sale_item" name="is_sale_item" class="select2 form-select">
                            <option value="0" {{ ! $isSaleItem ? 'selected' : '' }}>No — tidak tampil di POS</option>
                            <option value="1" {{ $isSaleItem ? 'selected' : '' }}>Yes — bisa dijual di POS</option>
                        </select>
                        <small class="text-muted">Produk dengan Item Sales aktif muncul di Point of Sales.</small>
                    </div>

                    <div class="col-md-4">
                        <label for="is_purchase_item" class="form-label">Purchase Item</label>
                        <select id="is_purchase_item" name="is_purchase_item" class="select2 form-select">
                            <option value="1" {{ $isPurchaseItem ? 'selected' : '' }}>Yes — bisa dibeli/PO</option>
                            <option value="0" {{ ! $isPurchaseItem ? 'selected' : '' }}>No — tidak untuk pembelian</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') ?? $tempProduct['description'] ?? '' }}</textarea>
                        @error('description') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="min_stock" class="form-label">Minimum Stock</label>
                        <input type="text" class="form-control @error('min_stock') is-invalid @enderror" id="min_stock" name="min_stock" value="{{ old('min_stock') ?? $tempProduct['min_stock'] ?? '' }}">
                        @error('min_stock') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="max_stock" class="form-label">Maximum Stock</label>
                        <input type="text" class="form-control @error('max_stock') is-invalid @enderror" id="max_stock" name="max_stock" value="{{ old('max_stock') ?? $tempProduct['max_stock'] ?? '' }}">
                        @error('max_stock') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="cogs_account_code" class="form-label">COGS Account Code</label>
                        <input type="text" class="form-control @error('cogs_account_code') is-invalid @enderror" id="cogs_account_code" name="cogs_account_code" value="{{ old('cogs_account_code') ?? $tempProduct['cogs_account_code'] ?? '' }}" placeholder="Optional">
                        @error('cogs_account_code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="revenue_account_code" class="form-label">Revenue Account Code</label>
                        <input type="text" class="form-control @error('revenue_account_code') is-invalid @enderror" id="revenue_account_code" name="revenue_account_code" value="{{ old('revenue_account_code') ?? $tempProduct['revenue_account_code'] ?? '' }}" placeholder="Optional">
                        @error('revenue_account_code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-12 mt-4 text-end">
                        <a href="{{ route('product.index.view') }}" class="btn btn-label-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Next: Unit Conversions</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Quick Add Category --}}
        <div class="modal fade" id="quickCategoryModal" tabindex="-1" aria-labelledby="quickCategoryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center gap-2" id="quickCategoryModalLabel">
                            <span class="avatar avatar-sm d-inline-flex align-items-center justify-content-center rounded bg-label-primary">
                                <i class="ti ti-category"></i>
                            </span>
                            <span>Add New Category</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">The new category will be available in the dropdown after saving.</p>
                        <div id="quickCategoryAlert" class="alert alert-danger d-none mb-3"></div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="quick_category_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                                <input type="text" id="quick_category_name" class="form-control" placeholder="e.g. Herbal">
                            </div>
                            <div class="col-md-6">
                                <label for="quick_category_code" class="form-label">Code</label>
                                <input type="text" id="quick_category_code" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label for="quick_category_sort_order" class="form-label">Sort Order</label>
                                <input type="number" id="quick_category_sort_order" class="form-control" value="0" min="0">
                            </div>
                            <div class="col-12">
                                <label for="quick_category_parent_id" class="form-label">Parent Category</label>
                                <select id="quick_category_parent_id" class="select2 form-select" data-allow-clear="true">
                                    <option value="">-- None --</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="quick_category_description" class="form-label">Description</label>
                                <textarea id="quick_category_description" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="btnSaveQuickCategory">
                            <i class="ti ti-device-floppy me-1"></i> Save
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Add Unit --}}
        <div class="modal fade" id="quickUnitModal" tabindex="-1" aria-labelledby="quickUnitModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center gap-2" id="quickUnitModalLabel">
                            <span class="avatar avatar-sm d-inline-flex align-items-center justify-content-center rounded bg-label-primary">
                                <i class="ti ti-ruler-measure"></i>
                            </span>
                            <span>Add New Unit</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">The new unit will be available in the dropdown after saving.</p>
                        <div id="quickUnitAlert" class="alert alert-danger d-none mb-3"></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="quick_unit_name" class="form-label">Unit Name <span class="text-danger">*</span></label>
                                <input type="text" id="quick_unit_name" class="form-control" placeholder="e.g. Kilogram">
                            </div>
                            <div class="col-md-6">
                                <label for="quick_unit_code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" id="quick_unit_code" class="form-control text-uppercase" placeholder="e.g. KG">
                                <div class="form-text">Auto-generated from name if left empty.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="quick_unit_symbol" class="form-label">Symbol</label>
                                <input type="text" id="quick_unit_symbol" class="form-control" placeholder="e.g. kg">
                            </div>
                            <div class="col-12">
                                <label for="quick_unit_description" class="form-label">Description</label>
                                <textarea id="quick_unit_description" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="btnSaveQuickUnit">
                            <i class="ti ti-device-floppy me-1"></i> Save
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- / Content -->

    @push('page-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            function toggleVariantOptions() {
                // This can be expanded if needed
            }

            function regenerateCode() {
                fetch('{{ route('product.generate.code') }}')
                    .then(response => response.json())
                    .then(data => {
                        if (data.code) {
                            document.getElementById('code').value = data.code;
                        }
                    })
                    .catch(error => {
                        console.error('Error regenerating code:', error);
                    });
            }

            function appendSelectOption(selectId, id, label) {
                const $select = $(selectId);
                if ($select.find('option[value="' + id + '"]').length === 0) {
                    $select.append(new Option(label, id, false, false));
                }
                $select.val(id).trigger('change');
            }

            function showQuickAlert(alertId, message) {
                const alertEl = document.getElementById(alertId);
                alertEl.textContent = message;
                alertEl.classList.remove('d-none');
            }

            function hideQuickAlert(alertId) {
                document.getElementById(alertId).classList.add('d-none');
            }

            function parseValidationErrors(payload) {
                if (payload.errors) {
                    return Object.values(payload.errors).flat().join(' ');
                }

                return payload.message || 'Failed to save data.';
            }

            document.getElementById('quickCategoryModal').addEventListener('shown.bs.modal', function () {
                $('#quick_category_parent_id').select2({
                    dropdownParent: $('#quickCategoryModal'),
                    allowClear: true,
                    width: '100%',
                });
            });

            document.getElementById('quickUnitModal').addEventListener('shown.bs.modal', function () {
                hideQuickAlert('quickUnitAlert');
            });

            document.getElementById('quickCategoryModal').addEventListener('show.bs.modal', function () {
                hideQuickAlert('quickCategoryAlert');
            });

            document.getElementById('quick_unit_name').addEventListener('blur', function () {
                const codeInput = document.getElementById('quick_unit_code');
                if (codeInput.value.trim() !== '') {
                    return;
                }

                codeInput.value = this.value
                    .trim()
                    .toUpperCase()
                    .replace(/[^A-Z0-9]+/g, '_')
                    .replace(/^_+|_+$/g, '')
                    .substring(0, 50);
            });

            document.getElementById('btnSaveQuickCategory').addEventListener('click', function () {
                const btn = this;
                btn.disabled = true;
                hideQuickAlert('quickCategoryAlert');

                fetch('{{ route('product.quick-category.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        name: document.getElementById('quick_category_name').value.trim(),
                        code: document.getElementById('quick_category_code').value.trim() || null,
                        sort_order: document.getElementById('quick_category_sort_order').value || 0,
                        parent_id: document.getElementById('quick_category_parent_id').value || null,
                        description: document.getElementById('quick_category_description').value.trim() || null,
                    }),
                })
                    .then(async (response) => {
                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(parseValidationErrors(data));
                        }

                        appendSelectOption('#category_id', data.data.id, data.data.label);
                        appendSelectOption('#quick_category_parent_id', data.data.id, data.data.label);

                        document.getElementById('quick_category_name').value = '';
                        document.getElementById('quick_category_code').value = '';
                        document.getElementById('quick_category_sort_order').value = '0';
                        $('#quick_category_parent_id').val('').trigger('change');
                        document.getElementById('quick_category_description').value = '';

                        bootstrap.Modal.getInstance(document.getElementById('quickCategoryModal')).hide();
                    })
                    .catch((error) => {
                        showQuickAlert('quickCategoryAlert', error.message);
                    })
                    .finally(() => {
                        btn.disabled = false;
                    });
            });

            document.getElementById('btnSaveQuickUnit').addEventListener('click', function () {
                const btn = this;
                btn.disabled = true;
                hideQuickAlert('quickUnitAlert');

                fetch('{{ route('product.quick-unit.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        name: document.getElementById('quick_unit_name').value.trim(),
                        code: document.getElementById('quick_unit_code').value.trim(),
                        symbol: document.getElementById('quick_unit_symbol').value.trim() || null,
                        description: document.getElementById('quick_unit_description').value.trim() || null,
                    }),
                })
                    .then(async (response) => {
                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(parseValidationErrors(data));
                        }

                        appendSelectOption('#default_unit_id', data.data.id, data.data.label);

                        document.getElementById('quick_unit_name').value = '';
                        document.getElementById('quick_unit_code').value = '';
                        document.getElementById('quick_unit_symbol').value = '';
                        document.getElementById('quick_unit_description').value = '';

                        bootstrap.Modal.getInstance(document.getElementById('quickUnitModal')).hide();
                    })
                    .catch((error) => {
                        showQuickAlert('quickUnitAlert', error.message);
                    })
                    .finally(() => {
                        btn.disabled = false;
                    });
            });
        </script>
    @endpush

</x-app-layout>
