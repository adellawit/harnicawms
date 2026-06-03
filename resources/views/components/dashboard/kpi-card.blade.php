@props([
    'title',
    'value',
    'subtitle' => null,
    'trend' => null,
    'trendType' => null,
    'icon' => null,
    'iconColor' => 'primary',
])

@php
    $badgeClass = match ($trendType) {
        'up' => 'bg-success-subtle text-success',
        'down' => 'bg-danger-subtle text-danger',
        default => 'bg-secondary-subtle text-secondary',
    };

    $trendIcon = match ($trendType) {
        'up' => 'ti ti-arrow-up',
        'down' => 'ti ti-arrow-down',
        default => 'ti ti-minus',
    };

    $iconBg = "bg-label-{$iconColor}";
@endphp

<div {{ $attributes->merge(['class' => 'card h-100 dashboard-stat-card shadow-sm border-0']) }}>
    <div class="card-body pb-2">
        <div class="d-flex align-items-start justify-content-between mb-2">
            <div class="flex-grow-1 me-2">
                <small class="text-muted text-uppercase fw-medium d-block mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">{{ $title }}</small>
                <h4 class="mb-0 fw-bold" style="font-size: 1.15rem;">{{ $value }}</h4>
            </div>

            @if($icon)
                <div class="rounded {{ $iconBg }} d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 38px; height: 38px; border-radius: 8px !important;">
                    <i class="{{ $icon }}" style="font-size: 1.1rem;"></i>
                </div>
            @endif
        </div>

        @if($trend)
            <span class="badge {{ $badgeClass }}" style="font-size: 0.68rem;">
                <i class="{{ $trendIcon }}" style="font-size: 0.65rem;"></i>
                {{ $trend }}
            </span>
        @endif

        @if($subtitle)
            <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">{{ $subtitle }}</small>
        @endif
    </div>
</div>
