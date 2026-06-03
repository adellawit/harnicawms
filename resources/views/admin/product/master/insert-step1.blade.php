<x-app-layout>

    @section('title', 'Add Product | ')

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <style>
            .breadcrumb-item a:hover {
                color: #212529 !important;
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
                        <select id="category_id" name="category_id" class="select2 form-select @error('category_id') is-invalid @enderror" data-allow-clear="true">
                            <option value="">Select Category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" {{ (old('category_id') ?? $tempProduct['category_id'] ?? '') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="default_unit_id" class="form-label">Default Unit <span class="text-danger">*</span></label>
                        <select id="default_unit_id" name="default_unit_id" class="select2 form-select @error('default_unit_id') is-invalid @enderror" data-allow-clear="true" required>
                            <option value="">Select Unit</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}" {{ (old('default_unit_id') ?? $tempProduct['default_unit_id'] ?? '') == $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->symbol }})</option>
                            @endforeach
                        </select>
                        @error('default_unit_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="has_variants" class="form-label">Has Variants?</label>
                        <select id="has_variants" name="has_variants" class="select2 form-select" onchange="toggleVariantOptions()">
                            <option value="0" {{ (old('has_variants') ?? $tempProduct['has_variants'] ?? 0) == 0 ? 'selected' : '' }}>No - Single product</option>
                            <option value="1" {{ (old('has_variants') ?? $tempProduct['has_variants'] ?? 0) == 1 ? 'selected' : '' }}>Yes - Product with variants</option>
                        </select>
                    </div>

                    @php
                        $isSaleItem = filter_var(old('is_sale_item', $tempProduct['is_sale_item'] ?? false), FILTER_VALIDATE_BOOLEAN);
                    @endphp
                    <div class="col-md-6">
                        <label for="is_sale_item" class="form-label">Item Sales</label>
                        <select id="is_sale_item" name="is_sale_item" class="select2 form-select">
                            <option value="0" {{ ! $isSaleItem ? 'selected' : '' }}>No — tidak tampil di POS</option>
                            <option value="1" {{ $isSaleItem ? 'selected' : '' }}>Yes — bisa dijual di POS</option>
                        </select>
                        <small class="text-muted">Produk dengan Item Sales aktif muncul di Point of Sales.</small>
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

                    <div class="col-12 mt-4 text-end">
                        <a href="{{ route('product.index.view') }}" class="btn btn-label-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Next: Unit Conversions</button>
                    </div>
                </form>
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
        </script>
    @endpush

</x-app-layout>
