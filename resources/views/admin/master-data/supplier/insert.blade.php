<x-app-layout>
    @section('title', 'Add Supplier | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Master Data'],
                ['label' => 'Supplier', 'url' => route('product.supplier.index.view')],
                ['label' => 'Add', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <h5 class="card-header fw-bold">Add Supplier</h5>
            <form method="POST" action="{{ route('product.supplier.insert.data') }}" id="postForm">
                @csrf
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="code">Code</label>
                            <input type="text" id="code" class="form-control bg-light" value="Auto-generated on save" readonly disabled />
                            <small class="text-muted">Supplier code will be generated automatically when you save.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="Supplier name" value="{{ old('name') }}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="supplier_type_id">Type</label>
                            <select id="supplier_type_id" name="supplier_type_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">-- None --</option>
                                @foreach($supplierTypes as $type)
                                    <option value="{{ $type->id }}" {{ old('supplier_type_id', $defaultSupplierTypeId ?? '') == $type->id ? 'selected' : '' }}>{{ $type->value ?? $type->key }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contact">Contact Person</label>
                            <input type="text" id="contact" name="contact" class="form-control" placeholder="Contact person" value="{{ old('contact') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control" placeholder="Phone" value="{{ old('phone') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Email" value="{{ old('email') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Tax (PPN)</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="is_ppn" id="is_ppn_0" value="0" {{ old('is_ppn', '0') == '0' ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_ppn_0">NON PPN</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="is_ppn" id="is_ppn_1" value="1" {{ old('is_ppn') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_ppn_1">PPN</label>
                            </div>
                        </div>
                        <div class="col-md-6" id="ppn_rate_wrap" style="{{ old('is_ppn') == '1' ? '' : 'display:none' }}">
                            <label class="form-label" for="ppn_rate">PPN Rate (%) <span class="text-muted">jika PPN</span></label>
                            <input type="number" id="ppn_rate" name="ppn_rate" class="form-control" placeholder="11" step="0.01" min="0" max="100" value="{{ old('ppn_rate', '11') }}" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="2" placeholder="Address">{{ old('address') }}</textarea>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('product.supplier.index.view') }}" class="btn btn-outline-dark me-2">Cancel</a>
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
                $('input[name="is_ppn"]').change(function() {
                    $('#ppn_rate_wrap').toggle($(this).val() == '1');
                });
                $('#btn-submit').click(function() {
                    $('#postForm').submit();
                });
                $('#postForm').on('submit', function() {
                    $(this).block({ message: '<div class="spinner-border text-primary"></div>', timeout: 1000, css: { backgroundColor: "transparent", border: 0 }, overlayCSS: { backgroundColor: "#fff", opacity: .8 } });
                });
            });
        </script>
    @endpush
</x-app-layout>
