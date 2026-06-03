<x-app-layout>
    @section('title', 'Add Bill of Material | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Bill of Material', 'url' => route('product.bom.index.view')],
                ['label' => 'Add', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <h5 class="card-header fw-bold">Add Bill of Material</h5>
            <form method="POST" action="{{ route('product.bom.insert.data') }}" id="postForm">
                @csrf
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="raw_material_id">Product (Product) <span class="text-danger">*</span></label>
                            <select id="raw_material_id" name="raw_material_id" class="select2 form-select" required>
                                <option value="">-- Select Product --</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" {{ old('raw_material_id') == $product->id ? 'selected' : '' }}>{{ $product->name }}{{ $product->code ? ' (' . $product->code . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="code">BOM Code</label>
                            <input type="text" id="code" name="code" class="form-control" value="{{ old('code') }}" placeholder="Optional" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="version">Version</label>
                            <input type="text" id="version" name="version" class="form-control" placeholder="1.0" value="{{ old('version', '1.0') }}" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('product.bom.index.view') }}" class="btn btn-outline-dark me-2">Cancel</a>
        <button type="button" class="btn btn-primary" id="btn-submit">Save</button>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>$(function() { $('#btn-submit').click(function() { $('#postForm').submit(); }); });</script>
    @endpush
</x-app-layout>
