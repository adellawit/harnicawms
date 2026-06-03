@props([
    'id',
    'active' => false,
])

<div class="tab-pane fade {{ $active ? 'show active' : '' }}"
     id="{{ $id }}"
     role="tabpanel">
    {{ $slot }}
</div>
