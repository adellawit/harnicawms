@props([
    'type' => 'button',
    'variant' => 'default', // default, outline, label
    'color' => 'primary', // primary, success, warning, info, danger, secondary, dark, light
    'size' => null, // sm, lg
    'icon' => null, // tabler icon class e.g. ti-check, ti-plus
    'iconPosition' => 'start', // start, end
])

@php
    $baseClass = 'btn';
    $variantClass = match($variant) {
        'outline' => "btn-outline-{$color}",
        'label' => "btn-label-{$color}",
        default => "btn-{$color}",
    };
    $sizeClass = $size ? "btn-{$size}" : '';
    $classes = trim("{$baseClass} {$variantClass} {$sizeClass}");
@endphp

<button {{ $attributes->merge([
    'type' => $type,
    'class' => $classes,
])->except('variant', 'color', 'size', 'icon', 'iconPosition') }}>
    @if($icon && $iconPosition === 'start')
        <i class="ti {{ $icon }} me-1"></i>
    @endif
    {{ $slot }}
    @if($icon && $iconPosition === 'end')
        <i class="ti {{ $icon }} ms-1"></i>
    @endif
</button>
