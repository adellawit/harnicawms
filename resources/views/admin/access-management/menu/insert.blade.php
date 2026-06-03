<x-app-layout>

    @section('title', 'Tambah Menu | ')

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
                ['label' => 'Settings', 'url' => 'javascript:void(0);'],
                ['label' => 'Menu', 'url' => route('menu.index.view')],
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
            <div class="col-md mb-4 mb-md-2">
                <div class="card accordion mt-3" id="accordionWithIcon">
                    <h5 class="card-header fw-bold" style="color: #212529">Add Menu</h5>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('menu.insert.data') }}"
                        id="postForm">
                        @csrf
                        <hr style="margin-bottom: 0.5rem; margin-top: 0;" />
                        <div class="accordion-item active">
                            <h2 class="accordion-header d-flex align-items-center">
                                <button type="button" class="accordion-button" data-bs-toggle="collapse"
                                    data-bs-target="#accordionWithIcon-1" aria-expanded="true" style="color: #007BFF">
                                    Menu
                                </button>
                            </h2>
                            <div id="accordionWithIcon-1" class="accordion-collapse collapse show">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-6 mt-4">
                                            <label class="form-label" for="name">Menu Name<span
                                                    style="color: red">*</span></label>
                                            <input type="text" id="name" name="name" class="form-control"
                                                placeholder="Enter Menu Name" value="{{ old('name') }}" />
                                        </div>
                                        <div class="col-md-6 mt-4">
                                            <label class="form-label" for="code">Code Menu<span
                                                    style="color: red">*</span></label>
                                            <input type="text" id="code" name="code" class="form-control"
                                                placeholder="Enter Menu Code" value="{{ old('code') }}" />
                                        </div>
                                        <div class="col-md-6 mt-4">
                                            <label class="form-label" for="parent_id">Parent Menu</label>
                                            <select id="parent_id" name="parent_id" class="select2 form-select" data-allow-clear="true">
                                                <option value="">Select Parent Menu (Optional)</option>
                                                @foreach ($parentMenus as $parentMenu)
                                                    <option value="{{ $parentMenu->id }}" {{ old('parent_id') == $parentMenu->id ? 'selected' : '' }}>
                                                        {{ $parentMenu->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 mt-4">
                                            <label class="form-label" for="text_sidebar">Teks Sidebar<span
                                                    style="color: red">*</span></label>
                                            <input type="text" id="text_sidebar" name="text_sidebar" class="form-control"
                                                placeholder="Enter Sidebar Text" value="{{ old('text_sidebar') }}" />
                                        </div>
                                        <div class="col-md-6 mt-4">
                                            <label class="form-label" for="level_sidebar">Level Sidebar<span
                                                    style="color: red">*</span></label>
                                            <input type="number" id="level_sidebar" name="level_sidebar"
                                                class="form-control" placeholder="Enter Sidebar Level"
                                                value="{{ old('level_sidebar') }}" />
                                        </div>
                                        <div class="col-md-6 mt-4">
                                            <label class="form-label" for="order_number">Nomor Urut</label>
                                            <input type="number" id="order_number" name="order_number" class="form-control"
                                                placeholder="Enter Order Number (Optional)" value="{{ old('order_number') }}" />
                                        </div>
                                        <div class="col-md-6 mt-4">
                                            <label class="form-label" for="url_path">URL Path</label>
                                            <input type="text" id="url_path" name="url_path" class="form-control"
                                                placeholder="Enter URL Path (Optional)" value="{{ old('url_path') }}" />
                                        </div>
                                        <div class="col-md-6 mt-4">
                                            <label class="form-label" for="slug">Slug</label>
                                            <input type="text" id="slug" name="slug" class="form-control"
                                                placeholder="Enter Slug (Optional)" value="{{ old('slug') }}" />
                                        </div>
                                        <div class="col-md-6 mt-4">
                                            <label class="form-label" for="icon">Icon</label>
                                            <input type="text" id="icon" name="icon" class="form-control"
                                                placeholder="Enter Icon (Optional)" value="{{ old('icon') }}" />
                                        </div>
                                        <div class="col-md-6 mt-4">
                                            <label class="form-label d-block">Has Page<span style="color: red">*</span></label>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" id="has_page_yes" name="has_page" value="1" {{ old('has_page') == '1' ? 'checked' : '' }}>
                                                <label class="form-check-label" for="has_page_yes">Ya</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" id="has_page_no" name="has_page" value="0" {{ old('has_page') == '0' ? 'checked' : '' }}>
                                                <label class="form-check-label" for="has_page_no">Tidak</label>
                                            </div>
                                        </div>
                                        <div class="col-md-12 mt-4">
                                            <label class="form-label d-block">Hak Akses Menu<span style="color: red">*</span></label>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="has_create" name="has_create" value="1" {{ old('has_create') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="has_create">Create</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="has_update" name="has_update" value="1" {{ old('has_update') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="has_update">Update</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="has_read" name="has_read" value="1" {{ old('has_read') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="has_read">Read</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="has_delete" name="has_delete" value="1" {{ old('has_delete') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="has_delete">Delete</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="has_custom1" name="has_custom1" value="1" {{ old('has_custom1') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="has_custom1">Custom 1</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="has_custom2" name="has_custom2" value="1" {{ old('has_custom2') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="has_custom2">Custom 2</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="has_custom3" name="has_custom3" value="1" {{ old('has_custom3') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="has_custom3">Custom 3</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="has_custom4" name="has_custom4" value="1" {{ old('has_custom4') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="has_custom4">Custom 4</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="has_custom5" name="has_custom5" value="1" {{ old('has_custom5') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="has_custom5">Custom 5</label>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <hr style="margin: 0.5rem 0;" />
                        <div class="m-2">
                            <button type="submit" class="btn btn-primary me-2"><span
                                    class="ti-xs ti ti-device-floppy me-1"></span>Save</button>
                            <a href="{{ route('menu.index.view') }}" class="btn btn-outline-dark"><span
                                    class="ti-xs ti ti-x me-1"></span>Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
    <!-- / Content -->

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.js') }}"></script>
    @endpush


    @push('page-js')
        <script>
            $(document).ready(function() {
                // Initialize Select2
                $('.select2').select2({
                    placeholder: function() {
                        return $(this).data('placeholder');
                    }
                });

                $('#postForm').submit(function(e) {
                    $("#postForm").block({
                        message: '<div class="spinner-border text-primary" role="status"></div>',
                        timeout: 1000,
                        css: {
                            backgroundColor: "transparent",
                            border: "0"
                        },
                        overlayCSS: {
                            backgroundColor: "#fff",
                            opacity: .8
                        }
                    });
                });
            });
        </script>
    @endpush

</x-app-layout>
