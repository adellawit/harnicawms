@php
    $maxAbs = 0.0;
    foreach ($rows as $r) {
        $maxAbs = max($maxAbs, abs((float) $r['amount']));
    }
@endphp
@forelse($rows as $row)
    @php
        $account = $row['account'];
        $depth = max(0, (int) $row['depth']);
        $pad = $depth * 1.15;
        $isHeader = !empty($row['is_header']);
        $amount = (float) $row['amount'];
        $isZero = abs($amount) < 0.005;
        $pct = $maxAbs > 0 ? min(100, round(abs($amount) / $maxAbs * 100)) : 0;
        $rowClass = $isHeader ? 'fin-row-header' : ($isZero ? 'fin-row-zero' : '');
    @endphp
    <tr class="{{ $rowClass }}">
        <td>
            <span class="fin-tree" style="padding-left: {{ $pad }}rem; {{ $depth === 0 ? '' : '' }}">
                @if($isHeader)
                    <span class="fin-account-code">{{ $account->code }}</span>
                    <strong>{{ $account->name }}</strong>
                @else
                    <span class="fin-account-code">{{ $account->code }}</span>
                    {{ $account->name }}
                @endif
            </span>
        </td>
        <td class="text-end">
            <div class="fin-amount-wrap ms-auto">
                <span class="fin-amount {{ $isZero ? 'text-muted fw-normal' : '' }} {{ $amount < 0 ? 'text-danger' : '' }}">
                    {{ format_number($amount, 2, true) }}
                </span>
                @if(! $isHeader && $maxAbs > 0)
                    <div class="fin-bar {{ $amount < 0 ? 'is-neg' : '' }}" title="{{ $pct }}%">
                        <span style="width: {{ $pct }}%"></span>
                    </div>
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="2" class="text-center text-muted py-4">No accounts in this section.</td>
    </tr>
@endforelse
