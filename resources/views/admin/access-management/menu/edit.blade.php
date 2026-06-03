<x-app-layout>

    @section('title', 'Edit Menu | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.css') }}" />
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
                ['label' => 'Edit', 'active' => true]
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="row mb-3">
            <div class="col-md mb-4 mb-md-2">
                <div class="card accordion mt-3" id="accordionWithIcon">
                    <h5 class="card-header fw-bold" style="color: #212529">Edit Menu</h5>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('menu.edit.data') }}" id="postForm">
                        @csrf
                        <hr style="margin-bottom: 0.5rem; margin-top: 0;" />
                        <div class="accordion-item active">
                            <h2 class="accordion-header d-flex align-items-center">
                                <button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#accordionWithIcon-1" aria-expanded="true" style="color: #007BFF">
                                    Menu
                                </button>
                            </h2>
                            <div id="accordionWithIcon-1" class="accordion-collapse collapse show">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-12 d-none">
                                            <label class="form-label" for="id">ID<span style="color: red">*</span></label>
                                            <input type="text" id="id" name="id" class="form-control" placeholder="Input ID" value="{{ $menu['id'] }}" readonly />
                                        </div>
                                        <div class="col-md-12">
                                            <label for="name" class="form-label">Menu Name<span class="text-danger">*</span></label>
                                            <input type="text" id="name" name="name" class="form-control" placeholder="Enter Menu Name" value="{{ old('name', $menu['name'] ?? '') }}" required />
                                        </div>

                                        <div class="col-md-6 mt-4">
                                            <label for="code" class="form-label">Code Menu<span class="text-danger">*</span></label>
                                            <input type="text" id="code" name="code" class="form-control" placeholder="Enter Menu Code" value="{{ old('code', $menu['code'] ?? '') }}" required />
                                        </div>

                                        <div class="col-md-6 mt-4">
                                            <label for="parent_id" class="form-label">Parent Menu</label>
                                            <select id="parent_id" name="parent_id" class="select2 form-select" data-allow-clear="true">
                                                <option value="">Select Parent Menu (Optional)</option>
                                                @foreach ($parentMenus as $parentMenu)
                                                    <option value="{{ $parentMenu->id }}" {{ old('parent_id', $menu['parent_id'] ?? '') == $parentMenu->id ? 'selected' : '' }}>
                                                        {{ $parentMenu->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-6 mt-4">
                                            <label for="text_sidebar" class="form-label">Teks Sidebar<span class="text-danger">*</span></label>
                                            <input type="text" id="text_sidebar" name="text_sidebar" class="form-control" placeholder="Enter Sidebar Text" value="{{ old('text_sidebar', $menu['text_sidebar'] ?? '') }}" required />
                                        </div>

                                        <div class="col-md-6 mt-4">
                                            <label for="level_sidebar" class="form-label">Level Sidebar<span class="text-danger">*</span></label>
                                            <input type="number" id="level_sidebar" name="level_sidebar" class="form-control" placeholder="Enter Sidebar Level" value="{{ old('level_sidebar', $menu['level_sidebar'] ?? '') }}" required />
                                        </div>

                                        <div class="col-md-6 mt-4">
                                            <label for="order_number" class="form-label">Nomor Urut</label>
                                            <input type="number" id="order_number" name="order_number" class="form-control" placeholder="Enter Order Number (Optional)" value="{{ old('order_number', $menu['order_number'] ?? '') }}" />
                                        </div>

                                        <div class="col-md-6 mt-4">
                                            <label for="url_path" class="form-label">URL Path</label>
                                            <input type="text" id="url_path" name="url_path" class="form-control" placeholder="Enter URL Path (Optional)" value="{{ old('url_path', $menu['url_path'] ?? '') }}" />
                                        </div>

                                        <div class="col-md-6 mt-4">
                                            <label for="slug" class="form-label">Slug</label>
                                            <input type="text" id="slug" name="slug" class="form-control" placeholder="Enter Slug (Optional)" value="{{ old('slug', $menu['slug'] ?? '') }}" />
                                        </div>

                                        <div class="col-md-6 mt-4">
                                            <label for="icon" class="form-label">Icon</label>
                                            <input type="text" id="icon" name="icon" class="form-control" placeholder="Enter Icon (Optional)" value="{{ old('icon', $menu['icon'] ?? '') }}" />
                                        </div>

                                        <div class="col-md-6 mt-4">
                                            <label class="form-label d-block">Has Page<span class="text-danger">*</span></label>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" id="has_page_yes" name="has_page" value="1" {{ old('has_page', $menu['has_page'] ?? null) == '1' ? 'checked' : '' }}>
                                                <label class="form-check-label" for="has_page_yes">Ya</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" id="has_page_no" name="has_page" value="0" {{ old('has_page', $menu['has_page'] ?? null) == '0' ? 'checked' : '' }}>
                                                <label class="form-check-label" for="has_page_no">Tidak</label>
                                            </div>
                                        </div>

                                        <!-- Hak Akses -->
                                        <div class="col-md-12 mt-4">
                                            <label class="form-label d-block">Hak Akses Menu<span class="text-danger">*</span></label>
                                            @foreach(['create', 'update', 'read', 'delete', 'custom1', 'custom2', 'custom3', 'custom4', 'custom5'] as $access)
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="has_{{ $access }}" name="has_{{ $access }}" value="1" {{ old('has_'.$access, $menu['has_'.$access] ?? false) ? 'checked' : '' }} />
                                                    <label class="form-check-label" for="has_{{ $access }}">{{ ucfirst($access) }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="m-2">
                            <button type="submit" class="btn btn-primary me-2" id="btn-submit">
                                <span class="ti-xs ti ti-device-floppy me-1"></span>Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- / Content -->

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
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
        <script>
            $(document).ready(function() {
                // Initialize Select2
                $('.select2').select2({
                    placeholder: function() {
                        return $(this).data('placeholder');
                    }
                });

                $('#postForm').on('submit', function() {
                    $(this).block({
                        message: '<div class="spinner-border text-primary" role="status"></div>',
                        css: { backgroundColor: "transparent", border: "0" },
                        overlayCSS: { backgroundColor: "#fff", opacity: .8 }
                    });
                });
            });
        </script>
    @endpush

</x-app-layout>
