<x-app-layout>

    @section('title', 'Add Role | ')

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
                ['label' => 'Settings', 'url' => 'javascript:void(0);'],
                ['label' => 'Role', 'url' => route('roles.index.view')],
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
                    <h5 class="card-header fw-bold" style="color: #212529">Add Role</h5>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('roles.insert.data') }}"
                        id="postForm">
                        @csrf
                        <hr style="margin-bottom: 0.5rem; margin-top: 0;" />
                        <div class="accordion-item active">
                            <h2 class="accordion-header d-flex align-items-center">
                                <button type="button" class="accordion-button" data-bs-toggle="collapse"
                                    data-bs-target="#accordionWithIcon-1" aria-expanded="true" style="color: #007BFF">
                                    Role
                                </button>
                            </h2>
                            <div id="accordionWithIcon-1" class="accordion-collapse collapse show">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-6 mt-4">
                                            <label class="form-label" for="name">Role Name<span
                                                    style="color: red">*</span></label>
                                            <input type="text" id="name" name="name" class="form-control"
                                                placeholder="Enter Role Name" value="{{ old('name') }}" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item active mt-3">
                            <h2 class="accordion-header d-flex align-items-center">
                                <button type="button" class="accordion-button" data-bs-toggle="collapse"
                                    data-bs-target="#accordionWithIcon-2" aria-expanded="true" style="color: #007BFF">
                                    Menu Access
                                </button>
                            </h2>
                            <div id="accordionWithIcon-2" class="accordion-collapse collapse show">
                                <div class="accordion-body">
                                    <table class="table table-bordered" id="permissionTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 30%">Menu</th>
                                                <th class="text-center">Create</th>
                                                <th class="text-center">Read</th>
                                                <th class="text-center">Update</th>
                                                <th class="text-center">Delete</th>
                                                <th class="text-center">Custom 1</th>
                                                <th class="text-center">Custom 2</th>
                                                <th class="text-center">Custom 3</th>
                                                <th class="text-center">Custom 4</th>
                                                <th class="text-center">Custom 5</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- All Access Row -->
                                            <tr class="table-primary">
                                                <td><strong>All Access</strong></td>
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input select-all-checkbox" data-perm="create" title="Select All Create">
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input select-all-checkbox" data-perm="read" title="Select All Read">
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input select-all-checkbox" data-perm="update" title="Select All Update">
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input select-all-checkbox" data-perm="delete" title="Select All Delete">
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input select-all-checkbox" data-perm="custom1" title="Select All Custom 1">
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input select-all-checkbox" data-perm="custom2" title="Select All Custom 2">
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input select-all-checkbox" data-perm="custom3" title="Select All Custom 3">
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input select-all-checkbox" data-perm="custom4" title="Select All Custom 4">
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input select-all-checkbox" data-perm="custom5" title="Select All Custom 5">
                                                </td>
                                            </tr>
                                            @forelse ($parentMenus as $parent)
                                                {{-- Parent Menu Row (Level 1) --}}
                                                <tr class="table-secondary">
                                                    <td>
                                                        <strong>
                                                            @if($parent->icon)
                                                                <i class="{{ $parent->icon }} me-1"></i>
                                                            @endif
                                                            {{ $parent->name }}
                                                        </strong>
                                                    </td>
                                                    @foreach (['create', 'read', 'update', 'delete', 'custom1', 'custom2', 'custom3', 'custom4', 'custom5'] as $perm)
                                                    <td class="text-center">
                                                        <input type="checkbox" class="form-check-input parent-checkbox"
                                                               name="permissions[{{ $parent->id }}][{{ $perm }}]"
                                                               value="1"
                                                               data-parent-id="{{ $parent->id }}"
                                                               data-perm="{{ $perm }}"
                                                               {{ old("permissions.{$parent->id}.$perm") ? 'checked' : '' }}>
                                                    </td>
                                                    @endforeach
                                                </tr>

                                                {{-- Children (Level 2) --}}
                                                @foreach ($parent->children as $child)
                                                    @php $hasGrandChildren = $child->relationLoaded('children') && $child->children->count() > 0; @endphp
                                                    <tr class="{{ $hasGrandChildren ? 'table-light' : '' }}">
                                                        <td style="padding-left: 2rem;">
                                                            <span class="text-muted">└</span>
                                                            @if($child->icon)
                                                                <i class="{{ $child->icon }} me-1 text-muted"></i>
                                                            @endif
                                                            {!! $hasGrandChildren ? '<strong>' . e($child->name) . '</strong>' : e($child->name) !!}
                                                        </td>
                                                        @foreach (['create', 'read', 'update', 'delete', 'custom1', 'custom2', 'custom3', 'custom4', 'custom5'] as $perm)
                                                        <td class="text-center">
                                                            <input type="checkbox" class="form-check-input child-checkbox"
                                                                   name="permissions[{{ $child->id }}][{{ $perm }}]"
                                                                   value="1"
                                                                   data-parent-id="{{ $parent->id }}"
                                                                   data-perm="{{ $perm }}"
                                                                   {{ old("permissions.{$child->id}.$perm") ? 'checked' : '' }}>
                                                        </td>
                                                        @endforeach
                                                    </tr>

                                                    {{-- Grandchildren (Level 3) --}}
                                                    @if($hasGrandChildren)
                                                        @foreach ($child->children as $grandChild)
                                                            <tr>
                                                                <td style="padding-left: 4rem;">
                                                                    <span class="text-muted">└</span>
                                                                    @if($grandChild->icon)
                                                                        <i class="{{ $grandChild->icon }} me-1 text-muted"></i>
                                                                    @endif
                                                                    {{ $grandChild->name }}
                                                                </td>
                                                                @foreach (['create', 'read', 'update', 'delete', 'custom1', 'custom2', 'custom3', 'custom4', 'custom5'] as $perm)
                                                                <td class="text-center">
                                                                    <input type="checkbox" class="form-check-input child-checkbox"
                                                                           name="permissions[{{ $grandChild->id }}][{{ $perm }}]"
                                                                           value="1"
                                                                           data-parent-id="{{ $parent->id }}"
                                                                           data-perm="{{ $perm }}"
                                                                           {{ old("permissions.{$grandChild->id}.$perm") ? 'checked' : '' }}>
                                                                </td>
                                                                @endforeach
                                                            </tr>
                                                        @endforeach
                                                    @endif
                                                @endforeach
                                            @empty
                                                <tr>
                                                    <td colspan="10" class="text-center">No menus found.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <hr style="margin: 0.5rem 0;" />
                        <div class="m-2">
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
                <a href="{{ route('roles.index.view') }}" type="button" class="btn btn-outline-dark me-2"><span
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
            $(document).ready(function() {
                // Select All functionality for each permission column
                $('.select-all-checkbox').on('change', function() {
                    const perm = $(this).data('perm');

                    if ($(this).is(':checked')) {
                        // Check all checkboxes in this column
                        $('input[data-perm="' + perm + '"]').not('.select-all-checkbox').prop('checked', true);
                    } else {
                        // Uncheck all checkboxes in this column
                        $('input[data-perm="' + perm + '"]').not('.select-all-checkbox').prop('checked', false);
                    }
                });

                // Update select all checkbox state when individual checkboxes change
                $('input[data-perm]').not('.select-all-checkbox').on('change', function() {
                    const perm = $(this).data('perm');
                    const $selectAll = $('.select-all-checkbox[data-perm="' + perm + '"]');
                    const $allCheckboxes = $('input[data-perm="' + perm + '"]').not('.select-all-checkbox');
                    const $checkedBoxes = $allCheckboxes.filter(':checked');

                    // Update select all checkbox based on individual checkboxes
                    if ($allCheckboxes.length === $checkedBoxes.length) {
                        $selectAll.prop('checked', true);
                        $selectAll.prop('indeterminate', false);
                    } else if ($checkedBoxes.length === 0) {
                        $selectAll.prop('checked', false);
                        $selectAll.prop('indeterminate', false);
                    } else {
                        $selectAll.prop('checked', false);
                        $selectAll.prop('indeterminate', true);
                    }
                });

                // Initialize select all checkbox states on page load
                $('.select-all-checkbox').each(function() {
                    const perm = $(this).data('perm');
                    const $allCheckboxes = $('input[data-perm="' + perm + '"]').not('.select-all-checkbox');
                    const $checkedBoxes = $allCheckboxes.filter(':checked');

                    if ($allCheckboxes.length === $checkedBoxes.length && $allCheckboxes.length > 0) {
                        $(this).prop('checked', true);
                    } else if ($checkedBoxes.length > 0) {
                        $(this).prop('indeterminate', true);
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
