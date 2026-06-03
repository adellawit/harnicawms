<x-app-layout>

    @section('title', 'Holding | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    @endpush

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-profile.css') }}" />
        <style>
            .breadcrumb-item a:hover {
                color: #212529 !important;
            }
            .business-unit-table th, .business-unit-table td {
                vertical-align: middle;
            }
            .expand-icon {
                cursor: pointer;
                transition: transform 0.2s ease;
                display: inline-block;
            }
            .expand-icon.rotated {
                transform: rotate(90deg);
            }
            .child-row {
                display: none;
            }
            .child-row.show {
                display: table-row;
            }
            .business-unit-table th:nth-last-child(1),
            .business-unit-table td:nth-last-child(1) {
                width: 180px;
                white-space: nowrap;
            }
            .business-unit-table th:nth-child(2),
            .business-unit-table td:nth-child(2) {
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
                ['label' => 'Master Data', 'url' => 'javascript:void(0);'],
                ['label' => 'Holding', 'active' => true]
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

        <!-- Business Unit Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Holding Management</h5>
                <div>
                    <button type="button" class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    @permission('Holding', 'is_create')
                    <a href="{{ route('holding.insert.view') }}" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus me-1"></i> Add Holding
                    </a>
                    @endpermission
                </div>
            </div>
            <div class="card-body">
                @php
                    // Check if user has any action permissions for Holding
                    $hasUpdatePermission = session('permissions.Holding.is_update', false) == 1;
                    $hasDeletePermission = session('permissions.Holding.is_delete', false) == 1;
                    $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
                @endphp
                <div class="table-responsive">
                    <table class="table table-bordered business-unit-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px" class="text-center">No</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Code</th>
                                <th>Brand Name</th>
                                <th>Email</th>
                                <th class="text-center">Active</th>
                                @if($hasAnyActionPermission)
                                <th class="text-center">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @php $rowNumber = 1; @endphp
                            @forelse ($parentBusinessUnits as $parent)
                                {{-- Parent Business Unit Row --}}
                                <tr class="table-secondary" data-parent-id="{{ $parent->id }}">
                                    <td class="text-center">{{ $rowNumber++ }}</td>
                                    <td>
                                        @if($parent->children && $parent->children->count() > 0)
                                            <i class="ti ti-chevron-right expand-icon me-2" data-expand-id="{{ $parent->id }}"></i>
                                        @else
                                            <span class="me-5"></span>
                                        @endif
                                        <strong>
                                            <span class="badge bg-label-primary me-1">{{ $parent->type_code }}</span>
                                            {{ $parent->name }}
                                        </strong>
                                        @if($parent->children && $parent->children->count() > 0)
                                            <span class="badge bg-label-secondary ms-2">{{ $parent->children->count() }} Children</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-label-info">{{ $parent->type_code }}</span>
                                    </td>
                                    <td><code>{{ $parent->code ?? '-' }}</code></td>
                                    <td>{{ $parent->brand_name ?? '-' }}</td>
                                    <td>{{ $parent->email ?? '-' }}</td>
                                    <td class="text-center">
                                        @if($parent->is_active)
                                            <i class="ti ti-check text-success"></i>
                                        @else
                                            <i class="ti ti-x text-muted"></i>
                                        @endif
                                    </td>
                                    @if($hasAnyActionPermission)
                                    <td class="text-center">
                                        @if($parent->deleted_at)
                                            @permission('Holding', 'is_delete')
                                            <button type="button" class="btn btn-sm btn-label-success d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="{{ $parent->id }}" data-name="{{ $parent->name }}">
                                                <i class="ti ti-refresh me-1"></i> Restore
                                            </button>
                                            @endpermission
                                        @else
                                            @permission('Holding', 'is_update')
                                            <a href="{{ route('holding.edit.view', $parent->id) }}" class="btn btn-sm btn-label-warning d-inline-flex align-items-center me-1">
                                                <i class="ti ti-pencil me-1"></i> Edit
                                            </a>
                                            @endpermission
                                            @permission('Holding', 'is_delete')
                                            <button type="button" class="btn btn-sm btn-label-danger d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="{{ $parent->id }}" data-name="{{ $parent->name }}">
                                                <i class="ti ti-trash me-1"></i> Delete
                                            </button>
                                            @endpermission
                                        @endif
                                    </td>
                                    @endif
                                </tr>

                                {{-- Children Business Unit Rows (hidden by default) --}}
                                @if($parent->children && $parent->children->count() > 0)
                                    @foreach ($parent->children as $child)
                                        @include('admin.business.unit.partials.child-row', ['child' => $child, 'level' => 1])
                                    @endforeach
                                @endif
                            @empty
                                <tr>
                                    <td colspan="{{ $hasAnyActionPermission ? 8 : 7 }}" class="text-center">No holdings found.</td>
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
                <form method="POST" action="{{ route('holding.delete.data') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col">
                                <p>Are you sure you want to delete <strong id="holding-name-deleted"></strong>?</p>
                                <input type="text" id="holding-id-deleted" name="holding_id_deleted" class="form-control d-none" readonly />
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
                <form method="POST" action="{{ route('holding.restore.data') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col">
                                <p>Are you sure you want to restore <strong id="holding-name-restore"></strong>?</p>
                                <input type="text" id="holding-id-restore" name="holding_id_restored" class="form-control d-none" readonly />
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
                // Expand/Collapse functionality
                $(document).on('click', '.expand-icon', function(e) {
                    e.stopPropagation();
                    const expandId = $(this).data('expand-id');
                    const icon = $(this);

                    // Toggle the icon rotation
                    icon.toggleClass('rotated');

                    // Find all direct child rows with matching parent-id
                    const directChildren = $('.child-row[data-parent-id="' + expandId + '"]');

                    // Toggle visibility
                    if (icon.hasClass('rotated')) {
                        directChildren.addClass('show');
                    } else {
                        // When collapsing, also collapse all nested children
                        const allDescendants = getAllDescendants(expandId);
                        allDescendants.removeClass('show');

                        // Also rotate back all nested expand icons
                        allDescendants.each(function() {
                            const childId = $(this).data('child-id');
                            $('.expand-icon[data-expand-id="' + childId + '"]').removeClass('rotated');
                        });
                    }
                });

                // Helper function to get all descendants
                function getAllDescendants(parentId) {
                    let descendants = $();
                    const directChildren = $('.child-row[data-parent-id="' + parentId + '"]');

                    directChildren.each(function() {
                        descendants = descendants.add($(this));
                        const childId = $(this).data('child-id');
                        if (childId) {
                            descendants = descendants.add(getAllDescendants(childId));
                        }
                    });

                    return descendants;
                }

                $("#btnFilter").click(function() {
                    var status = $('#selectStatus').find(':selected').val();
                    var path = "/business/holding?";

                    if (status != "") {
                        path = path + 'status=' + status + "&";
                    }

                    window.location = path;
                });

                $("#btnResetFilter").click(function() {
                    window.location = '/business/holding';
                });

                $('#deleteModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    $('#holding-id-deleted').val(id);
                    $('#holding-name-deleted').text(name);
                });

                $('#restoreModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    $('#holding-id-restore').val(id);
                    $('#holding-name-restore').text(name);
                });
            });
        </script>
    @endpush

</x-app-layout>
