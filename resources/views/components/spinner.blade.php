@props([
    'type' => 'border', // border, grow
    'color' => 'primary', // primary, success, warning, danger, secondary
])

@php
    $class = $type === 'grow' ? "spinner-grow text-{$color}" : "spinner-border text-{$color}";
@endphp

<div {{ $attributes->merge(['class' => $class, 'role' => 'status']) }}>
    <span class="visually-hidden">Loading...</span>
</div>
