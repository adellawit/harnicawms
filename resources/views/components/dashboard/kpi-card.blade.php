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

<div {{ $attributes->merge(['class' => 'card h-100 dashboard-stat-card dashboard-kpi-card shadow-sm border-0']) }}>
    <div class="card-body dashboard-kpi-card__body">
        <div class="dashboard-kpi-card__top">
            <div class="dashboard-kpi-card__content">
                <span class="dashboard-kpi-card__title">{{ $title }}</span>
                <h4 class="dashboard-kpi-card__value" title="{{ $value }}">{{ $value }}</h4>
            </div>

            @if($icon)
                <div class="dashboard-kpi-card__icon {{ $iconBg }}">
                    <i class="{{ $icon }}"></i>
                </div>
            @endif
        </div>

        @if($trend)
            <span class="badge dashboard-kpi-card__trend {{ $badgeClass }}">
                <i class="{{ $trendIcon }}"></i>
                {{ $trend }}
            </span>
        @endif

        @if($subtitle)
            <small class="dashboard-kpi-card__subtitle">{{ $subtitle }}</small>
        @endif
    </div>
</div>
