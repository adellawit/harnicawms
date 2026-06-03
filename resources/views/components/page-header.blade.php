@props([
    'title' => null,
    'subtitle' => null,
    'breadcrumbs' => [],
])

@if(!empty($breadcrumbs))
    <nav aria-label="breadcrumb" id="layout-breadcrumb-source" class="d-none">
        <ol class="breadcrumb mb-0">
            @foreach($breadcrumbs as $crumb)
                @if(!empty($crumb['active']))
                    <li class="breadcrumb-item active" aria-current="page">{{ $crumb['label'] }}</li>
                @else
                    <li class="breadcrumb-item">
                        <a href="{{ $crumb['url'] ?? 'javascript:void(0);' }}">{{ $crumb['label'] }}</a>
                    </li>
                @endif
            @endforeach
        </ol>
    </nav>
@endif

@if($title || $subtitle)
    <div class="mb-4">
        @if($title)
            <h4 class="fw-bold mb-1">{{ $title }}</h4>
        @endif
        @if($subtitle)
            <p class="text-muted mb-0">{{ $subtitle }}</p>
        @endif
    </div>
@endif
