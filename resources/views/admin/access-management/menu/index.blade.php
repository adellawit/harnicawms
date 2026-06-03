<x-app-layout>

    @section('title', 'Menu | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    @endpush

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-profile.css') }}" />
        <style>
            .breadcrumb-item a:hover {
                color: #212529 !important;
            }
            .menu-table th, .menu-table td {
                vertical-align: middle;
            }
        </style>
    @endpush

    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">

        @php
            $hasUpdatePermission = session('permissions.Menu.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Menu.is_delete', false) == 1;
            $hasCreatePermission = session('permissions.Menu.is_create', false) == 1;
            $hasAnyActionPermission = $hasUpdatePermission || $hasDeletePermission;
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Settings', 'url' => 'javascript:void(0);'],
                ['label' => 'Menu', 'active' => true]
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

        <!-- Menu Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Menu Management</h5>
                <div>
                    <button type="button" class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    @permission('Menu', 'is_create')
                    <a href="{{ route('menu.insert.view') }}" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus me-1"></i> Add Menu
                    </a>
                    @endpermission
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered menu-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 25%">Menu</th>
                                <th>Icon</th>
                                <th>URL Path</th>
                                <th class="text-center">Create</th>
                                <th class="text-center">Read</th>
                                <th class="text-center">Update</th>
                                <th class="text-center">Delete</th>
                                <th class="text-center">Status</th>
                                @if($hasAnyActionPermission)
                                <th class="text-center">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
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
                                    <td>@if($parent->icon)<code>{{ $parent->icon }}</code>@else - @endif</td>
                                    <td>{{ $parent->url_path ?? '-' }}</td>
                                    <td class="text-center"><i class="ti ti-{{ $parent->has_create ? 'check text-success' : 'x text-muted' }}"></i></td>
                                    <td class="text-center"><i class="ti ti-{{ $parent->has_read ? 'check text-success' : 'x text-muted' }}"></i></td>
                                    <td class="text-center"><i class="ti ti-{{ $parent->has_update ? 'check text-success' : 'x text-muted' }}"></i></td>
                                    <td class="text-center"><i class="ti ti-{{ $parent->has_delete ? 'check text-success' : 'x text-muted' }}"></i></td>
                                    <td class="text-center">
                                        <x-badge :color="$parent->deleted_at ? 'danger' : 'success'">{{ $parent->deleted_at ? 'Deleted' : 'Active' }}</x-badge>
                                    </td>
                                    @if($hasAnyActionPermission)
                                    <td class="text-center">
                                        @if($parent->deleted_at)
                                            @permission('Menu', 'is_delete')
                                            <button type="button" class="btn btn-sm btn-label-success d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="{{ $parent->id }}" data-name="{{ $parent->name }}">
                                                <i class="ti ti-refresh me-1"></i> Restore
                                            </button>
                                            @endpermission
                                        @else
                                            @permission('Menu', 'is_update')
                                            <a href="{{ route('menu.edit.view', $parent->id) }}" class="btn btn-sm btn-label-warning d-inline-flex align-items-center me-1"><i class="ti ti-pencil me-1"></i> Edit</a>
                                            @endpermission
                                            @permission('Menu', 'is_delete')
                                            <button type="button" class="btn btn-sm btn-label-danger d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="{{ $parent->id }}" data-name="{{ $parent->name }}"><i class="ti ti-trash me-1"></i> Delete</button>
                                            @endpermission
                                        @endif
                                    </td>
                                    @endif
                                </tr>

                                {{-- Children Menu Rows (Level 2) --}}
                                @foreach ($parent->children as $child)
                                    @php
                                        $hasGrandChildren = $child->relationLoaded('children') && $child->children->count() > 0;
                                    @endphp
                                    <tr class="{{ $hasGrandChildren ? 'table-light' : '' }}">
                                        <td style="padding-left: 2rem;">
                                            <span class="text-muted">└</span>
                                            @if($child->icon)
                                                <i class="{{ $child->icon }} me-1 text-muted"></i>
                                            @endif
                                            {!! $hasGrandChildren ? '<strong>' . e($child->name) . '</strong>' : e($child->name) !!}
                                        </td>
                                        <td>@if($child->icon)<code>{{ $child->icon }}</code>@else - @endif</td>
                                        <td>{{ $child->url_path ?? '-' }}</td>
                                        <td class="text-center"><i class="ti ti-{{ $child->has_create ? 'check text-success' : 'x text-muted' }}"></i></td>
                                        <td class="text-center"><i class="ti ti-{{ $child->has_read ? 'check text-success' : 'x text-muted' }}"></i></td>
                                        <td class="text-center"><i class="ti ti-{{ $child->has_update ? 'check text-success' : 'x text-muted' }}"></i></td>
                                        <td class="text-center"><i class="ti ti-{{ $child->has_delete ? 'check text-success' : 'x text-muted' }}"></i></td>
                                        <td class="text-center">
                                            <x-badge :color="$child->deleted_at ? 'danger' : 'success'">{{ $child->deleted_at ? 'Deleted' : 'Active' }}</x-badge>
                                        </td>
                                        @if($hasAnyActionPermission)
                                        <td class="text-center">
                                            @if($child->deleted_at)
                                                @permission('Menu', 'is_delete')
                                                <button type="button" class="btn btn-sm btn-label-success d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="{{ $child->id }}" data-name="{{ $child->name }}">
                                                    <i class="ti ti-refresh me-1"></i> Restore
                                                </button>
                                                @endpermission
                                            @else
                                                @permission('Menu', 'is_update')
                                                <a href="{{ route('menu.edit.view', $child->id) }}" class="btn btn-sm btn-label-warning d-inline-flex align-items-center me-1"><i class="ti ti-pencil me-1"></i> Edit</a>
                                                @endpermission
                                                @permission('Menu', 'is_delete')
                                                <button type="button" class="btn btn-sm btn-label-danger d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="{{ $child->id }}" data-name="{{ $child->name }}"><i class="ti ti-trash me-1"></i> Delete</button>
                                                @endpermission
                                            @endif
                                        </td>
                                        @endif
                                    </tr>

                                    {{-- Grandchildren Menu Rows (Level 3) --}}
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
                                                <td>@if($grandChild->icon)<code>{{ $grandChild->icon }}</code>@else - @endif</td>
                                                <td>{{ $grandChild->url_path ?? '-' }}</td>
                                                <td class="text-center"><i class="ti ti-{{ $grandChild->has_create ? 'check text-success' : 'x text-muted' }}"></i></td>
                                                <td class="text-center"><i class="ti ti-{{ $grandChild->has_read ? 'check text-success' : 'x text-muted' }}"></i></td>
                                                <td class="text-center"><i class="ti ti-{{ $grandChild->has_update ? 'check text-success' : 'x text-muted' }}"></i></td>
                                                <td class="text-center"><i class="ti ti-{{ $grandChild->has_delete ? 'check text-success' : 'x text-muted' }}"></i></td>
                                                <td class="text-center">
                                                    <x-badge :color="$grandChild->deleted_at ? 'danger' : 'success'">{{ $grandChild->deleted_at ? 'Deleted' : 'Active' }}</x-badge>
                                                </td>
                                                @if($hasAnyActionPermission)
                                                <td class="text-center">
                                                    @if($grandChild->deleted_at)
                                                        @permission('Menu', 'is_delete')
                                                        <button type="button" class="btn btn-sm btn-label-success d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="{{ $grandChild->id }}" data-name="{{ $grandChild->name }}">
                                                            <i class="ti ti-refresh me-1"></i> Restore
                                                        </button>
                                                        @endpermission
                                                    @else
                                                        @permission('Menu', 'is_update')
                                                        <a href="{{ route('menu.edit.view', $grandChild->id) }}" class="btn btn-sm btn-label-warning d-inline-flex align-items-center me-1"><i class="ti ti-pencil me-1"></i> Edit</a>
                                                        @endpermission
                                                        @permission('Menu', 'is_delete')
                                                        <button type="button" class="btn btn-sm btn-label-danger d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="{{ $grandChild->id }}" data-name="{{ $grandChild->name }}"><i class="ti ti-trash me-1"></i> Delete</button>
                                                        @endpermission
                                                    @endif
                                                </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    @endif
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="{{ $hasAnyActionPermission ? 9 : 8 }}" class="text-center">No menus found.</td>
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
                <form method="POST" action="{{ route('menu.delete.data') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col">
                                <p>Are you sure you want to delete <strong id="menu-name-deleted"></strong>?</p>
                                <input type="text" id="menu-id-deleted" name="menu_id_deleted" class="form-control d-none" readonly />
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
                <form method="POST" action="{{ route('menu.restore.data') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col">
                                <p>Are you sure you want to restore <strong id="menu-name-restore"></strong>?</p>
                                <input type="text" id="menu-id-restore" name="menu_id_restored" class="form-control d-none" readonly />
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
                    var path = "{{ url("access-management/menu") }}?";

                    if (status != "") {
                        path = path + 'status=' + status + "&";
                    }

                    window.location = path;
                });

                $("#btnResetFilter").click(function() {
                    window.location = '{{ route("menu.index.view") }}';
                });

                $('#deleteModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    $('#menu-id-deleted').val(id);
                    $('#menu-name-deleted').text(name);
                });

                $('#restoreModal').on('show.bs.modal', function(event) {
                    const button = $(event.relatedTarget);
                    const id = button.data('id');
                    const name = button.data('name');

                    $('#menu-id-restore').val(id);
                    $('#menu-name-restore').text(name);
                });
            });
        </script>
    @endpush

</x-app-layout>
