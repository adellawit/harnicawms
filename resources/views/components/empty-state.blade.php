@props([
    'icon' => 'ti ti-inbox',
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'empty-state text-center']) }}>
    <div class="empty-state-icon mx-auto mb-2">
        <i class="{{ $icon }}"></i>
    </div>
    @if ($title)
        <div class="fw-semibold mb-1">{{ $title }}</div>
    @endif
    @if ($subtitle)
        <div class="small text-muted">{{ $subtitle }}</div>
    @endif
    {{ $slot }}
</div>
