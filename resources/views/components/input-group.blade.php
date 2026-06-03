@props([
    'label' => null,
])

<div {{ $attributes->merge(['class' => 'mb-3'])->except('label') }}>
    @if($label)
        <label class="form-label">{{ $label }}</label>
    @endif
    <div class="input-group">
        {{ $slot }}
    </div>
</div>
