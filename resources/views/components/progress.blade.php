@props([
    'value' => 0,
    'color' => null, // primary, success, warning, danger (null = default)
    'showLabel' => true,
    'height' => '20px',
])

@php
    $barClass = $color ? "progress-bar bg-{$color}" : 'progress-bar';
@endphp

<div {{ $attributes->merge(['class' => 'progress']) }} style="height: {{ $height }};">
    <div class="{{ $barClass }}" role="progressbar"
         style="width: {{ min(100, max(0, $value)) }}%;"
         aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="100">
        @if($showLabel)
            {{ $value }}%
        @endif
    </div>
</div>
