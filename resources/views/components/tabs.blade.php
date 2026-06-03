@props([
    'tabs' => [], // [['id' => 'home', 'label' => 'Home', 'active' => true], ...]
])

<ul class="nav nav-tabs" role="tablist">
    @foreach($tabs as $tab)
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ ($tab['active'] ?? false) ? 'active' : '' }}"
                    id="{{ ($tab['id'] ?? 'tab-' . $loop->index) }}-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#{{ $tab['id'] ?? 'tab-' . $loop->index }}"
                    type="button"
                    role="tab">
                {{ $tab['label'] ?? $tab['id'] }}
            </button>
        </li>
    @endforeach
</ul>
<div class="tab-content" id="{{ $attributes->get('contentId', 'tabContent') }}">
    {{ $slot }}
</div>
