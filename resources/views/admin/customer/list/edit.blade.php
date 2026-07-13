<x-app-layout>
    @section('title', 'Edit Customer | ')
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
                ['label' => 'Edit', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        @if ($customer->agent || $customer->reseller || $customer->latestPartnerApplication)
            <div class="card mb-3 border-start border-3 border-primary">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h6 class="mb-1"><i class="ti ti-affiliate me-1"></i> Network</h6>
                            <p class="mb-0 text-muted small">
                                Customer ini terhubung ke modul Network (partner).
                            </p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($customer->agent)
                                <a href="{{ route('partner.agents.show', $customer->agent->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="ti ti-users me-1"></i> Agent {{ $customer->agent->code }}
                                </a>
                            @endif
                            @if ($customer->reseller)
                                <a href="{{ route('partner.resellers.show', $customer->reseller->id) }}" class="btn btn-sm btn-outline-info">
                                    <i class="ti ti-user-star me-1"></i> Reseller {{ $customer->reseller->code }}
                                </a>
                                @if ($customer->reseller->agent)
                                    <span class="badge bg-label-secondary align-self-center">
                                        Agent: {{ $customer->reseller->agent->name }}
                                    </span>
                                @endif
                            @endif
                            @if ($customer->latestPartnerApplication && ! $customer->agent && ! $customer->reseller)
                                <a href="{{ route('partner.applications.show', $customer->latestPartnerApplication->id) }}" class="btn btn-sm btn-outline-warning">
                                    <i class="ti ti-clipboard-list me-1"></i> Application {{ $customer->latestPartnerApplication->application_number }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('customer.list.edit.data') }}" id="postForm" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="id" value="{{ $customer->id }}" />
            <div class="card">
                <h5 class="card-header fw-bold">Edit Customer</h5>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="customer_group_id">Customer Group <span class="text-danger">*</span></label>
                            <select id="customer_group_id" name="customer_group_id" class="select2 form-select" required>
                                @foreach($customerGroups as $g)
                                    <option value="{{ $g->id }}" {{ old('customer_group_id', $customer->customer_group_id) == $g->id ? 'selected' : '' }}>
                                        {{ $g->name }} ({{ $g->branch->name ?? '-' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
                            <input type="text" id="code" name="code" class="form-control" placeholder="e.g. CST001" value="{{ old('code', $customer->code) }}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="Legal name or company name" value="{{ old('name', $customer->name) }}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_type">Type</label>
                            @if ($customer->agent || $customer->reseller || in_array($customer->customer_type, [\App\Models\Customer::TYPE_PARTNER_LEAD, \App\Models\Customer::TYPE_AGENT, \App\Models\Customer::TYPE_RESELLER], true))
                                <input type="text" class="form-control" value="{{ $customer->customerTypeLabel() }}" readonly />
                                <input type="hidden" name="customer_type" value="{{ $customer->customer_type }}" />
                                <small class="text-muted">Tipe partner dikelola otomatis dari Network.</small>
                            @else
                                <select id="customer_type" name="customer_type" class="select2 form-select">
                                    <option value="">-- Select --</option>
                                    <option value="individual" {{ old('customer_type', $customer->customer_type) == 'individual' ? 'selected' : '' }}>Individual</option>
                                    <option value="company" {{ old('customer_type', $customer->customer_type) == 'company' ? 'selected' : '' }}>Company</option>
                                </select>
                            @endif
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Tax Information</h6></div>
                        <div class="col-md-6">
                            <label class="form-label" for="tax_number">Tax Number (NPWP)</label>
                            <input type="text" id="tax_number" name="tax_number" class="form-control" placeholder="00.000.000.0-000.000" value="{{ old('tax_number', $customer->tax_number) }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="tax_name">Tax Name</label>
                            <input type="text" id="tax_name" name="tax_name" class="form-control" placeholder="Name for tax invoice" value="{{ old('tax_name', $customer->tax_name) }}" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="tax_address">Tax Address</label>
                            <textarea id="tax_address" name="tax_address" class="form-control" rows="2" placeholder="Address as registered in NPWP">{{ old('tax_address', $customer->tax_address) }}</textarea>
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Contact</h6></div>
                        <div class="col-md-6">
                            <label class="form-label" for="contact_person">Contact Person</label>
                            <input type="text" id="contact_person" name="contact_person" class="form-control" placeholder="Person in charge" value="{{ old('contact_person', $customer->contact_person) }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="email@example.com" value="{{ old('email', $customer->email) }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control" placeholder="+62 21 1234567" value="{{ old('phone', $customer->phone) }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mobile">Mobile</label>
                            <input type="text" id="mobile" name="mobile" class="form-control" placeholder="0812 3456 7890" value="{{ old('mobile', $customer->mobile) }}" />
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Address</h6></div>
                        <div class="col-12">
                            <label class="form-label" for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="2" placeholder="Street, building, floor">{{ old('address', $customer->address) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="city">City</label>
                            <input type="text" id="city" name="city" class="form-control" placeholder="e.g. Jakarta Selatan" value="{{ old('city', $customer->city) }}" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="province">Province</label>
                            <input type="text" id="province" name="province" class="form-control" placeholder="e.g. DKI Jakarta" value="{{ old('province', $customer->province) }}" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="postal_code">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-control" placeholder="12345" value="{{ old('postal_code', $customer->postal_code) }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="country">Country</label>
                            <input type="text" id="country" name="country" class="form-control" placeholder="Indonesia" value="{{ old('country', $customer->country ?? 'Indonesia') }}" />
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Location (Coordinates)</h6></div>
                        <div class="col-md-6">
                            <label class="form-label" for="lat">Latitude</label>
                            <input type="number" id="lat" name="lat" class="form-control" step="0.00000001" placeholder="-6.2088" value="{{ old('lat', $customer->lat) }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="long">Longitude</label>
                            <input type="number" id="long" name="long" class="form-control" step="0.00000001" placeholder="106.8456" value="{{ old('long', $customer->long) }}" />
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Identity</h6></div>
                        <div class="col-md-6">
                            <label class="form-label" for="identity_type">Identity Type</label>
                            <select id="identity_type" name="identity_type" class="select2 form-select">
                                <option value="">-- Select --</option>
                                <option value="KTP" {{ old('identity_type', $customer->identity_type) == 'KTP' ? 'selected' : '' }}>KTP</option>
                                <option value="Passport" {{ old('identity_type', $customer->identity_type) == 'Passport' ? 'selected' : '' }}>Passport</option>
                                <option value="KITAS" {{ old('identity_type', $customer->identity_type) == 'KITAS' ? 'selected' : '' }}>KITAS</option>
                                <option value="SIM" {{ old('identity_type', $customer->identity_type) == 'SIM' ? 'selected' : '' }}>SIM</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="identity_number">Identity Number</label>
                            <input type="text" id="identity_number" name="identity_number" class="form-control" placeholder="e.g. 3174012345678901" value="{{ old('identity_number', $customer->identity_number) }}" />
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Attachments (Supporting Documents)</h6></div>
                        @if(!empty($customer->attachments))
                            <div class="col-12">
                                <label class="form-label">Current Files</label>
                                <ul class="list-group list-group-flush">
                                    @foreach($customer->attachments as $idx => $att)
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <a href="{{ isset($att['path']) && \Illuminate\Support\Facades\Storage::disk('public')->exists($att['path']) ? asset('storage/'.$att['path']) : '#' }}" target="_blank">{{ $att['name'] ?? 'File' }}</a>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-att" data-id="{{ $customer->id }}" data-idx="{{ $idx }}">Remove</button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="col-12">
                            <label class="form-label">Add More Files</label>
                            <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png" />
                        </div>

                        <div class="col-12"><hr class="my-2"><h6 class="text-muted mb-2">Customer App (Future)</h6></div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Has App Access</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="has_app_access" id="has_app_0" value="0" {{ !old('has_app_access', $customer->has_app_access) ? 'checked' : '' }}>
                                <label class="form-check-label" for="has_app_0">No</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="has_app_access" id="has_app_1" value="1" {{ old('has_app_access', $customer->has_app_access) ? 'checked' : '' }}>
                                <label class="form-check-label" for="has_app_1">Yes</label>
                            </div>
                        </div>
                        <div class="col-md-6" id="app_creds_wrap" style="{{ old('has_app_access', $customer->has_app_access) ? '' : 'display:none' }}">
                            <label class="form-label" for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" placeholder="Login username" value="{{ old('username', $customer->username) }}" />
                        </div>
                        <div class="col-md-6" id="password_wrap" style="{{ old('has_app_access', $customer->has_app_access) ? '' : 'display:none' }}">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Leave blank to keep current. Min. 6 characters" />
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="notes">Notes</label>
                            <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Additional notes">{{ old('notes', $customer->notes) }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $customer->is_active) ? 'checked' : '' }} />
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
                    $('#app_creds_wrap, #password_wrap').toggle($(this).val() == '1');
                });
                $('#btn-submit').click(function() { $('#postForm').submit(); });
                $('#postForm').on('submit', function() {
                    $(this).block({ message: '<div class="spinner-border text-primary"></div>', timeout: 1000, css: { backgroundColor: "transparent", border: 0 }, overlayCSS: { backgroundColor: "#fff", opacity: .8 } });
                });
                $('.btn-remove-att').click(function() {
                    var btn = $(this);
                    if (confirm('Remove this attachment?')) {
                        $.post('{{ route("customer.list.remove.attachment") }}', {
                            _token: '{{ csrf_token() }}',
                            customer_id: btn.data('id'),
                            index: btn.data('idx')
                        }).done(function() { btn.closest('li').remove(); }).fail(function() { alert('Failed'); });
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
