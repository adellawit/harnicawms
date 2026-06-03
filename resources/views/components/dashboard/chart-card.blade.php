@props([
    'title',
    'actions' => null,
])

<x-card {{ $attributes->merge(['class' => 'shadow-sm border-0']) }}>
    <x-slot:header>
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold">{{ $title }}</h6>
            @if($actions)
                <div class="d-flex align-items-center gap-2">
                    {{ $actions }}
                </div>
            @endif
        </div>
    </x-slot:header>

    {{ $slot }}
</x-card>

