<x-app-layout>
    @section('title', 'Edit Bill of Material | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Bill of Material', 'url' => route('product.bom.index.view')],
                ['label' => 'Edit', 'active' => true],
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

        <div class="card mb-4">
            <h5 class="card-header fw-bold">Data Bill of Material</h5>
            <form method="POST" action="{{ route('product.bom.edit.data') }}" id="postForm">
                @csrf
                <input type="hidden" name="id" value="{{ $bom->id }}" />
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="product_id">Product <span class="text-danger">*</span></label>
                            <select id="product_id" name="product_id" class="select2 form-select" required>
                                <option value="">-- Select Product --</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" {{ old('product_id', $bom->variant_id) == $product->id ? 'selected' : '' }}>{{ $product->name }}{{ $product->code ? ' (' . $product->code . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="code">BOM Code</label>
                            <input type="text" id="code" name="code" class="form-control" value="{{ old('code', $bom->code) }}" placeholder="Optional" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="version">Version</label>
                            <input type="text" id="version" name="version" class="form-control" value="{{ old('version', $bom->version) }}" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="2">{{ old('description', $bom->description) }}</textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        @php
            $hasUpdatePermission = session('permissions.Bill of Material.is_update', false) == 1;
        @endphp

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
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                @if($hasUpdatePermission)<th style="width:120px">Action</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bom->items as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $item->product?->name ?? '-' }}{{ $item->product?->code ? ' (' . $item->product->code . ')' : '' }}</td>
                                <td>{{ format_number($item->quantity, 10, true) }}</td>
                                <td>{{ $item->unit?->symbol ?? $item->unit?->name ?? '-' }}</td>
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
                                <td colspan="{{ $hasUpdatePermission ? 5 : 4 }}" class="text-center text-muted">No items yet. Click "Add Item" to add recipe ingredients.</td>
                            </tr>
                            @endforelse
                        </tbody>
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
                            <label class="form-label" for="add_product_id">Product <span class="text-danger">*</span></label>
                            <select id="add_product_id" name="product_id" class="select2 form-select" required>
                                <option value="">-- Select --</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}{{ $product->code ? ' (' . $product->code . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="add_unit_id">Unit <span class="text-danger">*</span></label>
                            <select id="add_unit_id" name="unit_id" class="select2 form-select" required>
                                <option value="">-- Select --</option>
                                @foreach($units as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} {{ $u->symbol ? '('.$u->symbol.')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="add_quantity">Quantity <span class="text-danger">*</span></label>
                            <input type="text" id="add_quantity" name="quantity" class="form-control number-format" required />
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
                <form method="POST" action="{{ route('product.bom.edit-item') }}" id="editItemForm">
                    @csrf
                    <input type="hidden" name="item_id" id="edit_item_id" />
                    <input type="hidden" name="bill_of_material_id" value="{{ $bom->id }}" />
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Recipe Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product <span class="text-danger">*</span></label>
                            <select name="product_id" id="edit_item_product" class="form-select" required>
                                <option value="">-- Select --</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}{{ $product->code ? ' (' . $product->code . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <select name="unit_id" id="edit_item_unit" class="form-select" required>
                                <option value="">-- Select --</option>
                                @foreach($units as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} {{ $u->symbol ? '('.$u->symbol.')' : '' }}</option>
                                @endforeach
                            </select>
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
        <a href="{{ route('product.bom.index.view') }}" class="btn btn-outline-dark me-2">Back</a>
        <button type="button" class="btn btn-primary" id="btn-submit">Save Header</button>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(function() {
                $('#btn-submit').click(function() { $('#postForm').submit(); });

                @if($hasUpdatePermission)
                $('#addItemModal').on('shown.bs.modal', function() {
                    $('.select2', this).each(function() { if (!$(this).hasClass('select2-hidden-accessible')) $(this).select2({ dropdownParent: $('#addItemModal') }); });
                });

                $('.btn-edit-item').click(function() {
                    var btn = $(this);
                    $('#edit_item_id').val(btn.data('id'));
                    $('#edit_item_product').val(btn.data('product-id'));
                    $('#edit_item_unit').val(btn.data('unit'));
                    var qtyInput = $('#edit_item_qty');
                    qtyInput.val(btn.data('qty'));
                    qtyInput.trigger('input');
                    var modal = new bootstrap.Modal('#editItemModal');
                    modal.show();
                });
                @endif
            });
        </script>
    @endpush
</x-app-layout>
