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

            .menu-tree-table {
                min-width: 1020px;
                border-top: 1px solid var(--bs-border-color);
                color: var(--bs-body-color);
            }

            .menu-tree-grid {
                display: grid;
                grid-template-columns: 74px minmax(200px, 1.5fr) minmax(132px, .8fr) minmax(160px, 1.1fr) repeat(4, 52px) 82px;
            }

            .menu-tree-table.has-actions .menu-tree-grid {
                grid-template-columns: 74px minmax(200px, 1.5fr) minmax(132px, .8fr) minmax(160px, 1.1fr) repeat(4, 52px) 82px 96px;
            }

            .menu-tree-header {
                position: sticky;
                top: 0;
                z-index: 5;
                color: var(--bs-secondary-color);
                background: var(--bs-tertiary-bg);
                font-size: .6875rem;
                font-weight: 600;
                letter-spacing: .035em;
                text-transform: uppercase;
            }

            .menu-tree-header > div,
            .menu-tree-cell {
                display: flex;
                min-width: 0;
                align-items: center;
                min-height: 50px;
                padding: .625rem .75rem;
                border-right: 1px solid var(--bs-border-color);
                border-bottom: 1px solid var(--bs-border-color);
            }

            .menu-tree-header > div {
                min-height: 42px;
                padding-block: .5rem;
            }

            .menu-tree-header > div:last-child,
            .menu-tree-cell:last-child {
                border-right: 0;
            }

            .menu-tree-row {
                background: var(--bs-body-bg);
                font-size: .8125rem;
                transition: background-color 160ms ease;
            }

            .menu-tree-row:hover {
                background: var(--bs-tertiary-bg);
            }

            .menu-tree-row.is-root {
                background: color-mix(in srgb, var(--bs-primary) 5%, var(--bs-body-bg));
            }

            .menu-tree-row.is-root:hover {
                background: color-mix(in srgb, var(--bs-primary) 9%, var(--bs-body-bg));
            }

            .menu-name-cell {
                padding-left: calc(.75rem + (var(--menu-depth) - 1) * 1.5rem);
                gap: .375rem;
                white-space: nowrap;
            }

            .menu-collapse-toggle {
                display: inline-flex;
                width: 28px;
                height: 28px;
                align-items: center;
                justify-content: center;
                flex: 0 0 28px;
                padding: 0;
                border: 0;
                border-radius: var(--bs-border-radius-sm);
                color: var(--bs-secondary-color);
                background: transparent;
                cursor: pointer;
                transition: color 160ms ease, background-color 160ms ease;
            }

            .menu-collapse-toggle:hover,
            .menu-collapse-toggle:focus-visible {
                color: var(--bs-primary);
                background: rgba(var(--bs-primary-rgb), .1);
                outline: none;
            }

            .menu-collapse-toggle.is-hidden {
                visibility: hidden;
                pointer-events: none;
            }

            .menu-tree-branch {
                display: inline-flex;
                flex: 0 0 18px;
                align-items: center;
                justify-content: center;
                color: var(--bs-secondary-color);
            }

            .menu-name-text {
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .menu-order-cell {
                justify-content: center;
                gap: .25rem;
                font-variant-numeric: tabular-nums;
            }

            .menu-drag-handle {
                display: inline-flex;
                width: 30px;
                height: 30px;
                align-items: center;
                justify-content: center;
                flex: 0 0 30px;
                padding: 0;
                border: 0;
                border-radius: var(--bs-border-radius-sm);
                color: var(--bs-secondary-color);
                background: transparent;
                cursor: grab;
                transition: color 160ms ease, background-color 160ms ease;
            }

            .menu-drag-handle:hover,
            .menu-drag-handle:focus-visible {
                color: var(--bs-primary);
                background: rgba(var(--bs-primary-rgb), .1);
                outline: none;
            }

            .menu-drag-handle:active {
                cursor: grabbing;
            }

            .menu-icon-code {
                display: block;
                max-width: 100%;
                overflow: hidden;
                padding: .2rem .4rem;
                border-radius: var(--bs-border-radius-sm);
                color: var(--bs-secondary-color);
                background: var(--bs-tertiary-bg);
                font-size: .6875rem;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .menu-url {
                display: block;
                overflow: hidden;
                color: var(--bs-secondary-color);
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .menu-permission-icon {
                font-size: 1rem;
            }

            .menu-tree-item.is-deleted {
                opacity: .68;
            }

            .menu-tree-list:empty {
                min-height: 0;
            }

            .menu-tree-list[data-depth="4"]:empty {
                display: none;
            }

            .menu-tree.is-sorting .menu-tree-list:empty {
                min-height: 12px;
                outline: 1px dashed rgba(var(--bs-primary-rgb), .35);
                outline-offset: -2px;
            }

            .menu-tree-ghost > .menu-tree-row {
                background: rgba(var(--bs-primary-rgb), .12);
            }

            .menu-tree-chosen > .menu-tree-row {
                box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), .2);
            }

            .menu-empty-state {
                padding: 2rem;
                text-align: center;
                border-bottom: 1px solid var(--bs-border-color);
            }

            .menu-action-cell {
                justify-content: center;
                gap: .375rem;
                white-space: nowrap;
            }

            .menu-action-btn {
                display: inline-flex;
                width: 32px;
                height: 32px;
                align-items: center;
                justify-content: center;
                flex: 0 0 32px;
                padding: 0;
            }

            .menu-save-status {
                min-width: 100px;
                font-size: .8125rem;
                white-space: nowrap;
            }

            .menu-tree-toolbar .btn {
                display: inline-flex;
                align-items: center;
                gap: .3rem;
            }

            .menu-table-scroll {
                border-radius: 0 0 var(--bs-card-border-radius) var(--bs-card-border-radius);
            }

            @media (max-width: 767.98px) {
                .menu-card-header {
                    align-items: flex-start !important;
                    gap: .75rem;
                }

                .menu-card-actions {
                    flex-wrap: wrap;
                    justify-content: flex-end;
                }

                .menu-save-status {
                    width: 100%;
                    margin-right: 0 !important;
                    text-align: right;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .menu-tree-row,
                .menu-drag-handle,
                .menu-collapse-toggle {
                    transition: none;
                }
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
            $canReorderMenus = $hasUpdatePermission && $status === '';
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
            <div class="card-header menu-card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Menu Management</h5>
                <div class="menu-card-actions d-flex align-items-center">
                    <div class="btn-group btn-group-sm menu-tree-toolbar me-2" role="group" aria-label="Menu tree controls">
                        <button type="button" id="expandAllMenus" class="btn btn-outline-secondary" title="Expand all menus">
                            <i class="ti ti-arrows-maximize"></i>
                            <span>Expand All</span>
                        </button>
                        <button type="button" id="collapseAllMenus" class="btn btn-outline-secondary" title="Collapse all menus">
                            <i class="ti ti-arrows-minimize"></i>
                            <span>Collapse All</span>
                        </button>
                    </div>
                    @if($hasUpdatePermission)
                        <span id="menuSaveStatus" class="menu-save-status text-muted me-3" aria-live="polite">
                            {{ $canReorderMenus ? 'Drag to reorder' : 'Use filter All to reorder' }}
                        </span>
                    @endif
                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                    @permission('Menu', 'is_create')
                    <a href="{{ route('menu.insert.view') }}" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus me-1"></i> Add Menu
                    </a>
                    @endpermission
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive menu-table-scroll">
                    <div id="menuTree" class="menu-tree menu-tree-table {{ $hasAnyActionPermission ? 'has-actions' : '' }}">
                        <div class="menu-tree-header menu-tree-grid">
                            <div class="justify-content-center">Order Number</div>
                            <div>Menu</div>
                            <div>Icon</div>
                            <div>URL Path</div>
                            <div class="justify-content-center">Create</div>
                            <div class="justify-content-center">Read</div>
                            <div class="justify-content-center">Update</div>
                            <div class="justify-content-center">Delete</div>
                            <div class="justify-content-center">Status</div>
                            @if($hasAnyActionPermission)
                                <div class="justify-content-center">Actions</div>
                            @endif
                        </div>

                        <div id="menuTreeRoot" class="menu-tree-list" data-depth="1">
                            @forelse($parentMenus as $parent)
                                @include('admin.access-management.menu._tree-item', ['menu' => $parent, 'depth' => 1])
                            @empty
                                <div class="menu-empty-state">No menus found.</div>
                            @endforelse
                        </div>
                    </div>
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
        <script src="{{ asset('assets/vendor/libs/sortablejs/sortable.js') }}"></script>
    @endpush


    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>

        <script>
            $(document).ready(function() {
                const menuTree = document.getElementById('menuTree');
                const menuTreeRoot = document.getElementById('menuTreeRoot');
                const saveStatus = document.getElementById('menuSaveStatus');
                const expandAllMenus = document.getElementById('expandAllMenus');
                const collapseAllMenus = document.getElementById('collapseAllMenus');
                const canReorder = @json($canReorderMenus);
                const sortables = [];
                let lastSavedTree = [];

                function directItems(list) {
                    return Array.from(list.children).filter(element => element.classList.contains('menu-tree-item'));
                }

                function childList(item) {
                    return Array.from(item.children).find(element => element.classList.contains('menu-tree-list'));
                }

                function serializeList(list) {
                    return directItems(list).map(item => ({
                        id: item.dataset.menuId,
                        children: serializeList(childList(item)),
                    }));
                }

                function setItemExpanded(item, expanded) {
                    const list = childList(item);
                    const row = item.querySelector(':scope > .menu-tree-row');
                    const toggle = row.querySelector('.menu-collapse-toggle');
                    const hasChildren = directItems(list).length > 0;
                    const shouldExpand = !hasChildren || expanded;

                    item.classList.toggle('is-collapsed', hasChildren && !shouldExpand);
                    list.hidden = hasChildren && !shouldExpand;
                    toggle.classList.toggle('is-hidden', !hasChildren);
                    toggle.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
                    toggle.setAttribute('aria-label', `${shouldExpand ? 'Collapse' : 'Expand'} ${item.dataset.menuName}`);
                    toggle.title = `${shouldExpand ? 'Collapse' : 'Expand'} ${item.dataset.menuName}`;

                    const icon = toggle.querySelector('i');
                    icon.classList.toggle('ti-chevron-down', shouldExpand);
                    icon.classList.toggle('ti-chevron-right', !shouldExpand);
                }

                function setAllMenusExpanded(expanded) {
                    menuTree.querySelectorAll('.menu-tree-item').forEach(item => {
                        setItemExpanded(item, expanded);
                    });
                }

                function refreshTreeMetadata(list = menuTreeRoot, depth = 1) {
                    list.dataset.depth = depth;

                    directItems(list).forEach((item, index) => {
                        const row = item.querySelector(':scope > .menu-tree-row');
                        const orderValue = row.querySelector('.menu-order-value');
                        const children = directItems(childList(item));

                        row.style.setProperty('--menu-depth', depth);
                        row.classList.toggle('is-root', depth === 1);
                        row.classList.toggle('has-children', depth > 1 && children.length > 0);
                        orderValue.textContent = index + 1;
                        setItemExpanded(item, !item.classList.contains('is-collapsed'));
                        refreshTreeMetadata(childList(item), depth + 1);
                    });
                }

                function subtreeHeight(item) {
                    const children = directItems(childList(item));

                    if (children.length === 0) {
                        return 1;
                    }

                    return 1 + Math.max(...children.map(subtreeHeight));
                }

                function restoreTree(tree, list = menuTreeRoot) {
                    const itemMap = new Map(
                        Array.from(menuTree.querySelectorAll('.menu-tree-item'))
                            .map(item => [item.dataset.menuId, item])
                    );

                    tree.forEach(node => {
                        const item = itemMap.get(node.id);

                        if (!item) {
                            return;
                        }

                        list.appendChild(item);
                        restoreTree(node.children, childList(item));
                    });

                    refreshTreeMetadata();
                }

                function setSortablesDisabled(disabled) {
                    sortables.forEach(sortable => sortable.option('disabled', disabled));
                }

                function setSaveStatus(message, className) {
                    if (!saveStatus) {
                        return;
                    }

                    saveStatus.textContent = message;
                    saveStatus.className = `menu-save-status me-3 ${className}`;
                }

                async function saveTree() {
                    const tree = serializeList(menuTreeRoot);

                    setSortablesDisabled(true);
                    setSaveStatus('Saving...', 'text-primary');

                    try {
                        const response = await fetch(@json(route('menu.reorder')), {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': @json(csrf_token()),
                            },
                            body: JSON.stringify({ menus: tree }),
                        });
                        const payload = await response.json();

                        if (!response.ok) {
                            const validationMessage = payload.errors?.menus?.[0];
                            throw new Error(validationMessage || payload.message || 'Menu order could not be saved.');
                        }

                        lastSavedTree = tree;
                        setSaveStatus('Saved', 'text-success');
                    } catch (error) {
                        restoreTree(lastSavedTree);
                        setSaveStatus(error.message, 'text-danger');
                    } finally {
                        setSortablesDisabled(false);
                    }
                }

                if (canReorder && menuTreeRoot) {
                    lastSavedTree = serializeList(menuTreeRoot);

                    menuTree.querySelectorAll('.menu-tree-list').forEach(list => {
                        sortables.push(Sortable.create(list, {
                            group: 'menu-tree',
                            animation: 150,
                            handle: '.menu-drag-handle',
                            draggable: '.menu-tree-item',
                            ghostClass: 'menu-tree-ghost',
                            chosenClass: 'menu-tree-chosen',
                            fallbackOnBody: true,
                            swapThreshold: .65,
                            onStart: () => menuTree.classList.add('is-sorting'),
                            onMove: event => {
                                const targetDepth = Number(event.to.dataset.depth);
                                const targetParent = event.to.closest('.menu-tree-item');

                                if (
                                    event.dragged.contains(event.to) ||
                                    targetParent?.dataset.deleted === '1'
                                ) {
                                    return false;
                                }

                                return targetDepth + subtreeHeight(event.dragged) - 1 <= 3;
                            },
                            onEnd: event => {
                                menuTree.classList.remove('is-sorting');

                                if (event.from === event.to && event.oldIndex === event.newIndex) {
                                    return;
                                }

                                refreshTreeMetadata();
                                saveTree();
                            },
                        }));
                    });
                }

                menuTree.addEventListener('click', event => {
                    const toggle = event.target.closest('.menu-collapse-toggle');

                    if (!toggle) {
                        return;
                    }

                    const item = toggle.closest('.menu-tree-item');
                    setItemExpanded(item, item.classList.contains('is-collapsed'));
                });

                expandAllMenus.addEventListener('click', () => setAllMenusExpanded(true));
                collapseAllMenus.addEventListener('click', () => setAllMenusExpanded(false));

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
