<x-app-layout>

    @section('title', 'Edit Holding | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    @endpush

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
                ['label' => 'Master Data', 'url' => route('holding.index.view')],
                ['label' => 'Edit Holding', 'active' => true]
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <!-- Form Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Edit Holding</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('holding.edit.data') }}">
                    @csrf
                    <input type="hidden" name="id" value="{{ $businessUnit->id }}">

                    <div class="accordion" id="holdingAccordion">
                        <!-- Basic Information -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#collapseBasic">
                                    <i class="ti ti-building-arch me-2"></i> Basic Information
                                </button>
                            </h2>
                            <div id="collapseBasic" class="accordion-collapse collapse show" data-bs-parent="#holdingAccordion">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Type <span class="text-danger">*</span></label>
                                            <select name="type_code" class="select2 form-select" required>
                                                <option value="">Select Type</option>
                                                <option value="HOLDING" {{ $businessUnit->type_code === 'HOLDING' ? 'selected' : '' }}>Holding</option>
                                                <option value="COMPANY" {{ $businessUnit->type_code === 'COMPANY' ? 'selected' : '' }}>Company</option>
                                                <option value="BRANCH" {{ $businessUnit->type_code === 'BRANCH' ? 'selected' : '' }}>Branch</option>
                                            </select>
                                            @error('type_code')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Parent</label>
                                            <select name="parent_id" class="select2 form-select">
                                                <option value="">No Parent (Root Level)</option>
                                                @foreach ($parentBusinessUnits as $parent)
                                                    <option value="{{ $parent->id }}" {{ $businessUnit->parent_id === $parent->id ? 'selected' : '' }}>{{ $parent->type_code }} - {{ $parent->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('parent_id')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Code <span class="text-danger">*</span></label>
                                            <input type="text" name="code" class="form-control" placeholder="Enter code" value="{{ old('code', $businessUnit->code) }}" required>
                                            @error('code')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Name <span class="text-danger">*</span></label>
                                            <input type="text" name="name" class="form-control" placeholder="Enter business unit name" value="{{ old('name', $businessUnit->name) }}" required>
                                            @error('name')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Brand Name</label>
                                            <input type="text" name="brand_name" class="form-control" placeholder="Enter brand name" value="{{ old('brand_name', $businessUnit->brand_name) }}">
                                            @error('brand_name')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Legal Name</label>
                                            <input type="text" name="legal_name" class="form-control" placeholder="Enter legal name" value="{{ old('legal_name', $businessUnit->legal_name) }}">
                                            @error('legal_name')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseContact">
                                    <i class="ti ti-mail me-2"></i> Contact Information
                                </button>
                            </h2>
                            <div id="collapseContact" class="accordion-collapse collapse" data-bs-parent="#holdingAccordion">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control" placeholder="Enter email" value="{{ old('email', $businessUnit->email) }}">
                                            @error('email')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone</label>
                                            <input type="text" name="phone" class="form-control" placeholder="Enter phone number" value="{{ old('phone', $businessUnit->phone) }}">
                                            @error('phone')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Address</label>
                                            <textarea name="address" class="form-control" rows="3" placeholder="Enter address">{{ old('address', $businessUnit->address) }}</textarea>
                                            @error('address')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        {{-- Province & City Dropdown --}}
                                        @php
                                            $selectedProvince = old('province', $businessUnit->province ?? '');
                                            $selectedCity = old('city', $businessUnit->city ?? '');
                                        @endphp
                                        <div class="col-md-3">
                                            <label class="form-label">Province</label>
                                            <input type="hidden" name="province" id="province_name_hidden" value="{{ old('province', $businessUnit->province ?? '') }}">
                                            <select id="province_select" class="form-select" style="width: 100%;">
                                                <option value="">Pilih Province</option>
                                            </select>
                                            @error('province')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">City</label>
                                            <input type="hidden" name="city" id="city_name_hidden" value="{{ old('city', $businessUnit->city ?? '') }}">
                                            <select id="city_select" class="form-select" style="width: 100%;" disabled>
                                                <option value="">Pilih Province Terlebih Dahulu</option>
                                            </select>
                                            @error('city')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Postal Code</label>
                                            <input type="text" name="postal_code" class="form-control" placeholder="Enter postal code" value="{{ old('postal_code', $businessUnit->postal_code) }}">
                                            @error('postal_code')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Country</label>
                                            <input type="text" name="country" class="form-control" placeholder="Enter country" value="{{ old('country', $businessUnit->country) }}">
                                            @error('country')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tax & Legal -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseTax">
                                    <i class="ti ti-file-text me-2"></i> Tax & Legal
                                </button>
                            </h2>
                            <div id="collapseTax" class="accordion-collapse collapse" data-bs-parent="#holdingAccordion">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">NPWP</label>
                                            <input type="text" name="npwp" class="form-control" placeholder="Enter NPWP" value="{{ old('npwp', $businessUnit->npwp) }}">
                                            @error('npwp')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">NIB</label>
                                            <input type="text" name="nib" class="form-control" placeholder="Enter NIB" value="{{ old('nib', $businessUnit->nib) }}">
                                            @error('nib')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Tax Type</label>
                                            <select name="tax_type" class="select2 form-select">
                                                <option value="">Select Tax Type</option>
                                                <option value="inclusive" {{ $businessUnit->tax_type === 'inclusive' ? 'selected' : '' }}>Inclusive</option>
                                                <option value="exclusive" {{ $businessUnit->tax_type === 'exclusive' ? 'selected' : '' }}>Exclusive</option>
                                            </select>
                                            @error('tax_type')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Tax Percentage (%)</label>
                                            <input type="number" name="tax_percentage" class="form-control" placeholder="0" min="0" max="100" step="0.01" value="{{ old('tax_percentage', $businessUnit->tax_percentage) }}">
                                            @error('tax_percentage')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Service Charge (%)</label>
                                            <input type="number" name="service_charge_percentage" class="form-control" placeholder="0" min="0" max="100" step="0.01" value="{{ old('service_charge_percentage', $businessUnit->service_charge_percentage) }}">
                                            @error('service_charge_percentage')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Settings -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseSettings">
                                    <i class="ti ti-settings me-2"></i> Settings
                                </button>
                            </h2>
                            <div id="collapseSettings" class="accordion-collapse collapse" data-bs-parent="#holdingAccordion">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Timezone</label>
                                            <select name="timezone" class="select2 form-select">
                                                <option value="">Select Timezone</option>
                                                <option value="Asia/Jakarta" {{ $businessUnit->timezone === 'Asia/Jakarta' ? 'selected' : '' }}>Asia/Jakarta (WIB)</option>
                                                <option value="Asia/Makassar" {{ $businessUnit->timezone === 'Asia/Makassar' ? 'selected' : '' }}>Asia/Makassar (WITA)</option>
                                                <option value="Asia/Jayapura" {{ $businessUnit->timezone === 'Asia/Jayapura' ? 'selected' : '' }}>Asia/Jayapura (WIT)</option>
                                                <option value="Asia/Singapore" {{ $businessUnit->timezone === 'Asia/Singapore' ? 'selected' : '' }}>Asia/Singapore</option>
                                                <option value="UTC" {{ $businessUnit->timezone === 'UTC' ? 'selected' : '' }}>UTC</option>
                                            </select>
                                            @error('timezone')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Currency</label>
                                            <input type="text" name="currency" class="form-control" placeholder="e.g. IDR, USD" value="{{ old('currency', $businessUnit->currency) }}">
                                            @error('currency')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Opening Date</label>
                                            <input type="date" name="opening_date" class="form-control flatpickr-basic" value="{{ old('opening_date', $businessUnit->opening_date ? $businessUnit->opening_date->format('Y-m-d') : '') }}">
                                            @error('opening_date')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check mt-4">
                                                <input class="form-check-input" type="checkbox" name="is_pos_active" id="is_pos_active" value="1" {{ $businessUnit->is_pos_active ? 'checked' : '' }}>
                                                <label class="form-check-label" for="is_pos_active">
                                                    POS Active
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check mt-4">
                                                <input class="form-check-input" type="checkbox" name="is_inventory_active" id="is_inventory_active" value="1" {{ $businessUnit->is_inventory_active ? 'checked' : '' }}>
                                                <label class="form-check-label" for="is_inventory_active">
                                                    Inventory Active
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check mt-4">
                                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ $businessUnit->is_active ? 'checked' : '' }}>
                                                <label class="form-check-label" for="is_active">
                                                    Active
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-transparent border-top pt-3">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('holding.index.view') }}" class="btn btn-label-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
    <!-- / Content -->

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush


    @push('page-js')
        <script>
            $(document).ready(function() {
                // Initialize Select2 (global - exclude province_select and city_select)
                $('.select2').not('#province_select, #city_select').select2({
                    placeholder: function() {
                        return $(this).data('placeholder') || 'Select';
                    }
                });

                // Initialize Province Select2 with AJAX search
                $('#province_select').select2({
                    placeholder: 'Cari Province (minimal 3 karakter)',
                    allowClear: true,
                    width: '100%',
                    minimumInputLength: 3,
                    ajax: {
                        url: '/helper/provinces',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                search: params.term,
                                page: params.page || 1,
                                per_page: 50
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data.results || [],
                                pagination: data.pagination || { more: false }
                            };
                        },
                        cache: true
                    }
                });

                // Initialize City Select2
                $('#city_select').select2({
                    placeholder: 'Pilih Province Terlebih Dahulu',
                    allowClear: true,
                    width: '100%',
                    disabled: true
                });

                // Province change handler - load cities
                $('#province_select').on('select2:select', function(e) {
                    const provinceId = e.params.data.id;
                    const provinceName = e.params.data.text;
                    const $citySelect = $('#city_select');

                    $('#province_name_hidden').val(provinceName);
                    $('#city_name_hidden').val('');

                    $citySelect.empty().append('<option value="">Loading...</option>').prop('disabled', true).trigger('change');

                    $.get('/helper/cities', { province_id: provinceId, per_page: 9999 }, function(data) {
                        const cities = data.results || [];

                        $citySelect.empty().append('<option value="">Pilih City</option>');

                        cities.forEach(function(city) {
                            $citySelect.append('<option value="' + city.text + '">' + city.text + '</option>');
                        });

                        $citySelect.prop('disabled', false).trigger('change');

                        const oldCity = '{{ old('city', $businessUnit->city ?? '') }}';
                        if (oldCity && $citySelect.find('option[value="' + oldCity + '"]').length) {
                            $citySelect.val(oldCity).trigger('change');
                        }
                    });
                });

                // City change handler
                $('#city_select').on('change', function() {
                    $('#city_name_hidden').val($(this).val() || '');
                });

                // Province clear handler
                $('#province_select').on('select2:clear', function() {
                    const $citySelect = $('#city_select');
                    $('#province_name_hidden').val('');
                    $('#city_name_hidden').val('');
                    $citySelect.empty().append('<option value="">Pilih Province Terlebih Dahulu</option>').prop('disabled', true).trigger('change');
                });

                // Initial load: if province is selected, load cities
                @if($selectedProvinceId && $businessUnit->province)
                    var provinceOption = new Option('{{ $businessUnit->province }}', '{{ $selectedProvinceId }}', true, true);
                    $('#province_select').append(provinceOption).trigger('change');
                    $('#province_name_hidden').val('{{ $businessUnit->province }}');

                    $.get('/helper/cities', { province_id: '{{ $selectedProvinceId }}', per_page: 9999 }, function(data) {
                        const cities = data.results || [];
                        const $citySelect = $('#city_select');

                        $citySelect.empty().append('<option value="">Pilih City</option>');

                        cities.forEach(function(city) {
                            $citySelect.append('<option value="' + city.text + '">' + city.text + '</option>');
                        });

                        $citySelect.prop('disabled', false).trigger('change');

                        @if($businessUnit->city)
                            $citySelect.val('{{ $businessUnit->city }}').trigger('change');
                            $('#city_name_hidden').val('{{ $businessUnit->city }}');
                        @endif
                    });
                @endif
            });
        </script>
    @endpush

</x-app-layout>
