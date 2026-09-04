<x-app-layout>

    @section('title', 'Division | ')

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
            $hasUpdatePermission = session('permissions.Division.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Division.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Division.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Human Resources', 'url' => 'javascript:void(0);'],
                ['label' => 'Division', 'active' => true]
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
                            <th>Division</th>
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

    <!-- Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel1">Filter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="nameBasic" class="form-label">Status</label>
                            <select id="selectStatus" class="select2 form-select form-select-lg"
                                data-allow-clear="true">
                                <option value="">All</option>
                                <option value="active"@if ($status == 'active') selected @endif>Active</option>
                                <option value="deleted" @if ($status == 'deleted') selected @endif>Deleted
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    {{-- <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button> --}}
                    <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal"
                        id="btnResetFilter">Reset</button>
                    <button type="button" class="btn btn-primary" id="btnFilter"
                        data-bs-dismiss="modal">Filter</button>
                </div>
            </div>
        </div>
    </div>
    <!-- / Modal -->


    <!-- Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel1">Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('division.delete.data') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col">
                                <p class="">Apakah Anda yakin akan menghapus data <strong
                                        id="division-name-deleted"></strong>
                                    ?
                                </p>
                                <input type="text" id="division-id-deleted" name="division_id_deleted"
                                    class="form-control d-none" placeholder="Input ID Deleted" readonly />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- / Modal -->

    <!-- Modal -->
    <div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel1">Restore</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('division.restore.data') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col">
                                <p class="">Apakah Anda yakin akan mengembalikan data <strong
                                        id="division-name-restore"></strong>
                                    ?
                                </p>
                                <input type="text" id="division-id-restore" name="division_id_restored"
                                    class="form-control d-none" placeholder="Input ID Restore" readonly />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- / Modal -->

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
                         
                        ajax: {
                            "url": "{{ route('division.index.data') }}",
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

                                        var btnEdit = @json($hasUpdatePermission) ? '<li><a class="dropdown-item" href="/human-resources/division/edit/' + row.id + '">' + '<i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>' : '';

                                        var btnDelete = @json($hasDeletePermission) ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="' +
                                            row.id + '" data-name="' + row.name + '">' +
                                            '<i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>' : '';

                                        var btnRestore = @json($hasDeletePermission) ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="' +
                                            row.id + '" data-name="' + row.name + '">' +
                                            '<i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>' : '';

                                        var dropdownEnd = '</ul></div>';

                                        var actions = '';
                                        if (row.deleted_at == null) {
                                            actions = btnEdit + btnDelete;
                                        } else {
                                            actions = btnEdit + btnRestore;
                                        }
                                        
                                        if (actions.trim() !== '') {
                                            return dropdownStart + actions + dropdownEnd;
                                        } else {
                                            return "";
                                        }
                                    } else {
                                        return "";
                                    }
                                },
                            }
                            @endif
                        ],
                        // dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                        displayLength: 10,
                        lengthMenu: [10, 25, 50, 100],
                        buttons: [{
                            text: '<i class="ti ti-filter me-sm-1"></i> <span class="d-none d-sm-inline-block" >Filter</span>',
                            className: "btn @if ($isFilter == true) btn-warning @else btn-primary @endif",
                            action: function(e, dt, node, config) {
                                $("#filterModal").modal("show");
                            },
                        }, @if($hasCreatePermission) {
                            text: '<i class="ti ti-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">Add Division</span>',
                            className: "create-new btn btn-primary",
                            action: function(e, dt, node, config) {
                                window.location = 'division/insert';
                            },
                        }, @endif ],
                    }),
                    $("div.head-label").html('<h4 class="card-title mb-0">Division</h4>');

                $("#btnFilter").click(function() {
                    var status = $('#selectStatus').find(':selected').val();

                    var path = "/human-resources/division?";

                    if (status != "") {
                        path = path + 'status=' + status + "&";
                    }

                    window.location = path;
                });

                $("#btnResetFilter").click(function() {
                    window.location = '/human-resources/division';
                });

                $('#deleteModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    // Update the modal content
                    $('#division-id-deleted').val(id);
                    $('#division-name-deleted').text(name);
                });

                $('#restoreModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    // Update the modal content
                    $('#division-id-restore').val(id);
                    $('#division-name-restore').text(name);
                });
            });
        </script>
    @endpush

</x-app-layout>

