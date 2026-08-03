@php
    $type = $asset->type;
    $btnClass = ($btnClass ?? 'btn btn-sm btn-outline-primary');
@endphp
@if (in_array($type, ['image', 'pdf'], true) && $asset->file_url)
    <a href="{{ $asset->file_url }}" download target="_blank" rel="noopener" class="{{ $btnClass }}">
        <i class="ti ti-download me-1"></i>Unduh
    </a>
@elseif ($type === 'video' && $asset->link_url)
    <a href="{{ $asset->link_url }}" target="_blank" rel="noopener" class="{{ $btnClass }}">
        <i class="ti ti-external-link me-1"></i>Buka
    </a>
@elseif ($type === 'text')
    <button type="button" class="{{ $btnClass }} btn-copy-asset" data-text="{{ e($asset->body_text ?? '') }}">
        <i class="ti ti-copy me-1"></i>Salin
    </button>
@endif
