@php
    $type = $asset->type;
    $label = ['image' => 'IMG', 'pdf' => 'PDF', 'video' => 'VIDEO', 'text' => 'WA'][$type] ?? strtoupper((string) $type);
@endphp
<span class="badge bg-label-{{ $type === 'text' ? 'success' : ($type === 'video' ? 'danger' : 'info') }}">{{ $label }}</span>
