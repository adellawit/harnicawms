<tr>
    <td class="text-center">
        @if($child->children->count() > 0)
            <i class="ti ti-chevron-right expand-icon" data-branch-id="{{ $child->id }}" style="margin-left: {{ $level * 15 }}px;"></i>
        @else
            <span class="text-muted" style="margin-left: {{ $level * 15 + 8 }}px;">└</span>
        @endif
    </td>
    <td>
        <span style="margin-left: {{ $level * 15 }}px;">
            @for($i = 0; $i < $level; $i++)
                <span class="text-muted me-1">└</span>
            @endfor
            <span class="badge bg-label-secondary me-1">BRANCH</span>
            {{ $child->name }}
        </span>
        <small class="text-muted d-block" style="margin-left: {{ $level * 15 + 25 }}px;">
            @if($child->parent && $child->parent->type_code === 'COMPANY')
                <i class="ti ti-building-factory-2 me-1"></i>{{ $child->parent->name }}
            @elseif($child->parent && $child->parent->type_code === 'BRANCH')
                <i class="ti ti-building-store me-1"></i>{{ $child->parent->name }}
            @endif
        </small>
    </td>
    <td><code>{{ $child->code ?? '-' }}</code></td>
    <td>{{ $child->brand_name ?? '-' }}</td>
    <td>{{ $child->email ?? '-' }}</td>
    <td>{{ $child->phone ?? '-' }}</td>
    <td class="text-center">
        @if($child->is_active)
            <i class="ti ti-check text-success"></i>
        @else
            <i class="ti ti-x text-muted"></i>
        @endif
    </td>
    <td class="text-center">
        @if($child->deleted_at)
            <button type="button" class="btn btn-sm btn-label-success d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#restoreModal" data-id="{{ $child->id }}" data-name="{{ $child->name }}">
                <i class="ti ti-refresh me-1"></i> Restore
            </button>
        @else
            <a href="{{ route('branch.edit.view', $child->id) }}" class="btn btn-sm btn-label-warning d-inline-flex align-items-center me-1">
                <i class="ti ti-pencil me-1"></i> Edit
            </a>
            <button type="button" class="btn btn-sm btn-label-danger d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="{{ $child->id }}" data-name="{{ $child->name }}">
                <i class="ti ti-trash me-1"></i> Delete
            </button>
        @endif
    </td>
</tr>

@if($child->children->count() > 0)
    <tbody class="child-rows" id="child-rows-{{ $child->id }}">
        @foreach ($child->children as $grandchild)
            @include('admin.business.branch.partials.child-row', ['child' => $grandchild, 'level' => $level + 1])
        @endforeach
    </tbody>
@endif
