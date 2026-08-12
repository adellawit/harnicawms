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
        $payStatus = $order->payment_status === 'paid' ? 'LUNAS' : 'BELUM LUNAS';
        $payMethod = $order->payments->first()?->methodPayment?->name
            ?? $order->methodPayment?->name
            ?? '-';
        $variantLabel = function ($item) {
            $attrs = $item->variant?->variantAttributes
                ?->map(fn ($va) => $va->attributeValue?->value)
                ->filter()
                ->implode(' / ');
            return $attrs ?: null;
        };
        $shippingLabel = trim(collect([
            $order->shipping_courier ? strtoupper($order->shipping_courier) : null,
            $order->shipping_service,
            $order->shipping_etd ? '('.$order->shipping_etd.')' : null,
        ])->filter()->implode(' '));
    @endphp

    <table class="header-table">
        <tr>
            <td width="55%">
                <div class="company-name">{{ $company?->brand_name ?: ($company?->name ?: config('app.name')) }}</div>
                <div class="company-meta">
                    @if ($company?->legal_name && $company?->legal_name !== $company?->name)
                        {{ $company->legal_name }}<br>
                    @endif
                    @if ($company?->address){{ $company->address }}@endif
                    @if ($company?->city){{ $company->city ? ', '.$company->city : '' }}@endif
                    @if ($company?->province){{ $company->province ? ', '.$company->province : '' }}@endif
                    <br>
                    @if ($company?->phone)Telp: {{ $company->phone }}@endif
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
            <td width="50%" style="padding-right: 12px;">
                <div class="party-title">Tagih Kepada</div>
                <strong>{{ $agent?->name ?: ($order->customer_name ?: '-') }}</strong>
                @if ($agent?->code)
                    <span style="color:#666;"> ({{ $agent->code }})</span>
                @endif
                <br>
                @if ($order->customer_contact){{ $order->customer_contact }}<br>@endif
                @if ($order->customer_address){{ $order->customer_address }}@endif
            </td>
            <td width="50%">
                <table class="meta">
                    <tr><td class="label">Metode bayar</td><td>: {{ $payMethod }}</td></tr>
                    <tr><td class="label">Status order</td><td>: {{ ucfirst((string) $order->status) }}</td></tr>
                    @if ($order->paid_at)
                        <tr><td class="label">Dibayar</td><td>: {{ $order->paid_at->format('d M Y H:i') }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="text-center" style="width:28px;">No</th>
                <th>Produk</th>
                <th class="text-center" style="width:45px;">Qty</th>
                <th class="text-right" style="width:75px;">Harga</th>
                <th class="text-right" style="width:85px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $i => $item)
                @php $vLabel = $variantLabel($item); @endphp
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>
                        {{ $item->product?->name ?? '-' }}
                        @if ($vLabel)
                            <br><span class="variant-sub">{{ $vLabel }}</span>
                        @endif
                    </td>
                    <td class="text-center">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="text-right">{{ $fmt($item->unit_price) }}</td>
                    <td class="text-right">{{ $fmt($item->subtotal) }}</td>
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
                    @if ($order->tax_enabled && (float) $order->tax_amount > 0)
                        <tr><td class="label">PPN ({{ rtrim(rtrim(number_format((float) $order->tax_rate, 2, ',', '.'), '0'), ',') }}%)</td><td class="value">{{ $fmt($order->tax_amount) }}</td></tr>
                    @endif
                    @if ((float) $order->discount_amount > 0)
                        <tr><td class="label">Diskon</td><td class="value">- {{ $fmt($order->discount_amount) }}</td></tr>
                    @endif
                    <tr>
                        <td class="label">Ongkir</td>
                        <td class="value">
                            {{ $fmt($order->shipping_amount ?? 0) }}
                            @if ($shippingLabel)
                                <br><span style="font-weight:normal;font-size:9px;">{{ $shippingLabel }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr class="grand"><td class="label">Total</td><td class="value">{{ $fmt($order->total) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">Dicetak {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
