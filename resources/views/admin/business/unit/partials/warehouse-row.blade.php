@php
    $hasAnyActionPermission = $hasAnyActionPermission ?? false;
    $level = $level ?? 1;
@endphp

<tr class="child-row" data-parent-id="{{ $parentId }}" data-child-id="wh-{{ $warehouse->id }}">
    <td class="text-center"></td>
    <td style="padding-left: {{ 2 + ($level * 1.5) }}rem;">
        @for($i = 0; $i < $level; $i++)
            <span class="text-muted me-1">└</span>
        @endfor
        <span class="text-muted me-2">└</span>
        <span class="badge bg-label-primary me-1">WAREHOUSE</span>
        {{ $warehouse->name }}
    </td>
    <td>
        <span class="badge bg-label-primary">WAREHOUSE</span>
    </td>
    <td><code>{{ $warehouse->code ?? '-' }}</code></td>
    <td>{{ $warehouse->warehouse_type_code ?? $warehouse->short_name ?? '-' }}</td>
    <td>{{ $warehouse->email ?? '-' }}</td>
    <td class="text-center">
        @if($warehouse->is_active)
            <i class="ti ti-check text-success"></i>
        @else
            <i class="ti ti-x text-muted"></i>
        @endif
    </td>
    @if($hasAnyActionPermission)
    <td class="text-center">
        @permission('Warehouse', 'is_update')
        <a href="{{ route('warehouse.edit.view', $warehouse->id) }}" class="btn btn-sm btn-label-warning d-inline-flex align-items-center me-1">
            <i class="ti ti-pencil me-1"></i> Edit
        </a>
        @endpermission
        @permission('Warehouse', 'is_delete')
        <a href="{{ route('warehouse.index.view') }}" class="btn btn-sm btn-label-secondary d-inline-flex align-items-center" title="Kelola di menu Warehouse">
            <i class="ti ti-building-warehouse me-1"></i> Manage
        </a>
        @endpermission
    </td>
    @endif
</tr>
