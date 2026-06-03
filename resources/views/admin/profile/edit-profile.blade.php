<x-app-layout>

    @section('title', 'My Account | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
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
                ['label' => 'Profile', 'active' => true]
            ]"
        />

        <div class="row">
            <div class="col-md-12">
                @if (session('success'))
                    <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
                @endif

                @if ($errors->any())
                    <x-alert type="danger" class="mb-3">
                        <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </x-alert>
                @endif

                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data"
                    id="postForm">
                    <div class="card mb-4">
                        <h5 class="card-header">Profile Information</h5>
                        <!-- Account -->

                        <div class="card-body">
                            <div class="d-flex align-items-start align-items-sm-center gap-4">
                                <div class="image-crop rounded border-primary">
                                    <img src="{{ $user['url_image'] }}" alt="user-avatar" id="uploadedAvatar" />
                                </div>

                                <div class="button-wrapper">
                                    <label for="upload" class="btn btn-primary me-2 mb-3" tabindex="0">
                                        <span class="d-none d-sm-block">Upload new photo</span>
                                        <i class="ti ti-upload d-block d-sm-none"></i>
                                        <input type="file" id="upload" class="account-file-input" hidden
                                            accept=".png, .PNG, .jpg, .JPG, .jpeg, .JPEG" name="upload" />
                                    </label>
                                    <div class="text-muted">Allowed JPG or PNG. Max size of 1000K</div>
                                </div>
                            </div>
                        </div>
                        <hr class="my-0">
                        <div class="card-body">

                            @csrf
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="first_name" class="form-label">First Name<span style="color: red">
                                            *</span></label>
                                    <input class="form-control" type="text" id="first_name" name="first_name"
                                        placeholder="Enter First Name" value="{{ old('first_name', $user['first_name']) }}" />
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input class="form-control" type="text" id="last_name" name="last_name"
                                        placeholder="Enter Last Name" value="{{ old('last_name', $user['last_name']) }}" />
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select id="gender" name="gender" class="select2 form-select" data-allow-clear="true">
                                        <option value="">Select</option>
                                        <option value="Laki-laki" {{ old('gender', optional($user->employee)->gender ?? '') == 'Laki-laki' ? 'selected' : '' }}>Male</option>
                                        <option value="Perempuan" {{ old('gender', optional($user->employee)->gender ?? '') == 'Perempuan' ? 'selected' : '' }}>Female</option>
                                        <option value="Other" {{ old('gender', optional($user->employee)->gender ?? '') == 'Other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="phone_number" class="form-label">Phone Number</label>
                                    <input class="form-control" type="text" id="phone_number" name="phone_number"
                                        placeholder="021 000 111" value="{{ old('phone_number', optional($user->employee)->phone_number ?? '') }}"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '');" />
                                </div>
                                <div class="mb-3 col-md-12">
                                    <label for="email" class="form-label">Email</label>
                                    <input class="form-control" type="email" id="email" name="email"
                                        placeholder="email@email.com" value="{{ old('email', optional($user->employee)->email ?? '') }}" />
                                </div>
                                <div class="mb-3 col-md-12">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"
                                        placeholder="Enter Address">{{ old('address', optional($user->employee)->address ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>
                        <!-- /Account -->
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- / Content -->

    <!-- Floating Footer -->

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <div>
            <a href="/" type="button" class="btn btn-outline-dark me-2"><span
                    class="ti-xs ti ti-x me-1"></span>Cancel</a>
            <button type="submit" class="btn btn-primary me-2" id="btn-submit"><span
                    class="ti-xs ti ti-device-floppy me-1"></span>Save</button>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/jquery-timepicker/jquery-timepicker.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/pickr/pickr.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/block-ui/block-ui.js') }}"></script>
    @endpush


    @push('page-js')
        <script src="{{ asset('assets/js/forms-pickers.js') }}"></script>

        <script>
            $(document).ready(function() {
                // Initialize Select2
                $('.select2').select2({
                    placeholder: function() {
                        return $(this).data('placeholder');
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
