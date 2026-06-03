@props([
    'id',
    'title',
    'size' => null, // null, 'sm', 'lg', 'xl'
    'centered' => true,
])

<div {{ $attributes->merge(['class' => 'modal fade']) }} id="{{ $id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog {{ $centered ? 'modal-dialog-centered' : '' }} {{ $size ? "modal-{$size}" : '' }}">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{ $slot }}
            </div>
            @isset($footer)
                <div class="modal-footer">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
