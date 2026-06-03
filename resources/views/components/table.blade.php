@props([
    'bordered' => false,
    'striped' => false,
    'responsive' => true,
])

@php
    $tableClass = 'table';
    if ($bordered) $tableClass .= ' table-bordered';
    if ($striped) $tableClass .= ' table-striped';
@endphp

@if($responsive)
    <div class="table-responsive">
        <table {{ $attributes->merge(['class' => $tableClass])->except('bordered', 'striped', 'responsive') }}>
            {{ $slot }}
        </table>
    </div>
@else
    <table {{ $attributes->merge(['class' => $tableClass])->except('bordered', 'striped', 'responsive') }}>
        {{ $slot }}
    </table>
@endif
