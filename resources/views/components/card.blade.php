@props([
    'title' => null,
    'headerClass' => '',
])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if($title || isset($header))
        <div class="card-header {{ $headerClass }}">
            @isset($header)
                {{ $header }}
            @else
                <h5 class="card-title mb-0">{{ $title }}</h5>
            @endisset
        </div>
    @endif
    <div class="card-body">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="card-footer">
            {{ $footer }}
        </div>
    @endisset
</div>
