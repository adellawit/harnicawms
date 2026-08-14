@extends('layouts.print')

@section('title', 'Invoice '.$order->sales_number.' | ')

@section('content')
    @php
        $branch = $order->branch;
        $company = $branch?->parent;
        $issuerName = $branch?->brand_name
            ?: $company?->brand_name
            ?: $branch?->name
            ?: config('app.name');
        $hasItemDiscount = $order->items->sum(fn ($i) => (float) $i->discount_amount) > 0;
    @endphp

    <div class="invoice-header">
        <div>
            <h2 class="brand-name">{{ $issuerName }}</h2>
            @if($branch?->name)
                <div class="muted">{{ $branch->name }}</div>
            @endif
            @if($branch?->address)
                <div class="muted">{{ $branch->address }}</div>
            @endif
            @if($branch?->phone || $branch?->email)
                <div class="muted">
                    {{ collect([$branch?->phone, $branch?->email])->filter()->implode(' · ') }}
                </div>
            @endif
        </div>
        <div class="invoice-title">
            <h1>INVOICE</h1>
            <div><strong>{{ $order->sales_number }}</strong></div>
            <div class="muted">{{ $order->sales_date?->format('d M Y') ?? '-' }}</div>
            <div style="margin-top:0.4rem">
                <span class="badge">{{ strtoupper((string) $order->payment_status) }}</span>
            </div>
        </div>
    </div>

    <div class="meta-grid">
        <div class="box">
            <h3>Bill To</h3>
            <p><strong>{{ $order->customer_name ?: 'Walk-in Customer' }}</strong></p>
            @if($order->customer?->code)
                <p class="muted">{{ $order->customer->code }}</p>
            @endif
            @if($order->customer_contact)
                <p class="muted">{{ $order->customer_contact }}</p>
            @endif
            @if($order->customer_address)
                <p class="muted">{{ $order->customer_address }}</p>
            @endif
        </div>
        <div class="box">
            <h3>Payment Info</h3>
            <p>Method: <strong>{{ $order->methodPayment?->name ?? '-' }}</strong></p>
            <p>Order Type: <strong>{{ strtoupper((string) $order->order_type) }}</strong></p>
            <p>Status: <strong>{{ ucfirst((string) $order->status) }}</strong></p>
            @if($order->paid_at)
                <p>Paid At: <strong>{{ $order->paid_at->format('d M Y H:i') }}</strong></p>
            @endif
            @if($order->notes)
                <p class="muted" style="margin-top:0.4rem">Notes: {{ $order->notes }}</p>
            @endif
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:36px">No</th>
                <th>Item</th>
                <th style="width:70px">Unit</th>
                <th class="text-end" style="width:70px">Qty</th>
                <th class="text-end" style="width:110px">Price</th>
                @if($hasItemDiscount)
                    <th class="text-end" style="width:100px">Discount</th>
                @endif
                <th class="text-end" style="width:120px">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $i => $item)
                @php
                    $variantLabel = null;
                    if ($item->variant) {
                        $attrs = $item->variant->variantAttributes
                            ->map(fn ($va) => ($va->attributeDefinition->name ?? '').': '.($va->attributeValue->value ?? ''))
                            ->filter()
                            ->join(', ');
                        $variantLabel = trim(collect([$item->variant->sku, $attrs])->filter()->implode(' · '));
                    }
                @endphp
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>
                        <strong>{{ product_print_name($item->product?->name) }}</strong>
                        @if($variantLabel)
                            <div class="muted">{{ $variantLabel }}</div>
                        @endif
                        @if($item->is_promo_free)
                            <div class="muted">Promo free item</div>
                        @endif
                    </td>
                    <td>{{ $item->unit?->symbol ?: ($item->unit?->name ?? '-') }}</td>
                    <td class="text-end">{{ format_number($item->quantity, 2, true) }}</td>
                    <td class="text-end">Rp {{ format_number($item->unit_price, 2, true) }}</td>
                    @if($hasItemDiscount)
                        <td class="text-end">
                            @if((float) $item->discount_amount > 0)
                                - Rp {{ format_number($item->discount_amount, 2, true) }}
                            @else
                                -
                            @endif
                        </td>
                    @endif
                    <td class="text-end">Rp {{ format_number($item->subtotal, 2, true) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="muted">Subtotal</td>
            <td class="text-end">Rp {{ format_number($order->subtotal, 2, true) }}</td>
        </tr>
        @if((float) $order->item_discount_total > 0)
            <tr>
                <td class="muted">Item Discount</td>
                <td class="text-end">- Rp {{ format_number($order->item_discount_total, 2, true) }}</td>
            </tr>
        @endif
        <tr>
            <td class="muted">Tax {{ $order->tax_enabled ? '('.format_number($order->tax_rate, 0).'%)' : '' }}</td>
            <td class="text-end">Rp {{ format_number($order->tax_amount, 2, true) }}</td>
        </tr>
        @if((float) $order->discount_amount > 0)
            <tr>
                <td class="muted">
                    Discount
                    @if($order->discount_type === 'percent')
                        ({{ format_number($order->discount_value, 0) }}%)
                    @endif
                </td>
                <td class="text-end">- Rp {{ format_number($order->discount_amount, 2, true) }}</td>
            </tr>
        @endif
        @if((float) $order->membership_redeem_discount_amount > 0)
            <tr>
                <td class="muted">Membership Redeem</td>
                <td class="text-end">- Rp {{ format_number($order->membership_redeem_discount_amount, 2, true) }}</td>
            </tr>
        @endif
        @if((float) $order->shipping_amount > 0 || $order->shippingMetaLabel())
            <tr>
                <td class="muted">
                    Ongkir
                    @if($order->shippingMetaLabel())
                        <div>{{ $order->shippingMetaLabel() }}</div>
                    @endif
                </td>
                <td class="text-end">Rp {{ format_number($order->shipping_amount, 2, true) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Total</td>
            <td class="text-end">Rp {{ format_number($order->total, 2, true) }}</td>
        </tr>
    </table>

    @if($order->payments->isNotEmpty())
        <div class="box" style="margin-top:1.25rem">
            <h3>Payments</h3>
            @foreach($order->payments as $payment)
                <p>
                    {{ $payment->created_at?->format('d M Y H:i') ?? '-' }}
                    · {{ $payment->methodPayment?->name ?? '-' }}
                    · Rp {{ format_number($payment->amount, 2, true) }}
                    · {{ ucfirst((string) $payment->status) }}
                </p>
            @endforeach
        </div>
    @endif

    <div class="footer">
        <div>Dicetak: {{ now()->format('d M Y H:i') }}</div>
        <div>Terima kasih atas pembelian Anda.</div>
    </div>
@endsection
