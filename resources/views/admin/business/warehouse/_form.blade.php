@php
    /** @var \App\Models\Warehouse|\App\Models\BusinessUnit|null $warehouse */
    $warehouse = $warehouse ?? null;
    $linkedBranchIds = $linkedBranchIds ?? old('branch_ids', []);
    $defaultBranchId = $defaultBranchId ?? old('default_branch_id');
    $isEdit = ! empty($warehouse?->id);
    $codeValue = old('code', $isEdit ? $warehouse?->code : ($generatedCode ?? ''));
@endphp

<div class="accordion" id="warehouseAccordion">
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#collapseBasic">
                <i class="ti ti-building-warehouse me-2"></i> Basic Information
            </button>
        </h2>
        <div id="collapseBasic" class="accordion-collapse collapse show" data-bs-parent="#warehouseAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Company <span class="text-danger">*</span></label>
                        <select name="parent_id" id="warehouse_company_id" class="select2 form-select" required>
                            <option value="">Select company</option>
                            @foreach ($parentCompanies as $company)
                                <option value="{{ $company->id }}" @selected(old('parent_id', $warehouse?->company_id ?? $warehouse?->parent_id) === $company->id)>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Warehouse Code <span class="text-danger">*</span></label>
                        @if ($isEdit)
                            <input type="text" name="code" id="warehouse_code" class="form-control" value="{{ $codeValue }}" readonly>
                            <div class="form-text">Kode digenerate sistem dan tidak dapat diubah.</div>
                        @else
                            <div class="input-group">
                                <input type="text" name="code" id="warehouse_code" class="form-control" value="{{ $codeValue }}" readonly required>
                                <button type="button" class="btn btn-outline-secondary" id="btn_regenerate_warehouse_code" title="Regenerate Code">
                                    <i class="ti ti-refresh"></i>
                                </button>
                            </div>
                            <div class="form-text">Otomatis digenerate oleh sistem (WH-00001, …).</div>
                        @endif
                        @error('code')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Warehouse Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Warehouse name" value="{{ old('name', $warehouse?->name) }}" required>
                        @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Warehouse Type</label>
                        <select name="brand_name" class="select2 form-select">
                            <option value="">Select type</option>
                            @foreach ($warehouseTypes as $key => $label)
                                <option value="{{ $key }}" @selected(old('brand_name', $warehouse?->brand_name) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('brand_name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Legal Name / Person in Charge</label>
                        <input type="text" name="legal_name" class="form-control" value="{{ old('legal_name', $warehouse?->legal_name) }}">
                        @error('legal_name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseBranchLink">
                <i class="ti ti-link me-2"></i> Link to Branches
            </button>
        </h2>
        <div id="collapseBranchLink" class="accordion-collapse collapse" data-bs-parent="#warehouseAccordion">
            <div class="accordion-body">
                <p class="text-muted small">Select branches served by this warehouse. One branch can be set as the default.</p>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Linked Branches</label>
                        <select name="branch_ids[]" id="branch_ids" class="select2 form-select" multiple>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" data-parent="{{ $branch->parent_id }}"
                                    @selected(in_array($branch->id, (array) $linkedBranchIds, true))>
                                    {{ $branch->name }} ({{ $branch->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('branch_ids')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Default Branch</label>
                        <select name="default_branch_id" id="default_branch_id" class="select2 form-select">
                            <option value="">—</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('default_branch_id', $defaultBranchId) === $branch->id)>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseContact">
                <i class="ti ti-mail me-2"></i> Contact & Address
            </button>
        </h2>
        <div id="collapseContact" class="accordion-collapse collapse" data-bs-parent="#warehouseAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $warehouse?->email) }}">
                        @error('email')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $warehouse?->phone) }}">
                        @error('phone')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3">{{ old('address', $warehouse?->address) }}</textarea>
                        @error('address')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Province</label>
                        <input type="hidden" name="province" id="province_name_hidden" value="{{ old('province', $warehouse?->province) }}">
                        <select id="province_select" class="form-select" style="width: 100%;">
                            <option value="">Select province</option>
                            @if(old('province', $warehouse?->province))
                                <option value="{{ old('province', $warehouse?->province) }}" selected>{{ old('province', $warehouse?->province) }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">City</label>
                        <input type="hidden" name="city" id="city_name_hidden" value="{{ old('city', $warehouse?->city) }}">
                        <select id="city_select" class="form-select" style="width: 100%;" @if(empty($selectedProvinceId ?? null)) disabled @endif>
                            <option value="">Select city</option>
                            @if(old('city', $warehouse?->city))
                                <option value="{{ old('city', $warehouse?->city) }}" selected>{{ old('city', $warehouse?->city) }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code', $warehouse?->postal_code) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-control" value="{{ old('country', $warehouse?->country ?? 'Indonesia') }}">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseTax">
                <i class="ti ti-file-text me-2"></i> Tax & Legal
            </button>
        </h2>
        <div id="collapseTax" class="accordion-collapse collapse" data-bs-parent="#warehouseAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">NPWP</label>
                        <input type="text" name="npwp" class="form-control" value="{{ old('npwp', $warehouse?->npwp) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">NIB</label>
                        <input type="text" name="nib" class="form-control" value="{{ old('nib', $warehouse?->nib) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tax Type</label>
                        <select name="tax_type" class="select2 form-select">
                            <option value="">—</option>
                            <option value="inclusive" @selected(old('tax_type', $warehouse?->tax_type) === 'inclusive')>Inclusive</option>
                            <option value="exclusive" @selected(old('tax_type', $warehouse?->tax_type) === 'exclusive')>Exclusive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tax (%)</label>
                        <input type="number" name="tax_percentage" class="form-control" min="0" max="100" step="0.01" value="{{ old('tax_percentage', $warehouse?->tax_percentage) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Service Charge (%)</label>
                        <input type="number" name="service_charge_percentage" class="form-control" min="0" max="100" step="0.01" value="{{ old('service_charge_percentage', $warehouse?->service_charge_percentage) }}">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseSettings">
                <i class="ti ti-settings me-2"></i> Settings
            </button>
        </h2>
        <div id="collapseSettings" class="accordion-collapse collapse" data-bs-parent="#warehouseAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="select2 form-select">
                            <option value="">—</option>
                            @foreach (['Asia/Jakarta' => 'WIB', 'Asia/Makassar' => 'WITA', 'Asia/Jayapura' => 'WIT'] as $tz => $lbl)
                                <option value="{{ $tz }}" @selected(old('timezone', $warehouse?->timezone) === $tz)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Currency</label>
                        <input type="text" name="currency" class="form-control" value="{{ old('currency', $warehouse?->currency ?? 'IDR') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Opening Date</label>
                        <input type="date" name="opening_date" class="form-control" value="{{ old('opening_date', optional($warehouse?->opening_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_inventory_active" id="is_inventory_active" value="1"
                                @checked(old('is_inventory_active', $warehouse?->is_inventory_active ?? true))>
                            <label class="form-check-label" for="is_inventory_active">Inventory Active</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_pos_active" id="is_pos_active" value="1"
                                @checked(old('is_pos_active', $warehouse?->is_pos_active ?? false))>
                            <label class="form-check-label" for="is_pos_active">POS Active</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                                @checked(old('is_active', $warehouse?->is_active ?? true))>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
