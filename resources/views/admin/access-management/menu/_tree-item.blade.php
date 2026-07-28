@php
    $children = $menu->relationLoaded('children') ? $menu->children : collect();
    $hasChildren = $children->isNotEmpty();
@endphp

<div class="menu-tree-item {{ $menu->deleted_at ? 'is-deleted' : '' }}"
    data-menu-id="{{ $menu->id }}"
    data-menu-name="{{ $menu->name }}"
    data-deleted="{{ $menu->deleted_at ? '1' : '0' }}">
    <div class="menu-tree-row menu-tree-grid {{ $depth === 1 ? 'is-root' : ($hasChildren ? 'has-children' : '') }}"
        style="--menu-depth: {{ $depth }}">
        <div class="menu-tree-cell menu-order-cell text-center">
            @if($canReorderMenus && !$menu->deleted_at)
                <button type="button" class="menu-drag-handle" title="Drag to reorder menu" aria-label="Drag {{ $menu->name }}">
                    <i class="ti ti-grip-vertical"></i>
                </button>
            @endif
            <span class="menu-order-value">{{ $menu->order_number ?? '-' }}</span>
        </div>

        <div class="menu-tree-cell menu-name-cell">
            <button type="button"
                class="menu-collapse-toggle {{ $hasChildren ? '' : 'is-hidden' }}"
                aria-expanded="true"
                aria-label="Collapse {{ $menu->name }}"
                title="Collapse {{ $menu->name }}">
                <i class="ti ti-chevron-down"></i>
            </button>
            @if($depth > 1)
                <span class="menu-tree-branch" aria-hidden="true">
                    <i class="ti ti-corner-down-right"></i>
                </span>
            @endif
            @if($depth === 1 || $hasChildren)
                <strong class="menu-name-text" title="{{ $menu->name }}">{{ $menu->name }}</strong>
            @else
                <span class="menu-name-text" title="{{ $menu->name }}">{{ $menu->name }}</span>
            @endif
        </div>

        <div class="menu-tree-cell">
            @if($menu->icon)
                <code class="menu-icon-code" title="{{ $menu->icon }}">{{ $menu->icon }}</code>
            @else
                <span class="text-muted">—</span>
            @endif
        </div>
        <div class="menu-tree-cell">
            <span class="menu-url" title="{{ $menu->url_path ?? 'No URL path' }}">{{ $menu->url_path ?? '—' }}</span>
        </div>
        <div class="menu-tree-cell justify-content-center">
            <i class="menu-permission-icon ti ti-{{ $menu->has_create ? 'check text-success' : 'x text-muted' }}"
                aria-label="Create {{ $menu->has_create ? 'enabled' : 'disabled' }}"></i>
        </div>
        <div class="menu-tree-cell justify-content-center">
            <i class="menu-permission-icon ti ti-{{ $menu->has_read ? 'check text-success' : 'x text-muted' }}"
                aria-label="Read {{ $menu->has_read ? 'enabled' : 'disabled' }}"></i>
        </div>
        <div class="menu-tree-cell justify-content-center">
            <i class="menu-permission-icon ti ti-{{ $menu->has_update ? 'check text-success' : 'x text-muted' }}"
                aria-label="Update {{ $menu->has_update ? 'enabled' : 'disabled' }}"></i>
        </div>
        <div class="menu-tree-cell justify-content-center">
            <i class="menu-permission-icon ti ti-{{ $menu->has_delete ? 'check text-success' : 'x text-muted' }}"
                aria-label="Delete {{ $menu->has_delete ? 'enabled' : 'disabled' }}"></i>
        </div>
        <div class="menu-tree-cell text-center">
            <x-badge :color="$menu->deleted_at ? 'danger' : 'success'">{{ $menu->deleted_at ? 'Deleted' : 'Active' }}</x-badge>
        </div>

        @if($hasAnyActionPermission)
            <div class="menu-tree-cell text-center menu-action-cell">
                @if($menu->deleted_at)
                    @permission('Menu', 'is_delete')
                    <button type="button" class="btn btn-sm btn-icon btn-label-success menu-action-btn"
                        data-bs-toggle="modal" data-bs-target="#restoreModal"
                        data-id="{{ $menu->id }}" data-name="{{ $menu->name }}"
                        title="Restore {{ $menu->name }}" aria-label="Restore {{ $menu->name }}">
                        <i class="ti ti-refresh"></i>
                    </button>
                    @endpermission
                @else
                    @permission('Menu', 'is_update')
                    <a href="{{ route('menu.edit.view', $menu->id) }}"
                        class="btn btn-sm btn-icon btn-label-warning menu-action-btn"
                        title="Edit {{ $menu->name }}" aria-label="Edit {{ $menu->name }}">
                        <i class="ti ti-pencil"></i>
                    </a>
                    @endpermission
                    @permission('Menu', 'is_delete')
                    <button type="button" class="btn btn-sm btn-icon btn-label-danger menu-action-btn"
                        data-bs-toggle="modal" data-bs-target="#deleteModal"
                        data-id="{{ $menu->id }}" data-name="{{ $menu->name }}"
                        title="Delete {{ $menu->name }}" aria-label="Delete {{ $menu->name }}">
                        <i class="ti ti-trash"></i>
                    </button>
                    @endpermission
                @endif
            </div>
        @endif
    </div>

    <div class="menu-tree-list" data-depth="{{ $depth + 1 }}">
        @foreach($children as $child)
            @include('admin.access-management.menu._tree-item', ['menu' => $child, 'depth' => $depth + 1])
        @endforeach
    </div>
</div>
