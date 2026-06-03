<x-app-layout>

    @section('title', 'Detail Employee | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
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
                ['label' => 'Detail', 'active' => true]
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
                    <h5 class="card-header fw-bold" style="color: #212529">Employee Detail</h5>
                    <form method="POST" id="postForm">
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
                                            <label class="form-label" for="employee_code">Kode Karyawan</label>
                                            <input type="text" id="employee_code" name="employee_code" class="form-control"
                                                value="{{ $employee->employee_code }}" disabled />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="identity_number">Nomor Identitas (KTP/Passport)</label>
                                            <input type="text" id="identity_number" name="identity_number" class="form-control"
                                                value="{{ $employee->identity_number }}" disabled />
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label" for="fullname">Nama Lengkap</label>
                                            <input type="text" id="fullname" name="fullname" class="form-control"
                                                value="{{ $employee->fullname }}" disabled />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="nickname">Nama Panggilan</label>
                                            <input type="text" id="nickname" name="nickname" class="form-control"
                                                value="{{ $employee->nickname }}" disabled />
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" for="gender">Jenis Kelamin</label>
                                            <input type="text" id="gender" name="gender" class="form-control"
                                                value="{{ $employee->gender }}" disabled />
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" for="number_of_dependents">Jml Tanggungan</label>
                                            <input type="text" id="number_of_dependents" name="number_of_dependents" class="form-control"
                                                value="{{ $employee->number_of_dependents ?? 0 }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="place_of_birth">Tempat Lahir</label>
                                            <input type="text" id="place_of_birth" name="place_of_birth" class="form-control"
                                                value="{{ $employee->place_of_birth }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="date_of_birth">Tanggal Lahir</label>
                                            <input type="text" id="date_of_birth" name="date_of_birth" class="form-control"
                                                value="{{ $employee->date_of_birth }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="religion">Agama</label>
                                            <input type="text" id="religion" name="religion" class="form-control"
                                                value="{{ $employee->religion }}" disabled />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="marital_status">Status Pernikahan</label>
                                            <input type="text" id="marital_status" name="marital_status" class="form-control"
                                                value="{{ $employee->marital_status }}" disabled />
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
                                                disabled>{{ $employee->address }}</textarea>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="city">Kota</label>
                                            <input type="text" id="city" name="city" class="form-control"
                                                value="{{ $employee->city }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="province">Provinsi</label>
                                            <input type="text" id="province" name="province" class="form-control"
                                                value="{{ $employee->province }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="postal_code">Kode Pos</label>
                                            <input type="text" id="postal_code" name="postal_code" class="form-control"
                                                value="{{ $employee->postal_code }}" disabled />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="phone_number">Nomor Telepon</label>
                                            <input type="text" id="phone_number" name="phone_number" class="form-control"
                                                value="{{ $employee->phone_number }}" disabled />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="email">Email</label>
                                            <input type="text" id="email" name="email" class="form-control"
                                                value="{{ $employee->email }}" disabled />
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
                                            <label class="form-label" for="position_id">Jabatan</label>
                                            <input type="text" id="position_id" name="position_id" class="form-control"
                                                value="{{ $employee->position->name ?? '' }}" disabled />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="division_id">Divisi</label>
                                            <input type="text" id="division_id" name="division_id" class="form-control"
                                                value="{{ $employee->division->name ?? '' }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="join_date">Tanggal Bergabung</label>
                                            <input type="text" id="join_date" name="join_date" class="form-control"
                                                value="{{ $employee->join_date }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="contract_start_date">Tanggal Mulai Kontrak</label>
                                            <input type="text" id="contract_start_date" name="contract_start_date" class="form-control"
                                                value="{{ $employee->contract_start_date }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="contract_end_date">Tanggal Selesai Kontrak</label>
                                            <input type="text" id="contract_end_date" name="contract_end_date" class="form-control"
                                                value="{{ $employee->contract_end_date }}" disabled />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="employment_status">Status Ketenagakerjaan</label>
                                            <input type="text" id="employment_status" name="employment_status" class="form-control"
                                                value="{{ $employee->employment_status }}" disabled />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="employee_status">Status Karyawan</label>
                                            <input type="text" id="employee_status" name="employee_status" class="form-control"
                                                value="{{ $employee->employee_status }}" disabled />
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
                                                value="{{ $employee->tax_number }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="bpjs_kesehatan">BPJS Kesehatan</label>
                                            <input type="text" id="bpjs_kesehatan" name="bpjs_kesehatan" class="form-control"
                                                value="{{ $employee->bpjs_kesehatan }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="bpjs_ketenagakerjaan">BPJS Ketenagakerjaan</label>
                                            <input type="text" id="bpjs_ketenagakerjaan" name="bpjs_ketenagakerjaan" class="form-control"
                                                value="{{ $employee->bpjs_ketenagakerjaan }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="bank_name">Nama Bank</label>
                                            <input type="text" id="bank_name" name="bank_name" class="form-control"
                                                value="{{ $employee->bank_name }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="bank_account_number">Nomor Rekening</label>
                                            <input type="text" id="bank_account_number" name="bank_account_number" class="form-control"
                                                value="{{ $employee->bank_account_number }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="bank_account_name">Nama Pemilik Rekening</label>
                                            <input type="text" id="bank_account_name" name="bank_account_name" class="form-control"
                                                value="{{ $employee->bank_account_name }}" disabled />
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
                                                value="{{ $employee->emergency_contact_name }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="emergency_contact_relation">Hubungan</label>
                                            <input type="text" id="emergency_contact_relation" name="emergency_contact_relation" class="form-control"
                                                value="{{ $employee->emergency_contact_relation }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="emergency_contact_phone">Nomor Telepon Darurat</label>
                                            <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control"
                                                value="{{ $employee->emergency_contact_phone }}" disabled />
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
                                            <label class="form-label" for="username">Username</label>
                                            <input type="text" id="username" class="form-control"
                                                value="{{ $user->username }}" disabled />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="role_id">Role</label>
                                            <input type="text" id="role_id" class="form-control"
                                                value="{{ $user->role->name ?? '' }}" disabled />
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
                                            <label class="form-label">Holding</label>
                                            <input type="text" class="form-control"
                                                value="{{ $holdingName ?? '-' }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Company</label>
                                            <input type="text" class="form-control"
                                                value="{{ $companyName ?? '-' }}" disabled />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Branch</label>
                                            <input type="text" class="form-control"
                                                value="{{ $branchName ?? '-' }}" disabled />
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
                <a href="{{ route('users.index.view') }}" type="button" class="btn btn-outline-dark me-2">
                    <span class="ti-xs ti ti-arrow-left me-1"></span>Back
                </a>
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
        </script>
    @endpush

</x-app-layout>
