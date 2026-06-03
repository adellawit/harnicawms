<x-app-layout>

    @section('title', 'Change Password | ')

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
                ['label' => 'Change Password', 'active' => true]
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

                <!-- Change Password -->
                <div class="card mb-4">
                    <h5 class="card-header">Change Password</h5>
                    <div class="card-body">
                        <form method="POST" action="{{ route('profile.change-password') }}" id="postForm">
                            @csrf
                            <div class="row">
                                <div class="mb-3 col-md-6 form-password-toggle">
                                    <label class="form-label" for="currentPassword">Current Password</label>
                                    <div class="input-group input-group-merge">
                                        <input class="form-control" type="password" name="old_password"
                                            id="currentPassword"
                                            placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" />
                                        <span class="input-group-text cursor-pointer"><i
                                                class="ti ti-eye-off"></i></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="mb-3 col-md-6 form-password-toggle">
                                    <label class="form-label" for="newPassword">New Password</label>
                                    <div class="input-group input-group-merge">
                                        <input class="form-control" type="password" id="newPassword" name="new_password"
                                            placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" />
                                        <span class="input-group-text cursor-pointer"><i
                                                class="ti ti-eye-off"></i></span>
                                    </div>
                                    <div class="mt-1">
                                        <small id="newPasswordValidation" class="d-none"></small>
                                    </div>
                                </div>

                                <div class="mb-3 col-md-6 form-password-toggle">
                                    <label class="form-label" for="confirmPassword">Confirm New Password</label>
                                    <div class="input-group input-group-merge">
                                        <input class="form-control" type="password" name="new_password_confirmation"
                                            id="confirmPassword"
                                            placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" />
                                        <span class="input-group-text cursor-pointer"><i
                                                class="ti ti-eye-off"></i></span>
                                    </div>
                                    <div class="mt-1">
                                        <small id="confirmPasswordValidation" class="d-none"></small>
                                    </div>
                                </div>
                                <div class="col-12 mb-4">
                                    <h6>Password Requirements:</h6>
                                    <ul class="ps-3 mb-0">
                                        <li class="mb-1">Minimum 3 characters</li>
                                    </ul>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <!--/ Change Password -->
            </div>
        </div>
    </div>
    <!-- / Content -->

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

                // Real-time validation for new password
                $('#newPassword').on('input', function() {
                    const password = $(this).val();
                    const validationDiv = $('#newPasswordValidation');

                    if (password.length > 0) {
                        validationDiv.removeClass('d-none');
                        if (password.length >= 3) {
                            validationDiv.text('✓ Valid').removeClass('text-danger').addClass('text-success');
                        } else {
                            validationDiv.text('✗ Password must be at least 3 characters').removeClass('text-success').addClass('text-danger');
                        }
                    } else {
                        validationDiv.addClass('d-none');
                    }

                    // Also check confirm password match
                    checkPasswordMatch();
                });

                // Real-time validation for confirm password
                $('#confirmPassword').on('input', function() {
                    checkPasswordMatch();
                });

                function checkPasswordMatch() {
                    const newPassword = $('#newPassword').val();
                    const confirmPassword = $('#confirmPassword').val();
                    const validationDiv = $('#confirmPasswordValidation');

                    if (confirmPassword.length > 0) {
                        validationDiv.removeClass('d-none');
                        if (confirmPassword === newPassword && newPassword.length >= 3) {
                            validationDiv.text('✓ Passwords match').removeClass('text-danger').addClass('text-success');
                        } else if (confirmPassword !== newPassword) {
                            validationDiv.text('✗ Passwords do not match').removeClass('text-success').addClass('text-danger');
                        } else {
                            validationDiv.text('✗ Password must be at least 3 characters').removeClass('text-success').addClass('text-danger');
                        }
                    } else {
                        validationDiv.addClass('d-none');
                    }
                }

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
