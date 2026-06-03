<x-app-layout>

    @section('title', 'Add Employee | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.css') }}" />
        <link rel="stylesheet"
            href="{{ asset('assets/vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/jquery-timepicker/jquery-timepicker.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/pickr/pickr-themes.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}" />
    @endpush

    @push('page-css')
        <style>
            .breadcrumb-item a:hover {
                color: #212529 !important;
            }
        </style>
    @endpush

    <!-- Content -->

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Human Resources', 'url' => 'javascript:void(0);'],
                ['label' => 'Employee', 'url' => route('users.index.view')],
                ['label' => 'Add', 'active' => true]
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <!-- Advance Styling Options -->
        <div class="row mb-3">
            <!-- Accordion with Icon -->
            <div class="col-md mb-4 mb-md-2">
                <div class="card accordion mt-3" id="accordionWithIcon">
                    <h5 class="card-header fw-bold" style="color: #212529">Add Employee</h5>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('users.insert.data') }}"
                        id="postForm">
                        @csrf
                        <hr style="margin-bottom: 0.5rem; margin-top: 0;" />

                        <!-- Accordion 1: Identitas Karyawan -->
                        <div class="accordion-item">
                            <h2 class="accordion-header d-flex align-items-center">
                                <button type="button" class="accordion-button" data-bs-toggle="collapse"
                                    data-bs-target="#accordionIdentity" aria-expanded="true">
                                    <i class="ti ti-user me-2"></i> Identitas Karyawan
                                </button>
                            </h2>
                            <div id="accordionIdentity" class="accordion-collapse collapse show">
                                <div class="accordion-body">
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label" for="employee_code">Kode Karyawan<span style="color: red">*</span></label>
                                            <input type="text" id="employee_code" name="employee_code" class="form-control"
                                                placeholder="EMP-001" value="{{ old('employee_code') }}" />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="identity_number">Nomor Identitas (KTP/Passport)<span style="color: red">*</span></label>
                                            <input type="text" id="identity_number" name="identity_number" class="form-control"
                                                placeholder="320xxxxxxxxxxxxxx" value="{{ old('identity_number') }}" />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="fullname">Nama Lengkap<span style="color: red">*</span></label>
                                            <input type="text" id="fullname" name="fullname" class="form-control"
                                                placeholder="Enter Full Name" value="{{ old('fullname') }}" />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="nickname">Nama Panggilan</label>
                                            <input type="text" id="nickname" name="nickname" class="form-control"
                                                placeholder="Enter Nickname" value="{{ old('nickname') }}" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="gender">Jenis Kelamin</label>
                                            <select id="gender" name="gender" class="select2 form-select" data-allow-clear="true">
                                                <option value="">Select</option>
                                                <option value="Laki-laki" {{ old('gender') == 'Laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                                                <option value="Perempuan" {{ old('gender') == 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                                                <option value="Other" {{ old('gender') == 'Other' ? 'selected' : '' }}>Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="place_of_birth">Tempat Lahir</label>
                                            <input type="text" id="place_of_birth" name="place_of_birth" class="form-control"
                                                placeholder="Enter Place of Birth" value="{{ old('place_of_birth') }}" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="date_of_birth">Tanggal Lahir</label>
                                            <input type="text" id="date_of_birth" name="date_of_birth"
                                                class="form-control dob-picker" placeholder="DD MMMM YYYY" value="{{ old('date_of_birth') }}" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="religion">Agama</label>
                                            <select id="religion" name="religion" class="select2 form-select" data-allow-clear="true">
                                                <option value="">Select</option>
                                                <option value="Islam" {{ old('religion') == 'Islam' ? 'selected' : '' }}>Islam</option>
                                                <option value="Kristen" {{ old('religion') == 'Kristen' ? 'selected' : '' }}>Kristen</option>
                                                <option value="Katolik" {{ old('religion') == 'Katolik' ? 'selected' : '' }}>Katolik</option>
                                                <option value="Hindu" {{ old('religion') == 'Hindu' ? 'selected' : '' }}>Hindu</option>
                                                <option value="Buddha" {{ old('religion') == 'Buddha' ? 'selected' : '' }}>Buddha</option>
                                                <option value="Konghucu" {{ old('religion') == 'Konghucu' ? 'selected' : '' }}>Konghucu</option>
                                                <option value="Other" {{ old('religion') == 'Other' ? 'selected' : '' }}>Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="marital_status">Status Pernikahan</label>
                                            <select id="marital_status" name="marital_status" class="select2 form-select" data-allow-clear="true">
                                                <option value="">Select</option>
                                                <option value="Single" {{ old('marital_status') == 'Single' ? 'selected' : '' }}>Single</option>
                                                <option value="Menikah" {{ old('marital_status') == 'Menikah' ? 'selected' : '' }}>Menikah</option>
                                                <option value="Janda" {{ old('marital_status') == 'Janda' ? 'selected' : '' }}>Janda</option>
                                                <option value="Duda" {{ old('marital_status') == 'Duda' ? 'selected' : '' }}>Duda</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="number_of_dependents">Jumlah Tanggungan</label>
                                            <input type="number" id="number_of_dependents" name="number_of_dependents" class="form-control"
                                                placeholder="0" value="{{ old('number_of_dependents') ?? 0 }}" min="0" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion 2: Kontak & Alamat -->
                        <div class="accordion-item">
                            <h2 class="accordion-header d-flex align-items-center">
                                <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                                    data-bs-target="#accordionContact" aria-expanded="false">
                                    <i class="ti ti-map-pin me-2"></i> Kontak & Alamat
                                </button>
                            </h2>
                            <div id="accordionContact" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-12">
                                            <label class="form-label" for="address">Alamat Lengkap</label>
                                            <textarea id="address" name="address" class="form-control" rows="3"
                                                placeholder="Enter Address">{{ old('address') }}</textarea>
                                        </div>
                                        {{-- Province & City Dropdown --}}
                                        <div class="col-md-6">
                                            <label class="form-label">Provinsi</label>
                                            <input type="hidden" name="province" id="province_name_hidden" value="{{ old('province', '') }}">
                                            <select id="province_select" class="form-select" style="width: 100%;">
                                                <option value="">Pilih Provinsi</option>
                                                @if(old('province'))
                                                    <option value="{{ old('province') }}" selected>{{ old('province') }}</option>
                                                @endif
                                            </select>
                                            @error('province')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Kota</label>
                                            <input type="hidden" name="city" id="city_name_hidden" value="{{ old('city', '') }}">
                                            <select id="city_select" class="form-select" style="width: 100%;" disabled>
                                                <option value="">Pilih Provinsi Terlebih Dahulu</option>
                                                @if(old('city'))
                                                    <option value="{{ old('city') }}" selected>{{ old('city') }}</option>
                                                @endif
                                            </select>
                                            @error('city')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="postal_code">Kode Pos</label>
                                            <input type="text" id="postal_code" name="postal_code" class="form-control"
                                                placeholder="12345" value="{{ old('postal_code') }}" />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="phone_number">Nomor Telepon</label>
                                            <input type="text" id="phone_number" name="phone_number" class="form-control"
                                                placeholder="08xxxxxxxxxx" value="{{ old('phone_number') }}"
                                                oninput="this.value = this.value.replace(/[^0-9]/g, '');" />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="email">Email</label>
                                            <input type="email" id="email" name="email" class="form-control"
                                                placeholder="email@example.com" value="{{ old('email') }}" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion 3: Data Kepegawaian -->
                        <div class="accordion-item">
                            <h2 class="accordion-header d-flex align-items-center">
                                <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                                    data-bs-target="#accordionEmployment" aria-expanded="false">
                                    <i class="ti ti-briefcase me-2"></i> Data Kepegawaian
                                </button>
                            </h2>
                            <div id="accordionEmployment" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label" for="position_id">Jabatan<span style="color: red">*</span></label>
                                            <select id="position_id" name="position_id" class="select2 form-select" data-allow-clear="true">
                                                <option value="">Select Position</option>
                                                @foreach($positions as $position)
                                                    <option value="{{ $position->id }}" {{ old('position_id') == $position->id ? 'selected' : '' }}>
                                                        {{ $position->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="division_id">Divisi</label>
                                            <select id="division_id" name="division_id" class="select2 form-select" data-allow-clear="true">
                                                <option value="">Select Division</option>
                                                @foreach($divisions as $division)
                                                    <option value="{{ $division->id }}" {{ old('division_id') == $division->id ? 'selected' : '' }}>
                                                        {{ $division->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="join_date">Tanggal Bergabung</label>
                                            <input type="text" id="join_date" name="join_date"
                                                class="form-control date-picker" placeholder="DD MMMM YYYY" value="{{ old('join_date') }}" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="contract_start_date">Tanggal Mulai Kontrak</label>
                                            <input type="text" id="contract_start_date" name="contract_start_date"
                                                class="form-control date-picker" placeholder="DD MMMM YYYY" value="{{ old('contract_start_date') }}" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="contract_end_date">Tanggal Selesai Kontrak</label>
                                            <input type="text" id="contract_end_date" name="contract_end_date"
                                                class="form-control date-picker" placeholder="DD MMMM YYYY" value="{{ old('contract_end_date') }}" />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="employment_status">Status Ketenagakerjaan</label>
                                            <select id="employment_status" name="employment_status" class="select2 form-select" data-allow-clear="true">
                                                <option value="">Select</option>
                                                <option value="Permanent" {{ old('employment_status') == 'Permanent' ? 'selected' : '' }}>Permanent</option>
                                                <option value="Contract" {{ old('employment_status') == 'Contract' ? 'selected' : '' }}>Contract</option>
                                                <option value="Probation" {{ old('employment_status') == 'Probation' ? 'selected' : '' }}>Probation</option>
                                                <option value="Intern" {{ old('employment_status') == 'Intern' ? 'selected' : '' }}>Intern</option>
                                                <option value="Freelance" {{ old('employment_status') == 'Freelance' ? 'selected' : '' }}>Freelance</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="employee_status">Status Karyawan</label>
                                            <select id="employee_status" name="employee_status" class="select2 form-select" data-allow-clear="true">
                                                <option value="">Select</option>
                                                <option value="Active" {{ old('employee_status') == 'Active' ? 'selected' : '' }}>Active</option>
                                                <option value="Inactive" {{ old('employee_status') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                                                <option value="Resigned" {{ old('employee_status') == 'Resigned' ? 'selected' : '' }}>Resigned</option>
                                                <option value="Terminated" {{ old('employee_status') == 'Terminated' ? 'selected' : '' }}>Terminated</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion 4: Payroll & Legal -->
                        <div class="accordion-item">
                            <h2 class="accordion-header d-flex align-items-center">
                                <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                                    data-bs-target="#accordionPayroll" aria-expanded="false">
                                    <i class="ti ti-credit-card me-2"></i> Payroll & Legal
                                </button>
                            </h2>
                            <div id="accordionPayroll" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-4">
                                            <label class="form-label" for="tax_number">NPWP</label>
                                            <input type="text" id="tax_number" name="tax_number" class="form-control"
                                                placeholder="xx.xxx.xxx.x-xxx.xxx" value="{{ old('tax_number') }}" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="bpjs_kesehatan">BPJS Kesehatan</label>
                                            <input type="text" id="bpjs_kesehatan" name="bpjs_kesehatan" class="form-control"
                                                placeholder="xxxxxxxxxx" value="{{ old('bpjs_kesehatan') }}" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="bpjs_ketenagakerjaan">BPJS Ketenagakerjaan</label>
                                            <input type="text" id="bpjs_ketenagakerjaan" name="bpjs_ketenagakerjaan" class="form-control"
                                                placeholder="xxxxxxxxxx" value="{{ old('bpjs_ketenagakerjaan') }}" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="bank_name">Nama Bank</label>
                                            <input type="text" id="bank_name" name="bank_name" class="form-control"
                                                placeholder="BCA, Mandiri, etc." value="{{ old('bank_name') }}" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="bank_account_number">Nomor Rekening</label>
                                            <input type="text" id="bank_account_number" name="bank_account_number" class="form-control"
                                                placeholder="xxxxxxxxxx" value="{{ old('bank_account_number') }}" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="bank_account_name">Nama Pemilik Rekening</label>
                                            <input type="text" id="bank_account_name" name="bank_account_name" class="form-control"
                                                placeholder="Account Holder Name" value="{{ old('bank_account_name') }}" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion 5: Emergency Contact -->
                        <div class="accordion-item">
                            <h2 class="accordion-header d-flex align-items-center">
                                <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                                    data-bs-target="#accordionEmergency" aria-expanded="false">
                                    <i class="ti ti-phone-call me-2"></i> Kontak Darurat
                                </button>
                            </h2>
                            <div id="accordionEmergency" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-4">
                                            <label class="form-label" for="emergency_contact_name">Nama Kontak Darurat</label>
                                            <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control"
                                                placeholder="Emergency Contact Name" value="{{ old('emergency_contact_name') }}" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="emergency_contact_relation">Hubungan</label>
                                            <input type="text" id="emergency_contact_relation" name="emergency_contact_relation" class="form-control"
                                                placeholder="Spouse, Parent, Sibling, etc." value="{{ old('emergency_contact_relation') }}" />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="emergency_contact_phone">Nomor Telepon Darurat</label>
                                            <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control"
                                                placeholder="08xxxxxxxxxx" value="{{ old('emergency_contact_phone') }}"
                                                oninput="this.value = this.value.replace(/[^0-9]/g, '');" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion 6: Account -->
                        <div class="accordion-item">
                            <h2 class="accordion-header d-flex align-items-center">
                                <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                                    data-bs-target="#accordionAccount" aria-expanded="false">
                                    <i class="ti ti-user-circle me-2"></i> Akun
                                </button>
                            </h2>
                            <div id="accordionAccount" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="username">Username<span style="color: red">*</span></label>
                                            <div class="input-group input-group-merge">
                                                <input type="text" id="username" class="form-control"
                                                    placeholder="Enter Username" name="username"
                                                    value="{{ old('username') }}" />
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="role_id">Role<span style="color: red">*</span></label>
                                            <select id="role_id" name="role_id" class="select2 form-select" data-allow-clear="true">
                                                <option value="">Select Role</option>
                                                @foreach($roles as $role)
                                                    <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                                        {{ $role->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-password-toggle">
                                                <label class="form-label" for="password">Password<span style="color: red">*</span></label>
                                                <div class="input-group input-group-merge">
                                                    <input type="password" id="password" name="password"
                                                        class="form-control"
                                                        placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                                                        aria-describedby="password2" />
                                                    <span class="input-group-text cursor-pointer"
                                                        id="password2"><i class="ti ti-eye-off"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-password-toggle">
                                                <label class="form-label" for="password_confirmation">Confirm Password<span style="color: red">*</span></label>
                                                <div class="input-group input-group-merge">
                                                    <input type="password" id="password_confirmation"
                                                        class="form-control"
                                                        placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                                                        aria-describedby="password_confirmation2"
                                                        name="password_confirmation" />
                                                    <span class="input-group-text cursor-pointer"
                                                        id="password_confirmation2"><i
                                                            class="ti ti-eye-off"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Accordion 7: Branch Assignment -->
                        <div class="accordion-item">
                            <h2 class="accordion-header d-flex align-items-center">
                                <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                                    data-bs-target="#accordionBranch" aria-expanded="false">
                                    <i class="ti ti-building me-2"></i> Penempatan Cabang
                                </button>
                            </h2>
                            <div id="accordionBranch" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label" for="holding_id">Holding<span style="color: red">*</span></label>
                                            <select id="holding_id" class="form-select branch-select2" data-allow-clear="true">
                                                <option value="">Pilih Holding</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="company_id">Company<span style="color: red">*</span></label>
                                            <select id="company_id" class="form-select branch-select2" data-allow-clear="true" disabled>
                                                <option value="">Pilih Holding Terlebih Dahulu</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="current_business_unit_id">Branch<span style="color: red">*</span></label>
                                            <select id="current_business_unit_id" name="current_business_unit_id" class="form-select branch-select2" data-allow-clear="true" disabled>
                                                <option value="">Pilih Company Terlebih Dahulu</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="m-2"></div>
                    </form>
                </div>
            </div>
            <!--/ Accordion with Icon -->
        </div>
        <!--/ Advance Styling Options -->
    </div>
    <!-- / Content -->

    <!-- Floating Section -->
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="floating-footer d-flex justify-content-end align-items-center">
            <div>
                <a href="{{ route('users.index.view') }}" type="button" class="btn btn-outline-dark me-2"><span
                        class="ti-xs ti ti-x me-1"></span>Cancel</a>
                <button type="submit" class="btn btn-primary me-2" id="btn-submit" form="postForm"><span
                        class="ti-xs ti ti-device-floppy me-1"></span>Save</button>
            </div>
        </div>
    </div>

    <!-- / Floating Section -->

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/jquery-timepicker/jquery-timepicker.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/pickr/pickr.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/block-ui/block-ui.js') }}"></script>
    @endpush

    @push('page-js')
        <script src="{{ asset('assets/js/form-layouts.js') }}"></script>
        <script src="{{ asset('assets/js/forms-pickers.js') }}"></script>

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
                    placeholder: 'Cari Provinsi (minimal 3 karakter)',
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
                    placeholder: 'Pilih Provinsi Terlebih Dahulu',
                    allowClear: true,
                    width: '100%',
                    disabled: true
                });

                // Province change handler - load cities
                $('#province_select').on('select2:select', function(e) {
                    const provinceId = e.params.data.id;
                    const provinceName = e.params.data.text;
                    const $citySelect = $('#city_select');

                    // Set province name ke hidden input
                    $('#province_name_hidden').val(provinceName);
                    $('#city_name_hidden').val('');

                    // Show loading
                    $citySelect.empty().append('<option value="">Loading...</option>').prop('disabled', true).trigger('change');

                    // Load cities via AJAX
                    $.get('/helper/cities', { province_id: provinceId, per_page: 9999 }, function(data) {
                        const cities = data.results || [];

                        // Clear and populate city dropdown (value = nama, bukan UUID)
                        $citySelect.empty().append('<option value="">Pilih Kota</option>');

                        cities.forEach(function(city) {
                            $citySelect.append('<option value="' + city.text + '">' + city.text + '</option>');
                        });

                        // Enable city dropdown
                        $citySelect.prop('disabled', false).trigger('change');

                        // Restore old city value if exists
                        const oldCity = '{{ old('city') }}';
                        if (oldCity && $citySelect.find('option[value="' + oldCity + '"]').length) {
                            $citySelect.val(oldCity).trigger('change');
                        }
                    });
                });

                // City change handler - set nama ke hidden input
                $('#city_select').on('change', function() {
                    $('#city_name_hidden').val($(this).val() || '');
                });

                // Province clear handler
                $('#province_select').on('select2:clear', function() {
                    const $citySelect = $('#city_select');
                    $('#province_name_hidden').val('');
                    $('#city_name_hidden').val('');
                    $citySelect.empty().append('<option value="">Pilih Provinsi Terlebih Dahulu</option>').prop('disabled', true).trigger('change');
                });

                // === Branch Assignment Cascading Dropdowns ===
                $('.branch-select2').select2({
                    placeholder: function() {
                        return $(this).find('option:first').text() || 'Select';
                    },
                    width: '100%'
                });

                function loadBusinessUnits(typeCode, parentId, $target, selectedId, callback) {
                    var params = { type_code: typeCode };
                    if (parentId) params.parent_id = parentId;

                    $target.empty().append('<option value="">Loading...</option>').prop('disabled', true).trigger('change');

                    $.get('/helper/business-units', params, function(data) {
                        var results = data.results || [];
                        var placeholder = typeCode === 'HOLDING' ? 'Pilih Holding' : (typeCode === 'COMPANY' ? 'Pilih Company' : 'Pilih Branch');
                        $target.empty().append('<option value="">' + placeholder + '</option>');

                        results.forEach(function(item) {
                            $target.append('<option value="' + item.id + '">' + item.text + '</option>');
                        });

                        $target.prop('disabled', false);

                        if (selectedId && $target.find('option[value="' + selectedId + '"]').length) {
                            $target.val(selectedId).trigger('change');
                        } else {
                            $target.trigger('change');
                        }

                        if (typeof callback === 'function') callback();
                    });
                }

                function resetCompany() {
                    $('#company_id').empty().append('<option value="">Pilih Holding Terlebih Dahulu</option>').prop('disabled', true).trigger('change');
                }

                function resetBranch() {
                    $('#current_business_unit_id').empty().append('<option value="">Pilih Company Terlebih Dahulu</option>').prop('disabled', true).trigger('change');
                }

                loadBusinessUnits('HOLDING', null, $('#holding_id'), '{{ old('holding_id') }}');

                $('#holding_id').on('change', function() {
                    var holdingId = $(this).val();
                    resetBranch();

                    if (holdingId) {
                        loadBusinessUnits('COMPANY', holdingId, $('#company_id'), null);
                    } else {
                        resetCompany();
                    }
                });

                $('#company_id').on('change', function() {
                    var companyId = $(this).val();

                    if (companyId) {
                        loadBusinessUnits('BRANCH', companyId, $('#current_business_unit_id'), null);
                    } else {
                        resetBranch();
                    }
                });

                // Initialize Flatpickr for date fields
                $('.dob-picker').flatpickr({
                    dateFormat: 'd F Y',
                    maxDate: 'today'
                });

                $('.date-picker').flatpickr({
                    dateFormat: 'd F Y',
                });

                // Password toggle functionality
                $('.form-password-toggle .input-group-text').on('click', function() {
                    const input = $(this).siblings('input');
                    const icon = $(this).find('i');
                    if (input.attr('type') === 'password') {
                        input.attr('type', 'text');
                        icon.removeClass('ti-eye-off').addClass('ti-eye');
                    } else {
                        input.attr('type', 'password');
                        icon.removeClass('ti-eye').addClass('ti-eye-off');
                    }
                });

                $('#postForm').on('keypress', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        $(this).submit();
                    }
                });

                $('#btn-submit').on('click', function(e) {
                    $('#postForm').submit();
                });

                $('#postForm').submit(function(e) {
                    $("#postForm").block({
                        message: '<div class="spinner-border text-primary" role="status"></div>',
                        timeout: 1e3,
                        css: {
                            backgroundColor: "transparent",
                            border: "0"
                        },
                        overlayCSS: {
                            backgroundColor: "#fff",
                            opacity: .8
                        }
                    })
                });
            });
        </script>
    @endpush

</x-app-layout>
