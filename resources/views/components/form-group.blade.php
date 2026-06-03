@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'hint' => null,
])

<div {{ $attributes->merge(['class' => $attributes->get('class', 'mb-3')])->except('label', 'name', 'required', 'hint') }}>
    @if($label)
        <label class="form-label" for="{{ $name }}">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif
    {{ $slot }}
    @if($hint)
        <small class="text-muted">{{ $hint }}</small>
    @endif
    @if($name)
        @error($name)
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    @endif
</div>
