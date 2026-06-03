<x-app-layout>
    @section('title', 'Add Customer | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 100px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Customer'],
                ['label' => 'List', 'url' => route('customer.list.index')],
                ['label' => 'Add', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('customer.list.insert.data') }}" id="postForm" enctype="multipart/form-data">
            @csrf
            <div class="card">
                <h5 class="card-header fw-bold">Add Customer</h5>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="customer_group_id">Customer Group <span class="text-danger">*</span></label>
                            <select id="customer_group_id" name="customer_group_id" class="select2 form-select" required>
                                <option value="">-- Select Group --</option>
                                @foreach($customerGroups as $g)
                                    <option value="{{ $g->id }}" {{ old('customer_group_id') == $g->id ? 'selected' : '' }}>
                                        {{ $g->name }} ({{ $g->branch->name ?? '-' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
                            <input type="text" id="code" name="code" class="form-control" placeholder="e.g. CST001" value="{{ old('code') }}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="Legal name or company name" value="{{ old('name') }}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_type">Type</label>
                            <select id="customer_type" name="customer_type" class="select2 form-select">
                                <option value="">-- Select --</option>
                                <option value="individual" {{ old('customer_type') == 'individual' ? 'selected' : '' }}>Individual</option>
                                <option value="company" {{ old('customer_type') == 'company' ? 'selected' : '' }}>Company</option>
                            </select>
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Tax Information</h6></div>
                        <div class="col-md-6">
                            <label class="form-label" for="tax_number">Tax Number (NPWP)</label>
                            <input type="text" id="tax_number" name="tax_number" class="form-control" placeholder="00.000.000.0-000.000" value="{{ old('tax_number') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="tax_name">Tax Name</label>
                            <input type="text" id="tax_name" name="tax_name" class="form-control" placeholder="Name for tax invoice" value="{{ old('tax_name') }}" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="tax_address">Tax Address</label>
                            <textarea id="tax_address" name="tax_address" class="form-control" rows="2" placeholder="Address as registered in NPWP">{{ old('tax_address') }}</textarea>
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Contact</h6></div>
                        <div class="col-md-6">
                            <label class="form-label" for="contact_person">Contact Person</label>
                            <input type="text" id="contact_person" name="contact_person" class="form-control" placeholder="Person in charge" value="{{ old('contact_person') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="email@example.com" value="{{ old('email') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control" placeholder="+62 21 1234567" value="{{ old('phone') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mobile">Mobile</label>
                            <input type="text" id="mobile" name="mobile" class="form-control" placeholder="0812 3456 7890" value="{{ old('mobile') }}" />
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Address</h6></div>
                        <div class="col-12">
                            <label class="form-label" for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="2" placeholder="Street, building, floor">{{ old('address') }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="city">City</label>
                            <input type="text" id="city" name="city" class="form-control" placeholder="e.g. Jakarta Selatan" value="{{ old('city') }}" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="province">Province</label>
                            <input type="text" id="province" name="province" class="form-control" placeholder="e.g. DKI Jakarta" value="{{ old('province') }}" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="postal_code">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-control" placeholder="12345" value="{{ old('postal_code') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="country">Country</label>
                            <input type="text" id="country" name="country" class="form-control" placeholder="Indonesia" value="{{ old('country', 'Indonesia') }}" />
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Location (Coordinates)</h6></div>
                        <div class="col-md-6">
                            <label class="form-label" for="lat">Latitude</label>
                            <input type="number" id="lat" name="lat" class="form-control" step="0.00000001" placeholder="-6.2088" value="{{ old('lat') }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="long">Longitude</label>
                            <input type="number" id="long" name="long" class="form-control" step="0.00000001" placeholder="106.8456" value="{{ old('long') }}" />
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Identity</h6></div>
                        <div class="col-md-6">
                            <label class="form-label" for="identity_type">Identity Type</label>
                            <select id="identity_type" name="identity_type" class="select2 form-select">
                                <option value="">-- Select --</option>
                                <option value="KTP" {{ old('identity_type') == 'KTP' ? 'selected' : '' }}>KTP</option>
                                <option value="Passport" {{ old('identity_type') == 'Passport' ? 'selected' : '' }}>Passport</option>
                                <option value="KITAS" {{ old('identity_type') == 'KITAS' ? 'selected' : '' }}>KITAS</option>
                                <option value="SIM" {{ old('identity_type') == 'SIM' ? 'selected' : '' }}>SIM</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="identity_number">Identity Number</label>
                            <input type="text" id="identity_number" name="identity_number" class="form-control" placeholder="e.g. 3174012345678901" value="{{ old('identity_number') }}" />
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Attachments (Supporting Documents)</h6></div>
                        <div class="col-12">
                            <label class="form-label">Upload Files</label>
                            <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png" />
                            <small class="text-muted">Max 5MB per file. PDF, JPG, PNG.</small>
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Customer App (Future)</h6></div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Has App Access</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="has_app_access" id="has_app_0" value="0" {{ old('has_app_access', '0') == '0' ? 'checked' : '' }}>
                                <label class="form-check-label" for="has_app_0">No</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="has_app_access" id="has_app_1" value="1" {{ old('has_app_access') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="has_app_1">Yes</label>
                            </div>
                        </div>
                        <div class="col-md-6" id="app_creds_wrap" style="{{ old('has_app_access') == '1' ? '' : 'display:none' }}">
                            <label class="form-label" for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" placeholder="Login username" value="{{ old('username') }}" />
                        </div>
                        <div class="col-md-6" id="password_wrap" style="{{ old('has_app_access') == '1' ? '' : 'display:none' }}">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Min. 6 characters" />
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="notes">Notes</label>
                            <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Additional notes">{{ old('notes') }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} />
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('customer.list.index') }}" class="btn btn-outline-dark me-2">Cancel</a>
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
                $('input[name="has_app_access"]').change(function() {
                    var show = $(this).val() == '1';
                    $('#app_creds_wrap, #password_wrap').toggle(show);
                });
                $('#btn-submit').click(function() { $('#postForm').submit(); });
                $('#postForm').on('submit', function() {
                    $(this).block({ message: '<div class="spinner-border text-primary"></div>', timeout: 1000, css: { backgroundColor: "transparent", border: 0 }, overlayCSS: { backgroundColor: "#fff", opacity: .8 } });
                });
            });
        </script>
    @endpush
</x-app-layout>
