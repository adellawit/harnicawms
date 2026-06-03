@props([
    'name' => null,
    'value' => null,
    'id' => null,
    'label' => null,
    'checked' => false,
    'disabled' => false,
])

@php
    $inputId = $id ?? $name . '-' . ($value ?? uniqid());
@endphp

<div class="form-check">
    <input type="radio"
           class="form-check-input"
           id="{{ $inputId }}"
           name="{{ $name }}"
           value="{{ $value }}"
           {{ $checked ? 'checked' : '' }}
           {{ $disabled ? 'disabled' : '' }}
           {{ $attributes->except('name', 'value', 'id', 'label', 'checked', 'disabled') }}>
    @if($label)
        <label class="form-check-label" for="{{ $inputId }}">{{ $label }}</label>
    @endif
</div>
