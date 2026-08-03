@php
    $fieldId = $fieldId ?? 'city';
    $fieldName = $fieldName ?? 'city_id';
    $label = $label ?? 'Kota';
    $selectedId = $selectedId ?? null;
    $selectedText = $selectedText ?? null;
    $required = $required ?? true;
    $tooltip = $tooltip ?? null;
@endphp
<div class="col-md-6">
    <label class="form-label" for="{{ $fieldId }}">
        {{ $label }} @if($required)<span class="text-danger">*</span>@endif
        @if($tooltip)
            <i class="ti ti-help-circle text-primary ms-1"
               style="cursor: help;"
               data-bs-toggle="tooltip"
               data-bs-placement="top"
               data-bs-custom-class="shipping-rate-tooltip"
               data-bs-title="{{ $tooltip }}"
               title="{{ $tooltip }}"
               aria-label="{{ $tooltip }}"></i>
        @endif
    </label>
    <select id="{{ $fieldId }}" name="{{ $fieldName }}" class="form-select shipping-city-select" style="width:100%;"
            data-placeholder="Cari kota (min 2 karakter)"
            @if($required) required @endif>
        @if($selectedId)
            <option value="{{ $selectedId }}" selected>{{ $selectedText }}</option>
        @endif
    </select>
    @if($tooltip)
        <div class="form-text">{{ $tooltip }}</div>
    @endif
    @error($fieldName)
        <div class="text-danger small">{{ $message }}</div>
    @enderror
</div>
