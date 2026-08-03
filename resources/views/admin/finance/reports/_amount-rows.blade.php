@php
    $maxAbs = 0.0;
    foreach ($rows as $r) {
        $maxAbs = max($maxAbs, abs((float) ($r['amount'] ?? 0)));
    }
@endphp
@forelse($rows as $row)
    @php
        $amount = (float) $row['amount'];
        $isZero = abs($amount) < 0.005;
        $pct = $maxAbs > 0 ? min(100, round(abs($amount) / $maxAbs * 100)) : 0;
        $label = $row['label'] ?? null;
        $account = $row['account'] ?? null;
        $indent = !empty($row['indent']);
        $strong = !empty($row['strong']);
    @endphp
    <tr class="{{ $isZero && ! $strong ? 'fin-row-zero' : '' }} {{ $strong ? 'fin-row-header' : '' }}">
        <td class="{{ $indent ? 'ps-4' : '' }}">
            @if($account)
                <span class="fin-account-code">{{ $account->code }}</span>{{ $account->name }}
            @else
                {{ $label }}
            @endif
        </td>
        <td class="text-end">
            <div class="fin-amount-wrap ms-auto">
                <span class="fin-amount {{ $amount < 0 ? 'text-danger' : '' }} {{ $isZero ? 'text-muted fw-normal' : '' }}">
                    {{ format_number($amount, 2, true) }}
                </span>
                @if($maxAbs > 0 && ! $strong)
                    <div class="fin-bar {{ $amount < 0 ? 'is-neg' : '' }}">
                        <span style="width: {{ $pct }}%"></span>
                    </div>
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="2" class="text-center text-muted py-3">{{ $emptyMessage ?? 'No rows.' }}</td>
    </tr>
@endforelse
