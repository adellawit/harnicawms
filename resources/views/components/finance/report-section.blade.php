@props([
    'title',
    'accent' => 'primary',
    'subtitle' => null,
    'totalLabel' => null,
    'totalAmount' => null,
    'grand' => false,
    'columnLabel' => 'Account',
])

<div {{ $attributes->merge(['class' => 'card fin-section accent-'.$accent.' mb-3']) }}>
    <div class="fin-section-head">
        <div>
            <h5 class="fin-section-title">{{ $title }}</h5>
            @if($subtitle)
                <div class="fin-section-sub">{{ $subtitle }}</div>
            @endif
        </div>
        @if(! is_null($totalAmount))
            <div class="text-end">
                <div class="fin-section-sub">{{ $totalLabel ?? 'Total' }}</div>
                <div class="fin-amount fs-5 {{ (float) $totalAmount < 0 ? 'text-danger' : '' }}">
                    {{ format_number((float) $totalAmount, 2, true) }}
                </div>
            </div>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table fin-table mb-0">
            <thead>
                <tr>
                    <th style="width: 68%">{{ $columnLabel }}</th>
                    <th class="text-end" style="width: 32%">Amount</th>
                </tr>
            </thead>
            <tbody>
                {{ $slot }}
            </tbody>
            @if($totalLabel !== null && ! is_null($totalAmount))
                <tfoot>
                    <tr class="{{ $grand ? 'fin-row-grand' : '' }}">
                        <td>{{ $totalLabel }}</td>
                        <td class="text-end {{ (float) $totalAmount < 0 ? 'text-danger' : '' }}">
                            {{ format_number((float) $totalAmount, 2, true) }}
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
