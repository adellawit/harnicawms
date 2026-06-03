<x-app-layout>

    @section('title', 'Company | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    @endpush

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-profile.css') }}" />
        <style>
            .breadcrumb-item a:hover {
                color: #212529 !important;
            }
            .company-table th, .company-table td {
                vertical-align: middle;
            }
            .company-table th:nth-last-child(1),
            .company-table td:nth-last-child(1) {
                width: 180px;
                white-space: nowrap;
            }
            .company-table th:nth-child(2),
            .company-table td:nth-child(2) {
                white-space: nowrap;
                width: auto;
            }
        </style>
    @endpush

    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Business', 'url' => 'javascript:void(0);'],
                ['label' => 'Company', 'active' => true]
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

        <!-- Company Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Company Management</h5>
                <div>
                    <button type="button" class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    @permission('Company', 'is_create')
                    <a href="{{ route('company.insert.view') }}" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus me-1"></i> Add Company
                    </a>
                    @endpermission
                </div>
            </div>
            <div class="card-body">
                @php
                    // Check if user has any action permissions for Company
                    $hasUpdatePermission = session('permissions.Company.is_update', false) == 1;
                    $hasDeletePermission = session('permissions.Company.is_delete', false) == 1;
                    $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
                @endphp
                <div class="table-responsive">
                    <table class="table table-bordered company-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px" class="text-center">No</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Brand Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th class="text-center">Active</th>
                                @if($hasAnyActionPermission)
                                <th class="text-center">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @php $rowNumber = 1; @endphp
                            @forelse ($parentCompanies as $parent)
                                {{-- Parent Holding Row --}}
                                <tr class="table-secondary">
                                    <td class="text-center"></td>
                                    <td colspan="{{ $hasAnyActionPermission ? 7 : 6 }}">
                                        <strong>
                                            <span class="badge bg-label-primary me-1">HOLDING</span>
                                            {{ $parent->name }}
                                        </strong>
                                        @if($parent->children->count() > 0)
                                            <span class="badge bg-label-secondary ms-2">{{ $parent->children->count() }} Companies</span>
                                        @endif
                                    </td>
                                </tr>

                                {{-- Company Rows under Holding --}}
                                @if($parent->children->count() > 0)
                                    @foreach ($parent->children as $company)
                                        <tr>
                                            <td class="text-center">{{ $rowNumber++ }}</td>
                                            <td>
                                                <span class="text-muted">└</span>
                                                {{ $company->name }}
                                            </td>
                                            <td><code>{{ $company->code ?? '-' }}</code></td>
                                            <td>{{ $company->brand_name ?? '-' }}</td>
                                            <td>{{ $company->email ?? '-' }}</td>
                                            <td>{{ $company->phone ?? '-' }}</td>
                                            <td class="text-center">
                                                @if($company->is_active)
                                                    <i class="ti ti-check text-success"></i>
                                                @else
                                                    <i class="ti ti-x text-muted"></i>
                                                @endif
                                            </td>
                                            @if($hasAnyActionPermission)
                                            <td class="text-center">
                                                @if($company->deleted_at)
                                                    @permission('Company', 'is_delete')
                                                    <button type="button" class="btn btn-sm btn-label-success d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="{{ $company->id }}" data-name="{{ $company->name }}">
                                                        <i class="ti ti-refresh me-1"></i> Restore
                                                    </button>
                                                    @endpermission
                                                @else
                                                    @permission('Company', 'is_update')
                                                    <a href="{{ route('company.edit.view', $company->id) }}" class="btn btn-sm btn-label-warning d-inline-flex align-items-center me-1">
                                                        <i class="ti ti-pencil me-1"></i> Edit
                                                    </a>
                                                    @endpermission
                                                    @permission('Company', 'is_delete')
                                                    <button type="button" class="btn btn-sm btn-label-danger d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="{{ $company->id }}" data-name="{{ $company->name }}">
                                                        <i class="ti ti-trash me-1"></i> Delete
                                                    </button>
                                                    @endpermission
                                                @endif
                                            </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="{{ $hasAnyActionPermission ? 8 : 7 }}" class="text-center text-muted">No companies under this holding</td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="{{ $hasAnyActionPermission ? 8 : 7 }}" class="text-center">No companies found. Please add holdings first.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
    <!-- / Content -->

    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Filter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="selectStatus" class="form-label">Status</label>
                            <select id="selectStatus" class="select2 form-select form-select-lg" data-allow-clear="true">
                                <option value="">All</option>
                                <option value="active" @if ($status == 'active') selected @endif>Active</option>
                                <option value="deleted" @if ($status == 'deleted') selected @endif>Deleted</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal" id="btnResetFilter">Reset</button>
                    <button type="button" class="btn btn-primary" id="btnFilter" data-bs-dismiss="modal">Filter</button>
                </div>
            </div>
        </div>
    </div>
    <!-- / Filter Modal -->

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('company.delete.data') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col">
                                <p>Are you sure you want to delete <strong id="company-name-deleted"></strong>?</p>
                                <input type="text" id="company-id-deleted" name="company_id_deleted" class="form-control d-none" readonly />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- / Delete Modal -->

    <!-- Restore Modal -->
    <div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Restore</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('company.restore.data') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col">
                                <p>Are you sure you want to restore <strong id="company-name-restore"></strong>?</p>
                                <input type="text" id="company-id-restore" name="company_id_restored" class="form-control d-none" readonly />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- / Restore Modal -->

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush


    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>

        <script>
            $(document).ready(function() {
                $("#btnFilter").click(function() {
                    var status = $('#selectStatus').find(':selected').val();
                    var path = "/business/company?";

                    if (status != "") {
                        path = path + 'status=' + status + "&";
                    }

                    window.location = path;
                });

                $("#btnResetFilter").click(function() {
                    window.location = '/business/company';
                });

                $('#deleteModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    $('#company-id-deleted').val(id);
                    $('#company-name-deleted').text(name);
                });

                $('#restoreModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    $('#company-id-restore').val(id);
                    $('#company-name-restore').text(name);
                });
            });
        </script>
    @endpush

</x-app-layout>
