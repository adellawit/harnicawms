@props(['url' => null, 'type' => 'generic', 'alt' => ''])
@php
    $type = in_array($type, ['image','pdf','video','text','course','product'], true) ? $type : 'generic';
@endphp
<div {{ $attributes->merge(['class' => 'thumb-wrap']) }} style="width:100%;height:100%">
    @if ($url)
        <img src="{{ $url }}" alt="{{ $alt }}" loading="lazy"
             style="width:100%;height:100%;object-fit:cover;display:block"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <span class="thumb-illus" style="display:none">@include('components._thumb-illus', ['type' => $type])</span>
    @else
        <span class="thumb-illus">@include('components._thumb-illus', ['type' => $type])</span>
    @endif
</div>
