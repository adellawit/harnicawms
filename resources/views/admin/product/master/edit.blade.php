<x-app-layout>
    @section('title', 'Edit Product | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Product', 'url' => route('product.index.view')],
                ['label' => 'Edit', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>
        @endif

        <div class="card">
            <h5 class="card-header fw-bold">Edit Product</h5>
            <form method="POST" action="{{ route('product.edit.data') }}" id="postForm">
                @csrf
                <input type="hidden" name="id" value="{{ $product->id }}" />
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="nature_id">Product Type</label>
                            <select id="nature_id" name="nature_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">-- Select --</option>
                                @foreach($natures as $nat)
                                    <option value="{{ $nat->id }}" {{ old('nature_id', $product->nature_id) == $nat->id ? 'selected' : '' }}>{{ $nat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="category_id">Category</label>
                            <select id="category_id" name="category_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">-- Select --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="item_type_id">Item Type</label>
                            <select id="item_type_id" name="item_type_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">-- Select --</option>
                                @foreach($itemTypes as $itemType)
                                    <option value="{{ $itemType->id }}" {{ old('item_type_id', $product->item_type_id) == $itemType->id ? 'selected' : '' }}>{{ $itemType->value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="product_nature_id">Inventory Type</label>
                            <select id="product_nature_id" name="product_nature_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">-- Select --</option>
                                @foreach($productNatures as $productNature)
                                    <option value="{{ $productNature->id }}" {{ old('product_nature_id', $product->product_nature_id) == $productNature->id ? 'selected' : '' }}>{{ $productNature->value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="procurement_type_id">Procurement Type</label>
                            <select id="procurement_type_id" name="procurement_type_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">-- Select --</option>
                                @foreach($procurementTypes as $procurementType)
                                    <option value="{{ $procurementType->id }}" {{ old('procurement_type_id', $product->procurement_type_id) == $procurementType->id ? 'selected' : '' }}>{{ $procurementType->value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="default_unit_id">Default Unit<span style="color: red">*</span></label>
                            <select id="default_unit_id" name="default_unit_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">-- Select --</option>
                                @foreach($units as $u)
                                    <option value="{{ $u->id }}" {{ old('default_unit_id', $product->default_unit_id) == $u->id ? 'selected' : '' }}>{{ $u->name }} {{ $u->symbol ? '('.$u->symbol.')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="code">Code</label>
                            <input type="text" id="code" name="code" class="form-control" value="{{ old('code', $product->code) }}" placeholder="Optional" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $product->name) }}" required />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">SKU</label>
                            <input type="text" class="form-control bg-light" value="{{ $product->sku ?? 'Auto-generated' }}" readonly disabled />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="is_stock_item">Stock Item</label>
                            <select id="is_stock_item" name="is_stock_item" class="select2 form-select">
                                <option value="1" {{ old('is_stock_item', $product->is_stock_item) ? 'selected' : '' }}>Yes — track stock & HPP</option>
                                <option value="0" {{ ! old('is_stock_item', $product->is_stock_item) ? 'selected' : '' }}>No — non-stock/service</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="is_sale_item">Sales Item</label>
                            <select id="is_sale_item" name="is_sale_item" class="select2 form-select">
                                <option value="1" {{ old('is_sale_item', $product->is_sale_item) ? 'selected' : '' }}>Yes — bisa dijual</option>
                                <option value="0" {{ ! old('is_sale_item', $product->is_sale_item) ? 'selected' : '' }}>No — tidak dijual</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="is_purchase_item">Purchase Item</label>
                            <select id="is_purchase_item" name="is_purchase_item" class="select2 form-select">
                                <option value="1" {{ old('is_purchase_item', $product->is_purchase_item) ? 'selected' : '' }}>Yes — bisa dibeli/PO</option>
                                <option value="0" {{ ! old('is_purchase_item', $product->is_purchase_item) ? 'selected' : '' }}>No — tidak dibeli</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="min_stock">Min Stock</label>
                            <input type="text" id="min_stock" name="min_stock" class="form-control number-format" value="{{ format_number(old('min_stock', $product->min_stock), 10, true) }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="max_stock">Max Stock</label>
                            <input type="text" id="max_stock" name="max_stock" class="form-control number-format" value="{{ format_number(old('max_stock', $product->max_stock), 10, true) }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cogs_account_code">COGS Account Code</label>
                            <input type="text" id="cogs_account_code" name="cogs_account_code" class="form-control" value="{{ old('cogs_account_code', $product->cogs_account_code) }}" placeholder="Optional" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="revenue_account_code">Revenue Account Code</label>
                            <input type="text" id="revenue_account_code" name="revenue_account_code" class="form-control" value="{{ old('revenue_account_code', $product->revenue_account_code) }}" placeholder="Optional" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="2">{{ old('description', $product->description) }}</textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="card mt-3">
            <h5 class="card-header fw-bold d-flex justify-content-between align-items-center">
                Unit Conversions
                <x-badge color="secondary">e.g. 1 Dus = 24 Botol</x-badge>
            </h5>
            <div class="card-body">
                @php $conversions = $product->unitConversions ?? collect(); @endphp
                @if ($conversions->isNotEmpty())
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>From Unit</th>
                                    <th>To Unit</th>
                                    <th>Factor</th>
                                    <th style="width:120px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($conversions as $conv)
                                    <tr>
                                        <td>1 {{ $conv->fromUnit?->name ?? '-' }} {{ $conv->fromUnit?->symbol ? '('.$conv->fromUnit->symbol.')' : '' }}</td>
                                        <td>= {{ format_number($conv->conversion_factor, 10, true) }} {{ $conv->toUnit?->name ?? '-' }} {{ $conv->toUnit?->symbol ? '('.$conv->toUnit->symbol.')' : '' }}</td>
                                        <td>{{ format_number($conv->conversion_factor, 10, true) }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-icon btn-outline-warning btn-edit-conv"
                                                data-id="{{ $conv->id }}"
                                                data-from="{{ $conv->from_unit_id }}"
                                                data-to="{{ $conv->to_unit_id }}"
                                                data-factor="{{ format_number($conv->conversion_factor, 10, true) }}">
                                                <i class="ti ti-edit"></i>
                                            </button>
                                            <form method="POST" action="{{ route('product.delete-conversion') }}" class="d-inline" onsubmit="return confirm('Delete this conversion?');">
                                                @csrf
                                                <input type="hidden" name="conversion_id" value="{{ $conv->id }}" />
                                                <input type="hidden" name="raw_material_id" value="{{ $product->id }}" />
                                                <button type="submit" class="btn btn-sm btn-icon btn-outline-danger"><i class="ti ti-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-3">No unit conversions defined. Add conversions below (e.g. 1 Dus = 24 Botol).</p>
                @endif

                <form method="POST" action="{{ route('product.add-conversion') }}" class="row g-2 align-items-end">
                    @csrf
                    <input type="hidden" name="raw_material_id" value="{{ $product->id }}" />
                    <div class="col-md-3">
                        <label class="form-label">From Unit</label>
                        <select name="from_unit_id" class="select2 form-select" required>
                            <option value="">-- Select --</option>
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} {{ $u->symbol ? '('.$u->symbol.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Unit</label>
                        <select name="to_unit_id" class="select2 form-select" required>
                            <option value="">-- Select --</option>
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} {{ $u->symbol ? '('.$u->symbol.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Factor <small class="text-muted">(1 from = factor × to)</small></label>
                        <input type="text" name="conversion_factor" class="form-control number-format" placeholder="24" required />
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Add Conversion</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <h5 class="card-header fw-bold d-flex justify-content-between align-items-center">
                Product Variants
                <a href="{{ route('product.variants.view', $product->id) }}" class="btn btn-sm btn-primary">
                    <i class="ti ti-list me-1"></i> Manage Variants
                </a>
            </h5>
            <div class="card-body">
                @php $variants = $product->variants ?? collect(); @endphp
                @if ($variants->isNotEmpty())
                    <p class="mb-2">{{ $variants->count() }} variant(s) defined.</p>
                    <a href="{{ route('product.variants.view', $product->id) }}" class="btn btn-sm btn-outline-primary">View Variants</a>
                @else
                    <p class="text-muted mb-0">No variants. Use attributes (Size, Color) to define variants, then add them here.</p>
                    <a href="{{ route('product.variants.view', $product->id) }}" class="btn btn-sm btn-outline-primary mt-2">Manage Variants</a>
                @endif
            </div>
        </div>
    </div>

    {{-- Edit Conversion Modal --}}
    <div class="modal fade" id="editConversionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('product.edit-conversion') }}" id="editConversionForm">
                    @csrf
                    <input type="hidden" name="conversion_id" id="edit_conv_id" />
                    <input type="hidden" name="raw_material_id" value="{{ $product->id }}" />
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Unit Conversion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">From Unit <span class="text-danger">*</span></label>
                            <select name="from_unit_id" id="edit_from_unit" class="form-select" required>
                                <option value="">-- Select --</option>
                                @foreach ($units as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} {{ $u->symbol ? '('.$u->symbol.')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">To Unit <span class="text-danger">*</span></label>
                            <select name="to_unit_id" id="edit_to_unit" class="form-select" required>
                                <option value="">-- Select --</option>
                                @foreach ($units as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} {{ $u->symbol ? '('.$u->symbol.')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Factor <span class="text-danger">*</span> <small class="text-muted">(1 from = factor &times; to)</small></label>
                            <input type="text" name="conversion_factor" id="edit_conv_factor" class="form-control number-format" required />
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

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('product.index.view') }}" class="btn btn-outline-dark me-2">Cancel</a>
        <button type="button" class="btn btn-primary" id="btn-submit">Save</button>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
        $(function() {
            $('#btn-submit').click(function() { $('#postForm').submit(); });

            $('.btn-edit-conv').click(function() {
                var btn = $(this);
                $('#edit_conv_id').val(btn.data('id'));
                $('#edit_from_unit').val(btn.data('from'));
                $('#edit_to_unit').val(btn.data('to'));
                var factorInput = $('#edit_conv_factor');
                factorInput.val(btn.data('factor'));
                factorInput.trigger('input');
                var modal = new bootstrap.Modal('#editConversionModal');
                modal.show();
            });
        });
        </script>
    @endpush
</x-app-layout>
