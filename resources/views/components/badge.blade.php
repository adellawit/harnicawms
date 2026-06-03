@props([
    'variant' => 'label', // solid, label (bg-label-* vs bg-*)
    'color' => 'primary', // primary, success, warning, info, danger, secondary, dark, light
])

@php
    $class = $variant === 'label' ? "badge bg-label-{$color}" : "badge bg-{$color}";
@endphp

<span {{ $attributes->merge(['class' => $class])->except('variant', 'color') }}>
    {{ $slot }}
</span>
