<x-app-layout>
    @section('title', 'Add Customer Group | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Customer'],
                ['label' => 'Group', 'url' => route('customer.group.index')],
                ['label' => 'Add', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <h5 class="card-header fw-bold">Add Customer Group</h5>
            <form method="POST" action="{{ route('customer.group.insert.data') }}" id="postForm">
                @csrf
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="branch_id">Branch <span class="text-danger">*</span></label>
                            <select id="branch_id" name="branch_id" class="select2 form-select" required>
                                <option value="">-- Select Branch --</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" {{ old('branch_id', $branchId) == $b->id ? 'selected' : '' }}>{{ $b->name }} ({{ $b->code }})</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Customer group is specific per branch</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="code">Code</label>
                            <input type="text" id="code" name="code" class="form-control" placeholder="e.g. RGTL, GRIR, VIP" value="{{ old('code') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Retail, Wholesale, VIP Member" value="{{ old('name') }}" required />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="2" placeholder="Brief description of this customer group">{{ old('description') }}</textarea>
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Price & Discount</h6></div>
                        <div class="col-md-6">
                            <label class="form-label" for="price_list_id">Price List (Price Level)</label>
                            <select id="price_list_id" name="price_list_id" class="select2 form-select" data-allow-clear="true">
                                <option value="">-- Default --</option>
                                @foreach($priceLists as $pl)
                                    <option value="{{ $pl->id }}" {{ old('price_list_id') == $pl->id ? 'selected' : '' }}>{{ $pl->name }} ({{ $pl->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="default_discount">Default Discount (%)</label>
                            <input type="number" id="default_discount" name="default_discount" class="form-control" placeholder="e.g. 5 or 10" step="0.01" min="0" max="100" value="{{ old('default_discount', 0) }}" />
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Credit & Payment</h6></div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Allow Credit</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="allow_credit" id="allow_credit_0" value="0" {{ old('allow_credit', '0') == '0' ? 'checked' : '' }}>
                                <label class="form-check-label" for="allow_credit_0">No</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="allow_credit" id="allow_credit_1" value="1" {{ old('allow_credit') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="allow_credit_1">Yes</label>
                            </div>
                        </div>
                        <div class="col-md-6" id="credit_limit_wrap" style="{{ old('allow_credit') == '1' ? '' : 'display:none' }}">
                            <label class="form-label" for="credit_limit">Credit Limit</label>
                            <input type="number" id="credit_limit" name="credit_limit" class="form-control" placeholder="e.g. 10000000" min="0" step="1" value="{{ old('credit_limit') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="payment_term_days">Payment Term (days)</label>
                            <input type="number" id="payment_term_days" name="payment_term_days" class="form-control" placeholder="e.g. 7, 14, 30" min="0" value="{{ old('payment_term_days', 0) }}" />
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Loyalty / Points</h6></div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Earn Points</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="earn_point" id="earn_point_0" value="0" {{ old('earn_point', '0') == '0' ? 'checked' : '' }}>
                                <label class="form-check-label" for="earn_point_0">No</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="earn_point" id="earn_point_1" value="1" {{ old('earn_point') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="earn_point_1">Yes</label>
                            </div>
                        </div>
                        <div class="col-md-6" id="point_multiplier_wrap" style="{{ old('earn_point') == '1' ? '' : 'display:none' }}">
                            <label class="form-label" for="point_multiplier">Point Multiplier</label>
                            <input type="number" id="point_multiplier" name="point_multiplier" class="form-control" placeholder="e.g. 1, 1.5, 2" step="0.01" min="0" value="{{ old('point_multiplier', 1) }}" />
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="sort_order">Sort Order</label>
                            <input type="number" id="sort_order" name="sort_order" class="form-control" placeholder="Display order (0, 1, 2...)" min="0" value="{{ old('sort_order', 0) }}" />
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} />
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('customer.group.index') }}" class="btn btn-outline-dark me-2">Cancel</a>
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
                $('input[name="allow_credit"]').change(function() {
                    $('#credit_limit_wrap').toggle($(this).val() == '1');
                });
                $('input[name="earn_point"]').change(function() {
                    $('#point_multiplier_wrap').toggle($(this).val() == '1');
                });
                $('#btn-submit').click(function() { $('#postForm').submit(); });
                $('#postForm').on('submit', function() {
                    $(this).block({ message: '<div class="spinner-border text-primary"></div>', timeout: 1000, css: { backgroundColor: "transparent", border: 0 }, overlayCSS: { backgroundColor: "#fff", opacity: .8 } });
                });
            });
        </script>
    @endpush
</x-app-layout>
