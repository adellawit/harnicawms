<x-app-layout>
    @section('title', 'Edit Product Type | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Product Type', 'url' => route('product.nature.index.view')],
                ['label' => 'Edit', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <h5 class="card-header fw-bold">Edit Product Type</h5>
            <form method="POST" action="{{ route('product.nature.edit.data') }}" id="postForm">
                @csrf
                <input type="hidden" name="id" value="{{ $nature->id }}" />
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="parent_id">Parent Product Type</label>
                            <select id="parent_id" name="parent_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">-- None --</option>
                                @foreach($natures as $nat)
                                    <option value="{{ $nat->id }}" {{ old('parent_id', $nature->parent_id) == $nat->id ? 'selected' : '' }}>{{ $nat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
                            <input type="text" id="code" name="code" class="form-control" value="{{ old('code', $nature->code) }}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $nature->name) }}" required />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="2">{{ old('description', $nature->description) }}</textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('product.nature.index.view') }}" class="btn btn-outline-dark me-2">Cancel</a>
        <button type="button" class="btn btn-primary" id="btn-submit">Save</button>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/block-ui/block-ui.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
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
