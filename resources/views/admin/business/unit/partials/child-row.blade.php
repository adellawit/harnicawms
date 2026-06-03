<tr class="child-row" data-parent-id="{{ $child->parent_id }}" data-child-id="{{ $child->id }}">
    <td class="text-center"></td>
    <td style="padding-left: {{ 2 + ($level * 1.5) }}rem;">
        @for($i = 0; $i < $level; $i++)
            <span class="text-muted me-1">└</span>
        @endfor
        @if($child->children && $child->children->count() > 0)
            <i class="ti ti-chevron-right expand-icon me-2" data-expand-id="{{ $child->id }}"></i>
        @else
            <span class="text-muted me-2">└</span>
        @endif
        <span class="badge bg-label-secondary me-1">{{ $child->type_code }}</span>
        {{ $child->name }}
        @if($child->children && $child->children->count() > 0)
            <span class="badge bg-label-secondary ms-2">{{ $child->children->count() }} Children</span>
        @endif
    </td>
    <td>
        <span class="badge bg-label-secondary">{{ $child->type_code }}</span>
    </td>
    <td><code>{{ $child->code ?? '-' }}</code></td>
    <td>{{ $child->brand_name ?? '-' }}</td>
    <td>{{ $child->email ?? '-' }}</td>
    <td class="text-center">
        @if($child->is_active)
            <i class="ti ti-check text-success"></i>
        @else
            <i class="ti ti-x text-muted"></i>
        @endif
    </td>
    @if($hasAnyActionPermission)
    <td class="text-center">
        @if($child->deleted_at)
            @permission('Holding', 'is_delete')
            <button type="button" class="btn btn-sm btn-label-success d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="{{ $child->id }}" data-name="{{ $child->name }}">
                <i class="ti ti-refresh me-1"></i> Restore
            </button>
            @endpermission
        @else
            @permission('Holding', 'is_update')
            <a href="{{ route('holding.edit.view', $child->id) }}" class="btn btn-sm btn-label-warning d-inline-flex align-items-center me-1">
                <i class="ti ti-pencil me-1"></i> Edit
            </a>
            @endpermission
            @permission('Holding', 'is_delete')
            <button type="button" class="btn btn-sm btn-label-danger d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="{{ $child->id }}" data-name="{{ $child->name }}">
                <i class="ti ti-trash me-1"></i> Delete
            </button>
            @endpermission
        @endif
    </td>
    @endif
</tr>

@if($child->children && $child->children->count() > 0)
    @foreach ($child->children as $grandchild)
        @include('admin.business.unit.partials.child-row', ['child' => $grandchild, 'level' => $level + 1])
    @endforeach
@endif
