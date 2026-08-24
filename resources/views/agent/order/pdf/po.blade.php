<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Purchase Order - {{ $order->sales_number }}</title>
    @include('agent.order.pdf._styles')
</head>
<body>
    @php
        $orderDate = $order->sales_date ?? $order->created_at;
        $fmt = fn ($val) => 'Rp '.number_format((float) $val, 0, ',', '.');
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
                <h2>Purchase Order</h2>
                <div class="doc-number">No. {{ $order->sales_number }}</div>
                <div class="doc-meta">Tanggal: {{ $orderDate?->format('d M Y') ?? '-' }}</div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td width="50%" style="padding-right: 12px;">
                <strong>{{ $company?->brand_name ?: ($company?->name ?: '-') }}</strong><br>
                @if ($company?->address){{ $company->address }}<br>@endif
                @if ($company?->city || $company?->province)
                    {{ collect([$company?->city, $company?->province])->filter()->implode(', ') }}<br>
                @endif
            </td>
            <td width="50%">
                <strong>{{ $agent?->name ?: ($order->customer_name ?: '-') }}</strong>
                @if ($agent?->code)
                    <span style="color:#666;"> ({{ $agent->code }})</span>
                @endif
                <br>
                @if ($agent?->address){{ $agent->address }}<br>@endif
                @if ($agent?->city || $agent?->province)
                    {{ collect([$agent?->city, $agent?->province])->filter()->implode(', ') }}<br>
                @endif
                @if ($order->customer_address)
                    <br><strong>Alamat kirim:</strong><br>{{ $order->customer_address }}
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="text-center" style="width:28px;">No</th>
                <th>Produk</th>
                <th class="text-center" style="width:50px;">Satuan</th>
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
                        {{ $item->product ? product_print_name($item->product->name) : '-' }}
                        @if ($vLabel)
                            <br><span class="variant-sub">{{ $vLabel }}</span>
                        @endif
                    </td>
                    <td class="text-center">{{ $item->unit?->symbol ?: ($item->unit?->name ?? '-') }}</td>
                    <td class="text-center">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="text-right">{{ $fmt($item->unit_price) }}</td>
                    <td class="text-right">{{ $fmt($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="footer-grid">
        <tr>
            <td width="55%" style="padding-right: 12px;">
                <div class="notes-box">
                    <strong>Catatan:</strong><br>
                    {{ $order->notes ?: '-' }}
                </div>
            </td>
            <td width="45%">
                <table class="totals">
                    <tr><td class="label">Subtotal</td><td class="value">{{ $fmt($order->subtotal) }}</td></tr>
                    @if ((float) $order->discount_amount > 0)
                        <tr><td class="label">Diskon</td><td class="value">- {{ $fmt($order->discount_amount) }}</td></tr>
                    @endif
                    @if ((float) $order->tax_amount > 0)
                        <tr><td class="label">PPN ({{ rtrim(rtrim(number_format((float) $order->tax_rate, 2, ',', '.'), '0'), ',') }}%)</td><td class="value">{{ $fmt($order->tax_amount) }}</td></tr>
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
