<x-app-layout>

    @section('title', 'Add Product | ')

    @push('page-css')
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
                        <span class="badge bg-label-success rounded-circle me-2"><i class="ti ti-check"></i></span>
                        <span class="text-muted">Product Info</span>
                    </li>
                    <li class="list-group-item d-flex align-items-center border-end-0 pe-0 ps-2">
                        <span class="badge bg-label-success rounded-circle me-2"><i class="ti ti-check"></i></span>
                        <span class="text-muted">Unit Conversions</span>
                    </li>
                    <li class="list-group-item d-flex align-items-center ps-2">
                        <span class="badge bg-label-primary rounded-circle me-2">3</span>
                        <span class="fw-bold">Prices</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Step 3 Form - Prices (No Variants) -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Step 3: Product Prices</h5>
                <small class="text-muted">Set purchase and selling prices (product has no variants)</small>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="ti ti-info-circle me-1"></i>
                    This product has <strong>no variants</strong>. Prices are set for the base product only.
                </div>

                <form method="POST" action="{{ route('product.insert.data.step3') }}" class="row g-3">
                    @csrf

                    <!-- Product Summary -->
                    <div class="col-12 mb-4">
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <h6 class="mb-0">
                                    <strong>{{ $tempProduct['name'] ?? '' }}</strong>
                                    <span class="badge bg-label-secondary ms-2">{{ $selectedUnit->symbol ?? '' }}</span>
                                </h6>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="purchase_price" class="form-label">Purchase Price</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control" id="purchase_price" name="purchase_price" value="{{ old('purchase_price') ?? $tempProduct['purchase_price'] ?? '' }}" placeholder="0.00">
                        </div>
                        <div class="form-text">Price per {{ $selectedUnit->name ?? 'Unit' }}</div>
                    </div>

                    <div class="col-md-6">
                        <label for="selling_price" class="form-label">Selling Price</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control" id="selling_price" name="selling_price" value="{{ old('selling_price') ?? $tempProduct['selling_price'] ?? '' }}" placeholder="0.00">
                        </div>
                        <div class="form-text">Price per {{ $selectedUnit->name ?? 'Unit' }}</div>
                    </div>

                    <div class="col-12 mt-4">
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-circle me-1"></i>
                            <strong>Prices will be saved in the smallest unit.</strong><br>
                            If you have unit conversions, prices will be converted automatically.
                        </div>
                    </div>

                    <div class="col-12 mt-4 text-end">
                        <a href="{{ route('product.insert.view.step1') }}" class="btn btn-label-secondary">Back</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-save me-1"></i> Save Product
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
    <!-- / Content -->

</x-app-layout>
