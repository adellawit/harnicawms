<x-app-layout>
    @section('title', 'Add Tag | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Tag', 'url' => route('product.tag.index.view')],
                ['label' => 'Add', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <h5 class="card-header fw-bold">Add Product Tag</h5>
            <form method="POST" action="{{ route('product.tag.insert.data') }}" id="postForm">
                @csrf
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Best Seller" value="{{ old('name') }}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="code">Code</label>
                            <input type="text" id="code" name="code" class="form-control" placeholder="Optional" value="{{ old('code') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="type">Type <span class="text-danger">*</span></label>
                            <select id="type" name="type" class="form-select" required>
                                @foreach(\App\Models\ProductTag::typeOptions() as $k => $v)
                                    <option value="{{ $k }}" {{ old('type', 'general') == $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="color">Color</label>
                            <input type="text" id="color" name="color" class="form-control" placeholder="e.g. #ff0000" value="{{ old('color') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sort_order">Sort Order</label>
                            <input type="number" id="sort_order" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="2" placeholder="Description">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('product.tag.index.view') }}" class="btn btn-outline-dark me-2">Cancel</a>
        <button type="button" class="btn btn-primary" id="btn-submit">Save</button>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/block-ui/block-ui.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(function() {
                $('#btn-submit').click(function() { $('#postForm').submit(); });
                $('#postForm').submit(function() {
                    $(this).block({ message: '<div class="spinner-border text-primary"></div>', timeout: 1000, css: { backgroundColor: "transparent", border: 0 }, overlayCSS: { backgroundColor: "#fff", opacity: .8 } });
                });
            });
        </script>
    @endpush
</x-app-layout>
