<x-app-layout>

    @section('title', 'Role | ')

    @push('vendor-css')
        <!-- CSS Vendor -->
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-rowgroup-bs5/rowgroup.bootstrap5.css') }}">
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
            $hasUpdatePermission = session('permissions.Role.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Role.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Role.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Settings', 'url' => 'javascript:void(0);'],
                ['label' => 'Role', 'active' => true]
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
                            <th>Role</th>
                            <th>Created By</th>
                            <th>Status</th> <!-- Kolom Status Ditambahkan -->
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

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('roles.delete.data')" confirm-text="Submit">
        <input type="hidden" id="role-id-deleted" name="role_id_deleted" />
        <p class="mb-0">Apakah Anda yakin akan menghapus data <strong id="role-name-deleted"></strong>?</p>
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('roles.restore.data')" confirm-text="Submit">
        <input type="hidden" id="role-id-restore" name="role_id_restored" />
        <p class="mb-0">Apakah Anda yakin akan mengembalikan data <strong id="role-name-restore"></strong>?</p>
    </x-confirm-modal>

    <!-- Modal dan Script lainnya tetap sama -->

    @push('vendor-js')
        <!-- JS Vendor -->
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
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-rowgroup/datatables.rowgroup.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-rowgroup-bs5/rowgroup.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js') }}"></script>
    @endpush


    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>

        <script>
            $(document).ready(function() {
                $('#table').DataTable({
                        processing: true,
                        serverSide: true,
                        paging: true,
                         
                        ajax: {
                            "url": "{{ route('roles.index.data') }}",
                            "type": "POST",
                            "data": {
                                _token: "{{ csrf_token() }}",
                                "status": "{{ $status }}",
                            }
                        },
                        language: {
                            lengthMenu: "Tampilkan _MENU_",
                            zeroRecords: "Tidak ada data yang cocok dengan pencarian",
                            info: "Menampilkan _START_–_END_ dari _TOTAL_ data",
                            infoEmpty: "Tidak ada data",
                            infoFiltered: "(disaring dari _MAX_ data)",
                            search: "",
                            paginate: {
                                first: "Awal",
                                last: "Akhir",
                                next: "›",
                                previous: "‹"
                            }
                        },
                        columns: [
                            {
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
                                data: 'created_by',
                                orderable: true,
                                searchable: true,
                            },
                            {
                                data: 'deleted_by',
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

                                        var btnEdit = @json($hasUpdatePermission) ? '<li><a class="dropdown-item" href="{{ url("access-management/roles/edit") }}/' + row.id + '">' +
                                            '<i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>' : '';

                                        var btnDelete = @json($hasDeletePermission) ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="' +
                                            row.id + '" data-name="' + row.name + '">' +
                                            '<i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>' : '';

                                        var btnRestore = @json($hasDeletePermission) ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="' +
                                            row.id + '" data-name="' + row.name + '">' +
                                            '<i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>' : '';

                                        var dropdownEnd = '</ul></div>';

                                        if (row.deleted_by == null) {
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
                        displayLength: 10,
                        lengthMenu: [10, 25, 50, 100],
                        buttons: [
                            {
                                text: '<i class="ti ti-filter me-sm-1"></i> <span class="d-none d-sm-inline-block">Filter</span>',
                                className: "btn @if ($isFilter == true) btn-warning @else btn-primary @endif",
                                action: function(e, dt, node, config) {
                                    $("#filterModal").modal("show");
                                },
                            },
                            @if($hasCreatePermission)
                            {
                                text: '<i class="ti ti-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">Add Role</span>',
                                className: "create-new btn btn-primary",
                                action: function(e, dt, node, config) {
                                    window.location = '{{ route("roles.insert.view") }}';
                                },
                            },
                            @endif
                        ],
                    }),
                    $("div.head-label").html('<h4 class="card-title mb-0">Role</h4>');

                $("#btnFilter").click(function() {
                    var status = $('#selectStatus').find(':selected').val();

                    var path = "{{ url("access-management/roles") }}?";

                    if (status != "") {
                        path = path + 'status=' + status + "&";
                    }

                    window.location = path;
                });

                $("#btnResetFilter").click(function() {
                    window.location = '{{ route("roles.index.view") }}';
                });

                $('#deleteModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    // Update the modal content
                    $('#role-id-deleted').val(id);
                    $('#role-name-deleted').text(name);
                });

                $('#restoreModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    // Update the modal content
                    $('#role-id-restore').val(id);
                    $('#role-name-restore').text(name);
                });
            });
        </script>
    @endpush

</x-app-layout>
