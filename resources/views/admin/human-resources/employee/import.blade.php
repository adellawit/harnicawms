<x-app-layout>

    @section('title', 'Upload Data Employee | ')

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
                ['label' => 'Import', 'active' => true]
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
                    <form method="POST" enctype="multipart/form-data" action="{{ route('users.import.data') }}" id="postForm">
                        @csrf
                        <h5 class="card-header d-flex justify-content-between align-items-center" style="color: #212529">
                            <span>File input</span>

                            <a href="{{ route('users.export.data') }}" class="btn btn-success">
                                Download Template
                            </a>
                        </h5>
                        <div class="card-body">
                            <div class="mb-3">
                                <input class="form-control" type="file" id="file" name="file" required>
                            </div>
                        </div>
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
                <button type="submit" class="btn btn-primary me-2" id="btn-submit"><span
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
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
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
            $(document).ready(function () {
                $('#postForm').on('keypress', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        $(this).submit();
                    }
                });

                $('#btn-submit').on('click', function (e) {
                    $('#postForm').submit();
                });

                $('#postForm').submit(function (e) {
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
