<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $order->sales_number }}</title>
    @include('agent.order.pdf._styles')
</head>
<body>
    @php
        $orderDate = $order->sales_date ?? $order->created_at;
        $fmt = fn ($val) => 'Rp '.number_format((float) $val, 0, ',', '.');
        $payStatus = $order->payment_status === 'paid' ? 'LUNAS' : 'BELUM DIBAYAR';
        $variantLabel = function ($item) {
            $attrs = $item->variant?->variantAttributes
                ?->map(fn ($va) => $va->attributeValue?->value)
                ->filter()
                ->implode(' / ');

            return $attrs ?: null;
        };
    @endphp

    <table class="header-table">
        <tr>
            <td width="55%">
                <div class="company-name">{{ $agent?->name ?: 'Agen' }}</div>
                <div class="company-meta">
                    @if ($agent?->address){{ $agent->address }}<br>@endif
                    @if ($agent?->phone)Telp: {{ $agent->phone }}@endif
                </div>
            </td>
            <td width="45%" class="doc-box">
                <h2>Invoice</h2>
                <div class="doc-number">No. {{ $order->sales_number }}</div>
                <div class="doc-meta">Tanggal: {{ $orderDate?->format('d M Y') ?? '-' }}</div>
                <div class="doc-meta" style="margin-top:8px;">
                    <span class="status-badge">{{ $payStatus }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td width="55%">
                <div class="party-title">Ditagihkan kepada</div>
                <strong>{{ $order->customer_name ?: ($order->customer?->name ?: '-') }}</strong>
                @if ($order->customer?->code)
                    <span style="color:#666;"> ({{ $order->customer->code }})</span>
                @endif
                <br>
                @if ($order->customer_address){{ $order->customer_address }}<br>@endif
                @if ($order->customer_contact){{ $order->customer_contact }}@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="text-center" style="width:28px;">#</th>
                <th>Produk</th>
                <th class="text-center" style="width:45px;">Qty</th>
                <th class="text-center" style="width:55px;">Satuan</th>
                <th class="text-right" style="width:75px;">Harga</th>
                <th class="text-right" style="width:85px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $i => $item)
                @php
                    $line = (float) $item->quantity * (float) $item->unit_price - (float) ($item->discount_amount ?? 0);
                    $vLabel = $variantLabel($item);
                @endphp
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>
                        {{ $item->product?->name ?? '-' }}
                        @if ($vLabel)
                            <br><span class="variant-sub">{{ $vLabel }}</span>
                        @endif
                    </td>
                    <td class="text-center">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="text-center">{{ $item->unit?->symbol ?? ($item->unit?->name ?? '-') }}</td>
                    <td class="text-right">{{ $fmt($item->unit_price) }}</td>
                    <td class="text-right">{{ $fmt($line) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="footer-grid">
        <tr>
            <td width="55%"></td>
            <td width="45%">
                <table class="totals">
                    <tr><td class="label">Subtotal</td><td class="value">{{ $fmt($order->subtotal) }}</td></tr>
                    @if ((float) $order->item_discount_total > 0)
                        <tr><td class="label">Diskon Item</td><td class="value">- {{ $fmt($order->item_discount_total) }}</td></tr>
                    @endif
                    @if ((float) $order->discount_amount > 0)
                        <tr><td class="label">Diskon Transaksi</td><td class="value">- {{ $fmt($order->discount_amount) }}</td></tr>
                    @endif
                    @if ((float) $order->shipping_amount > 0)
                        <tr><td class="label">Ongkir</td><td class="value">{{ $fmt($order->shipping_amount) }}</td></tr>
                    @endif
                    <tr class="grand"><td class="label">TOTAL</td><td class="value">{{ $fmt($order->total) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">Invoice ini sah tanpa tanda tangan. Terima kasih.</div>
</body>
</html>
