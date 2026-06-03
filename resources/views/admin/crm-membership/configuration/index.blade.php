<x-app-layout>

    @section('title', 'Membership Configuration | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
        <link rel="stylesheet"
            href="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}">
        <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>

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

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasUpdatePermission = (session('permissions.Membership Configuration.is_update', false) == 1) ||
                (session('permissions.Configuration.is_update', false) == 1);
            $hasDeletePermission = (session('permissions.Membership Configuration.is_delete', false) == 1) ||
                (session('permissions.Configuration.is_delete', false) == 1);
            $hasCreatePermission = (session('permissions.Membership Configuration.is_create', false) == 1) ||
                (session('permissions.Configuration.is_create', false) == 1);
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Settings', 'url' => 'javascript:void(0);'],
                ['label' => 'Membership Configuration', 'active' => true]
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

        <div class="card">
            <div class="card-datatable text-nowrap">
                <table class="table table-bordered" id="table">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Branch</th>
                            <th>Name</th>
                            <th>Rule</th>
                            <th>Default</th>
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

    <div class="modal fade" id="filterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Filter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="selectStatus" class="form-label">Status</label>
                    <select id="selectStatus" class="select2 form-select form-select-lg" data-allow-clear="true">
                        <option value="">All</option>
                        <option value="active"@if ($status == 'active') selected @endif>Active</option>
                        <option value="deleted" @if ($status == 'deleted') selected @endif>Deleted</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal" id="btnResetFilter">Reset</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="btnFilter">Filter</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('crm.membership-configuration.delete.data') }}">
                    @csrf
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong id="membership-name-deleted"></strong>?</p>
                        <input type="text" id="membership-id-deleted" name="membership_configuration_id_deleted" class="form-control d-none" readonly />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Restore</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('crm.membership-configuration.restore.data') }}">
                    @csrf
                    <div class="modal-body">
                        <p>Are you sure you want to restore <strong id="membership-name-restore"></strong>?</p>
                        <input type="text" id="membership-id-restore" name="membership_configuration_id_restored" class="form-control d-none" readonly />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.js') }}"></script>
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
                    scrollX: true,
                    ajax: {
                        url: "{{ route('crm.membership-configuration.index.data') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            status: "{{ $status }}",
                        }
                    },
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false },
                        {
                            data: 'branch_name',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { data: 'name' },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `${row.points_per_step} point(s) / ${row.transaction_amount_step.toLocaleString('id-ID')}`;
                            }
                        },
                        {
                            data: 'is_default',
                            render: function(data) {
                                return data ? '<span class="badge bg-label-primary">Yes</span>' : '<span class="badge bg-label-secondary">No</span>';
                            }
                        },
                        {
                            data: 'deleted_at',
                            render: function(data) {
                                return data == null
                                    ? '<span class="badge bg-label-success">Active</span>'
                                    : '<span class="badge bg-label-danger">Deleted</span>';
                            }
                        },
                        @if($hasAnyActionPermission)
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            render: function(data, type, row) {
                                let dropdown = '<div class="dropdown"><button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical text-primary"></i></button><ul class="dropdown-menu dropdown-menu-end">';
                                @if($hasUpdatePermission)
                                dropdown += `<li><a class="dropdown-item" href="{{ url('crm/membership-configuration/edit') }}/${row.id}"><i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>`;
                                @endif
                                @if($hasDeletePermission)
                                if (row.deleted_at == null) {
                                    dropdown += `<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="${row.id}" data-name="${row.name}"><i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>`;
                                } else {
                                    dropdown += `<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="${row.id}" data-name="${row.name}"><i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>`;
                                }
                                @endif
                                dropdown += '</ul></div>';
                                return dropdown;
                            }
                        }
                        @endif
                    ],
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
                    dom: '<"card-header flex-column flex-md-row"<"head-label text-center"><"dt-action-buttons text-end pt-3 pt-md-0"B>><"row m-0"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                    displayLength: 10,
                    lengthMenu: [7, 10, 25, 50],
                    buttons: [
                        {
                            text: '<i class="ti ti-filter me-sm-1"></i> <span class="d-none d-sm-inline-block">Filter</span>',
                            className: "btn @if ($isFilter == true) btn-warning @else btn-primary @endif",
                            action: function() { $("#filterModal").modal("show"); },
                        },
                        @if($hasCreatePermission)
                        {
                            text: '<i class="ti ti-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">Add Membership</span>',
                            className: "create-new btn btn-primary",
                            action: function() { window.location = '{{ route('crm.membership-configuration.insert.view') }}'; },
                        }
                        @endif
                    ],
                });

                $("div.head-label").html('<h4 class="card-title mb-0">Membership Configuration</h4>');

                $("#btnFilter").click(function() {
                    let status = $('#selectStatus').val();
                    let path = "{{ url('crm/membership-configuration') }}?";
                    if (status !== "") path += 'status=' + status;
                    window.location = path;
                });

                $("#btnResetFilter").click(function() {
                    window.location = '{{ route('crm.membership-configuration.index.view') }}';
                });

                $('#deleteModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    $('#membership-id-deleted').val(button.data('id'));
                    $('#membership-name-deleted').text(button.data('name'));
                });

                $('#restoreModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    $('#membership-id-restore').val(button.data('id'));
                    $('#membership-name-restore').text(button.data('name'));
                });
            });
        </script>
    @endpush

</x-app-layout>
