@props([
    'type' => 'success', // success, danger, warning, info
    'dismissible' => true,
    'icon' => true,
])

@php
    $iconClass = match($type) {
        'success' => 'ti-check',
        'danger' => 'ti-ban',
        'warning' => 'ti-alert-triangle',
        'info', 'primary' => 'ti-info-circle',
        default => 'ti-info-circle',
    };
@endphp

<div {{ $attributes->merge(['class' => "alert alert-{$type} d-flex align-items-center" . ($dismissible ? ' alert-dismissible' : '')]) }} role="alert">
    @if($icon)
        <span class="alert-icon text-{{ $type }} me-2">
            <i class="ti {{ $iconClass }} ti-xs"></i>
        </span>
    @endif
    <div class="flex-grow-1">
        {{ $slot }}
    </div>
    @if($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif
</div>
