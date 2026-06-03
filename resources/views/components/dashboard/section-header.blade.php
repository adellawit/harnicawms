@props([
    'icon' => null,
    'title',
    'subtitle' => null,
])

<div class="d-flex align-items-center justify-content-between mb-3 mt-4">
    <div>
        <h5 class="section-title d-flex align-items-center mb-1">
            @if($icon)
                <i class="{{ $icon }} me-2"></i>
            @endif
            <span class="fw-semibold">{{ $title }}</span>
        </h5>
        @if($subtitle)
            <p class="text-muted small mb-0">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="d-flex align-items-center gap-2">
        {{ $slot }}
    </div>
</div>

