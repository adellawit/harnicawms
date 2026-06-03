<x-app-layout>

    @section('title', 'Postion | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
        <link rel="stylesheet"
            href="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}">
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>

        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
        <!-- Row Group CSS -->
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-rowgroup-bs5/rowgroup.bootstrap5.css') }}">
        <!-- Form Validation -->
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    @endpush

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-profile.css') }}" />
        <style>
            .breadcrumb-item a:hover {
                color: #212529 !important;
            }
        </style>
    @endpush

    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = session('permissions.Position.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Position.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Position.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Human Resources', 'url' => 'javascript:void(0);'],
                ['label' => 'Position', 'active' => true]
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <!-- DataTable with Buttons -->
        <div class="card">
            <div class="card-datatable text-nowrap">
                <table class="table table-bordered" id="table">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Position</th>
                            <th>Created At</th>
                            <th>Status</th>
                            @if($hasAnyActionPermission)
                            <th>Actions</th>
                            @endif
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

    </div>
    <!-- / Content -->

    <x-modal id="filterModal" title="Filter">
        <div class="mb-3">
            <label for="selectStatus" class="form-label">Status</label>
            <select id="selectStatus" class="select2 form-select form-select-lg" data-allow-clear="true">
                <option value="">All</option>
                <option value="active"@if ($status == 'active') selected @endif>Active</option>
                <option value="deleted" @if ($status == 'deleted') selected @endif>Deleted</option>
            </select>
        </div>
        <x-slot name="footer">
            <button type="button" class="btn btn-label-dark" id="btnResetFilter">Reset</button>
            <button type="button" class="btn btn-primary" id="btnFilter" data-bs-dismiss="modal">Filter</button>
        </x-slot>
    </x-modal>

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('position.delete.data')" confirm-text="Submit">
        <input type="hidden" id="position-id-deleted" name="position_id_deleted" />
        <p class="mb-0">Apakah Anda yakin akan menghapus data <strong id="position-name-deleted"></strong>?</p>
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('position.restore.data')" confirm-text="Submit">
        <input type="hidden" id="position-id-restore" name="position_id_restored" />
        <p class="mb-0">Apakah Anda yakin akan mengembalikan data <strong id="position-name-restore"></strong>?</p>
    </x-confirm-modal>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-responsive/datatables.responsive.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.js') }}"></script>

        <script src="{{ asset('assets/vendor/libs/datatables-buttons/datatables-buttons.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/jszip/jszip.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/pdfmake/pdfmake.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons/buttons.html5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons/buttons.print.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <!-- Flat Picker -->
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
        <!-- Row Group JS -->
        <script src="{{ asset('assets/vendor/libs/datatables-rowgroup/datatables.rowgroup.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-rowgroup-bs5/rowgroup.bootstrap5.js') }}"></script>
        <!-- Form Validation -->
        <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js') }}"></script>
    @endpush


    @push('page-js')
        {{-- <script src="{{ asset('assets/js/pages-profile.js') }}"></script> --}}
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>

        <script>
            $(document).ready(function() {
                $('#table').DataTable({
                        processing: true,
                        serverSide: true,
                        paging: true,
                        scrollX: true,
                        ajax: {
                            "url": "{{ route('position.index.data') }}",
                            "type": "POST",
                            "data": {
                                _token: "{{ csrf_token() }}",
                                "status": "{{ $status }}",
                            }
                        },
                        language: {
                            lengthMenu: "Show _MENU_ entries",
                            zeroRecords: "No data available",
                            info: "Showing _START_ to _END_ of _TOTAL_ entries",
                            infoEmpty: "No entries available",
                            infoFiltered: "(filtered from _MAX_ total entries)",
                            search: "Search:",
                            paginate: {
                                first: "First",
                                last: "Last",
                                next: "Next",
                                previous: "Previous"
                            }
                        },
                        columns: [{
                                data: 'DT_RowIndex',
                                orderable: false,
                                searchable: false,
                            },
                            {
                                data: 'name',
                                orderable: true,
                                searchable: true,
                            },
                            {
                                data: 'created_at',
                                orderable: true,
                                searchable: true,
                                render: function(data, type, row, meta) {
                                    t = new Date(data);
                                    return moment(t).format("DD MMM YYYY - HH:mm");
                                },
                            },
                            {
                                data: 'deleted_at',
                                orderable: true,
                                searchable: true,
                                render: function(data, type, row, meta) {

                                    if (data == null) {
                                        return '<span class="badge bg-label-success">Active</span>';
                                    } else {
                                        return '<span class="badge bg-label-danger">Deleted</span>';
                                    }
                                },
                            },
                            @if($hasAnyActionPermission)
                            {
                                data: null,
                                orderable: false,
                                searchable: false,
                                render: function(data, type, row, meta) {
                                    if (row.id != "{{ auth('web')->user()->id }}") {
                                        var dropdownStart = '<div class="dropdown">' +
                                            '<button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="false">' +
                                            '<i class="ti ti-dots-vertical text-primary"></i>' +
                                            '</button>' +
                                            '<ul class="dropdown-menu dropdown-menu-end">';

                                        var btnEdit = @json($hasUpdatePermission) ? '<li><a class="dropdown-item" href="/human-resources/position/edit/' + row.id + '">' +
                                            '<i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>' : '';

                                        var btnDelete = @json($hasDeletePermission) ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="' +
                                            row.id + '" data-name="' + row.name + '">' +
                                            '<i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>' : '';

                                        var btnRestore = @json($hasDeletePermission) ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="' +
                                            row.id + '" data-name="' + row.name + '">' +
                                            '<i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>' : '';

                                        var dropdownEnd = '</ul></div>';

                                        if (row.deleted_at == null) {
                                            return dropdownStart + btnEdit + btnDelete + dropdownEnd;
                                        } else {
                                            return dropdownStart + btnEdit + btnRestore + dropdownEnd;
                                        }
                                    } else {
                                        return "";
                                    }
                                },
                            }
                            @endif
                        ],
                        // dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                        dom: '<"card-header flex-column flex-md-row"<"head-label text-center"><"dt-action-buttons text-end pt-3 pt-md-0"B>><"row m-0"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                        displayLength: 10,
                        lengthMenu: [7, 10, 25, 50],
                        buttons: [{
                            text: '<i class="ti ti-filter me-sm-1"></i> <span class="d-none d-sm-inline-block" >Filter</span>',
                            className: "btn @if ($isFilter == true) btn-warning @else btn-primary @endif",
                            action: function(e, dt, node, config) {
                                $("#filterModal").modal("show");
                            },
                        }, @if($hasCreatePermission) {
                            text: '<i class="ti ti-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">Add Position</span>',
                            className: "create-new btn btn-primary",
                            action: function(e, dt, node, config) {
                                window.location = 'position/insert';
                            },
                        }, @endif ],
                    }),
                    $("div.head-label").html('<h4 class="card-title mb-0">Position</h4>');

                $("#btnFilter").click(function() {
                    var status = $('#selectStatus').find(':selected').val();

                    var path = "/human-resources/position?";

                    if (status != "") {
                        path = path + 'status=' + status + "&";
                    }

                    window.location = path;
                });

                $("#btnResetFilter").click(function() {
                    window.location = '/position';
                });

                $('#deleteModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    // Update the modal content
                    $('#position-id-deleted').val(id);
                    $('#position-name-deleted').text(name);
                });

                $('#restoreModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    // Update the modal content
                    $('#position-id-restore').val(id);
                    $('#position-name-restore').text(name);
                });
            });
        </script>
    @endpush

</x-app-layout>
