<x-app-layout>

    @section('title', 'Employee | ')

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
            $hasUpdatePermission = session('permissions.Employee.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Employee.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Employee.is_create', false) == 1;
            $isSuperAdmin = auth('web')->user()->is_super_admin || auth('web')->user()->role_id === '147c8a8e-52dc-4a79-a8ce-acb612b6e484';
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission || $isSuperAdmin;

            $branches = \App\Models\BusinessUnit::where('is_active', true)
                ->where('type_code', 'BRANCH')
                ->orderBy('name')
                ->get(['id', 'name']);
            $defaultBranch = auth('web')->user()->current_business_unit_id;
            $filterBranchId = request('branch_id', $defaultBranch);
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Human Resources'],
                ['label' => 'Employee', 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        @if (session('warning'))
            <x-alert type="danger" class="mb-3">{{ session('warning') }}</x-alert>
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
                            <th>Employee</th>
                            <th>Employee Code</th>
                            <th>Jabatan</th>
                            <th>Employment Status</th>
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
        <div class="row">
            <div class="col-md-12 mb-3">
                <label for="selectBranch" class="form-label">Branch</label>
                <select id="selectBranch" class="select2-modal form-select form-select-lg" data-allow-clear="true">
                    <option value="">All Branch</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @if($filterBranchId === $b->id) selected @endif>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-12 mb-3">
                <label for="selectStatus" class="form-label">Status</label>
                <select id="selectStatus" class="select2-modal form-select form-select-lg" data-allow-clear="true">
                    <option value="">All</option>
                    <option value="active"@if ($status == 'active') selected @endif>Active</option>
                    <option value="deleted" @if ($status == 'deleted') selected @endif>Deleted</option>
                </select>
            </div>
            <div class="col-md-12 mb-3">
                <label for="selectEmploymentStatus" class="form-label">Employment Status</label>
                <select id="selectEmploymentStatus" class="select2-modal form-select form-select-lg" data-allow-clear="true">
                    <option value="">All</option>
                    <option value="Permanent" @if (request('employment_status') == 'Permanent') selected @endif>Permanent</option>
                    <option value="Contract" @if (request('employment_status') == 'Contract') selected @endif>Contract</option>
                    <option value="Probation" @if (request('employment_status') == 'Probation') selected @endif>Probation</option>
                    <option value="Intern" @if (request('employment_status') == 'Intern') selected @endif>Intern</option>
                    <option value="Freelance" @if (request('employment_status') == 'Freelance') selected @endif>Freelance</option>
                </select>
            </div>
            <div class="col-md-12 mb-3">
                <label for="selectEmployeeStatus" class="form-label">Employee Status</label>
                <select id="selectEmployeeStatus" class="select2-modal form-select form-select-lg" data-allow-clear="true">
                    <option value="">All</option>
                    <option value="Active" @if (request('employee_status') == 'Active') selected @endif>Active</option>
                    <option value="Inactive" @if (request('employee_status') == 'Inactive') selected @endif>Inactive</option>
                    <option value="Resigned" @if (request('employee_status') == 'Resigned') selected @endif>Resigned</option>
                    <option value="Terminated" @if (request('employee_status') == 'Terminated') selected @endif>Terminated</option>
                </select>
            </div>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn-label-dark" id="btnResetFilter">Reset</button>
            <button type="button" class="btn btn-primary" id="btnFilter" data-bs-dismiss="modal">Filter</button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-modal id="deleteModal" title="Delete" :action="route('users.delete.data')" confirmText="Submit">
        <p>Apakah Anda yakin akan menghapus data <strong id="user-name-deleted"></strong>?</p>
        <input type="hidden" id="user-id-deleted" name="user_id_deleted" />
    </x-confirm-modal>

    <x-confirm-modal id="restoreModal" title="Restore" :action="route('users.restore.data')" confirmText="Submit">
        <p>Apakah Anda yakin akan mengembalikan data <strong id="user-name-restore"></strong>?</p>
        <input type="hidden" id="user-id-restore" name="user_id_restored" />
    </x-confirm-modal>

    @if($isSuperAdmin)
    <div class="modal fade" id="loginAsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Login As</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('users.login-as') }}">
                    @csrf
                    <div class="modal-body">
                        <p>Apakah Anda yakin ingin login sebagai <strong id="user-name-loginas"></strong>?</p>
                        <p class="text-muted small mb-0">Anda akan dialihkan ke dashboard dengan akun tersebut. Untuk kembali ke akun Anda, gunakan tombol "Kembali ke Akun Saya" yang akan muncul di bagian atas halaman.</p>
                        <input type="hidden" id="user-id-loginas" name="user_id" />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">
                            <i class="ti ti-switch-horizontal me-1"></i>Login As
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

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
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>

        <script>
            $(document).ready(function() {
                $('#table').DataTable({
                        processing: true,
                        serverSide: true,
                        paging: true,
                         
                        ajax: {
                            "url": "{{ route('users.index.data') }}",
                            "type": "POST",
                            "data": {
                                _token: "{{ csrf_token() }}",
                                "status": "{{ $status }}",
                                "branch_id": "{{ $branchId ?? $defaultBranch }}",
                                "employment_status": "{{ request('employment_status') }}",
                                "employee_status": "{{ request('employee_status') }}",
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
                                data: null,
                                orderable: false,
                                searchable: true,
                                render: function(data, type, row, meta) {
                                    var n = row.fullname,
                                        i = row.username,
                                        o = row.url_image;

                                    return '<div class="d-flex justify-content-start align-items-center user-name"><div class="avatar-wrapper"><div class="' +
                                        (o ? 'avatar-datatable' : 'avatar') + ' avatar-sm me-3">' +
                                        (o ? '<img src="' + o +
                                            '" alt="Avatar" class="rounded-circle avatar-datatable-image">' :
                                            '<span class="avatar-initial rounded-circle bg-label-' + [
                                                "success", "danger", "warning", "info", "primary",
                                                "secondary"
                                            ][Math.floor(6 * Math.random())] + '">' + (o = (((o = n.match(
                                                /\b\w/g) || []).shift() || "") + (
                                                o.pop() || "")).toUpperCase()) + "</span>") +
                                        '</div></div><div class="d-flex flex-column"><a class="text-body text-truncate"><span class="fw-semibold">' +
                                        n +
                                        '</span></a><small class="text-muted">' + i +
                                        "</small></div></div>";
                                },
                            },
                            {
                                data: 'employee_code',
                                orderable: true,
                                searchable: true,
                            },
                            {
                                data: 'position',
                                orderable: true,
                                searchable: true,
                            },
                            {
                                data: 'employment_status',
                                orderable: true,
                                searchable: true,
                                render: function(data, type, row, meta) {
                                    var badges = {
                                        'Permanent': 'success',
                                        'Contract': 'primary',
                                        'Probation': 'info',
                                        'Intern': 'warning',
                                        'Freelance': 'secondary'
                                    };
                                    var badge = badges[data] || 'secondary';
                                    return '<span class="badge bg-label-' + badge + '">' + (data || '-') + '</span>';
                                },
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
                                        var isSelf = row.id == "{{ auth('web')->user()->id }}";

                                        var dropdownStart = '<div class="dropdown">' +
                                            '<button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="false">' +
                                            '<i class="ti ti-dots-vertical text-primary"></i>' +
                                            '</button>' +
                                            '<ul class="dropdown-menu dropdown-menu-end">';

                                        var btnDetail = '<li><a class="dropdown-item" href="/human-resources/employee/detail/' + row.id + '">' +
                                            '<i class="ti ti-eye me-2 text-primary"></i>Detail</a></li>';

                                        var btnEdit = @json($hasUpdatePermission) ? '<li><a class="dropdown-item" href="/human-resources/employee/edit/' + row.id + '">' +
                                            '<i class="ti ti-pencil me-2 text-warning"></i>Edit</a></li>' : '';

                                        var btnDelete = (!isSelf && @json($hasDeletePermission)) ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="' +
                                            row.id + '" data-name="' + row.fullname + '">' +
                                            '<i class="ti ti-trash me-2 text-danger"></i>Delete</button></li>' : '';

                                        var btnRestore = @json($hasDeletePermission) ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="' +
                                            row.id + '" data-name="' + row.fullname + '">' +
                                            '<i class="ti ti-refresh me-2 text-success"></i>Restore</button></li>' : '';

                                        var btnLoginAs = (!isSelf && @json($isSuperAdmin)) ? '<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#loginAsModal" data-id="' +
                                            row.id + '" data-name="' + row.fullname + '">' +
                                            '<i class="ti ti-switch-horizontal me-2 text-info"></i>Login As</button></li>' : '';

                                        var dropdownEnd = '</ul></div>';

                                        if (row.deleted_at == null) {
                                            return dropdownStart + btnDetail + btnEdit + btnDelete + btnLoginAs + dropdownEnd;
                                        } else {
                                            return dropdownStart + btnDetail + btnEdit + btnRestore + dropdownEnd;
                                        }
                                },
                            }
                            @endif
                        ],
                        displayLength: 10,
                        lengthMenu: [10, 25, 50, 100],
                        buttons: [{
                            text: '<i class="ti ti-filter me-sm-1"></i> <span class="d-none d-sm-inline-block" >Filter</span>',
                            className: "btn @if ($isFilter == true) btn-warning @else btn-primary @endif",
                            action: function(e, dt, node, config) {
                                $("#filterModal").modal("show");
                            },
                        }, @if($hasCreatePermission) {
                            text: '<i class="ti ti-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">Add</span>',
                            className: "create-new btn btn-primary",
                            action: function(e, dt, node, config) {
                                window.location = '/human-resources/employee/insert';
                            },
                        }, @endif ],
                    }),
                    $("div.head-label").html('<h4 class="card-title mb-0">Employee</h4>');

                $('#filterModal').on('shown.bs.modal', function () {
                    $('#selectBranch, #selectStatus, #selectEmploymentStatus, #selectEmployeeStatus').each(function () {
                        if (!$(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2({
                                dropdownParent: $('#filterModal'),
                                placeholder: 'All',
                                allowClear: true,
                                width: '100%'
                            });
                        }
                    });
                });

                $("#btnFilter").click(function() {
                    var status = $('#selectStatus').find(':selected').val();
                    var employmentStatus = $('#selectEmploymentStatus').find(':selected').val();
                    var employeeStatus = $('#selectEmployeeStatus').find(':selected').val();
                    var branchId = $('#selectBranch').find(':selected').val();

                    var params = [];
                    if (status) params.push('status=' + encodeURIComponent(status));
                    if (employmentStatus) params.push('employment_status=' + encodeURIComponent(employmentStatus));
                    if (employeeStatus) params.push('employee_status=' + encodeURIComponent(employeeStatus));
                    if (branchId) params.push('branch_id=' + encodeURIComponent(branchId));

                    var path = "/human-resources/employee";
                    if (params.length) path += '?' + params.join('&');

                    window.location = path;
                });

                $("#btnResetFilter").click(function() {
                    window.location = '/human-resources/employee';
                });

                $('#deleteModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    // Update the modal content
                    $('#user-id-deleted').val(id);
                    $('#user-name-deleted').text(name);
                });

                $('#restoreModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    // Update the modal content
                    $('#user-id-restore').val(id);
                    $('#user-name-restore').text(name);
                });

                @if($isSuperAdmin)
                $('#loginAsModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    $('#user-id-loginas').val(id);
                    $('#user-name-loginas').text(name);
                });
                @endif
            });
        </script>
    @endpush

</x-app-layout>
