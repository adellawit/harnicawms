<x-app-layout>
    @section('title', 'Add Product | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => 'javascript:void(0);'],
                ['label' => 'Product', 'url' => route('product.index.view')],
                ['label' => 'Add', 'active' => true]
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <x-card title="Add Product">
            <form method="POST" action="{{ route('product.insert.data') }}" id="postForm">
                @csrf
                <hr style="margin: 0.5rem 0;" />
                <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="nature_id">Product Type</label>
                            <select id="nature_id" name="nature_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">-- Select --</option>
                                @foreach($natures as $nat)
                                    <option value="{{ $nat->id }}" {{ old('nature_id') == $nat->id ? 'selected' : '' }}>{{ $nat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="category_id">Category</label>
                            <select id="category_id" name="category_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">-- Select --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="default_unit_id">Default Unit<span style="color: red">*</span></label>
                            <select id="default_unit_id" name="default_unit_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">-- Select --</option>
                                @foreach($units as $u)
                                    <option value="{{ $u->id }}" {{ old('default_unit_id') == $u->id ? 'selected' : '' }}>{{ $u->name }} {{ $u->symbol ? '('.$u->symbol.')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="code">Code</label>
                            <input type="text" id="code" name="code" class="form-control" value="{{ old('code') }}" placeholder="Optional" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">SKU</label>
                            <input type="text" class="form-control bg-light" value="Auto-generated (DDMMYYTH + unique number)" readonly disabled />
                            <small class="text-muted">SKU will be generated automatically on save</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="min_stock">Min Stock</label>
                            <input type="text" id="min_stock" name="min_stock" class="form-control number-format" value="{{ format_number(old('min_stock', 0), 10, true) }}" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="max_stock">Max Stock</label>
                            <input type="text" id="max_stock" name="max_stock" class="form-control number-format" value="{{ format_number(old('max_stock'), 10, true) }}" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="purchase_price">Harga Beli <small class="text-muted" id="purchase_price_unit_label">/ Satuan Besar</small></label>
                            <input type="text" id="purchase_price" name="purchase_price" class="form-control number-format" value="{{ format_number(old('purchase_price', 0), 2, true) }}" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="selling_price">Harga Jual <small class="text-muted" id="selling_price_unit_label">/ Satuan Besar</small></label>
                            <input type="text" id="selling_price" name="selling_price" class="form-control number-format" value="{{ format_number(old('selling_price'), 2, true) }}" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                        </div>
                    </div>
            </form>
        </x-card>
    </div>

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('product.index.view') }}" class="btn btn-outline-dark me-2">Cancel</a>
        <x-button color="primary" id="btn-submit">Save</x-button>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
        $(function() {
            $('#btn-submit').click(function() { $('#postForm').submit(); });

            function updatePriceUnitLabel() {
                var selected = $('#default_unit_id option:selected');
                var unitName = selected.length && selected.val() ? selected.text().trim() : 'Satuan Besar';
                $('#purchase_price_unit_label').text('/ ' + unitName);
                $('#selling_price_unit_label').text('/ ' + unitName);
            }
            $('#default_unit_id').on('change', updatePriceUnitLabel);
            updatePriceUnitLabel();
        });
        </script>
    @endpush
</x-app-layout>
