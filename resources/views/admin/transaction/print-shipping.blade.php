@extends('layouts.print')

@section('title', 'Surat Jalan '.$order->sales_number.' | ')

@push('head')
    <style>
        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
            margin-top: 2.5rem;
        }
        .sign-box {
            text-align: center;
            min-height: 140px;
        }
        .sign-box .role {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 4.5rem;
        }
        .sign-box .line {
            border-top: 1px solid var(--ink);
            margin: 0 auto;
            width: 80%;
            padding-top: 0.35rem;
            font-size: 11px;
            color: var(--muted);
        }
        .note-box {
            margin-top: 1rem;
            border: 1px dashed var(--line);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 12px;
            color: var(--muted);
        }
    </style>
@endpush

@section('content')
    @php
        $branch = $order->branch;
        $company = $branch?->parent;
        $issuerName = $branch?->brand_name
            ?: $company?->brand_name
            ?: $branch?->name
            ?: config('app.name');
        $shipAddress = $order->customer_address
            ?: $order->customer?->address_shipping
            ?: $order->customer?->address
            ?: null;
        $shipContact = $order->customer_contact
            ?: $order->customer?->phone
            ?: null;
        $totalQty = $order->items->sum(fn ($i) => (float) $i->quantity);
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
            <h1>SURAT JALAN</h1>
            <div><strong>{{ $order->sales_number }}</strong></div>
            <div class="muted">{{ $order->sales_date?->format('d M Y') ?? '-' }}</div>
            @if($order->expected_delivery_date)
                <div class="muted">Est. kirim: {{ $order->expected_delivery_date->format('d M Y') }}</div>
            @endif
        </div>
    </div>

    <div class="meta-grid">
        <div class="box">
            <h3>Dikirim Kepada</h3>
            <p><strong>{{ $order->customer_name ?: 'Walk-in Customer' }}</strong></p>
            @if($order->customer?->code)
                <p class="muted">{{ $order->customer->code }}</p>
            @endif
            @if($shipContact)
                <p class="muted">{{ $shipContact }}</p>
            @endif
            @if($shipAddress)
                <p class="muted">{{ $shipAddress }}</p>
            @endif
        </div>
        <div class="box">
            <h3>Info Pengiriman</h3>
            <p>No. Transaksi: <strong>{{ $order->sales_number }}</strong></p>
            <p>Tipe: <strong>{{ strtoupper((string) $order->order_type) }}</strong></p>
            <p>Gudang: <strong>{{ $order->warehouse?->name ?? '-' }}</strong></p>
            @if($order->shippingMetaLabel())
                <p>Kurir: <strong>{{ $order->shippingMetaLabel() }}</strong></p>
            @endif
            @if($order->shipping_tracking_number)
                <p>No. Resi: <strong>{{ $order->shipping_tracking_number }}</strong></p>
            @endif
            @if((float) $order->shipping_amount > 0)
                <p>Ongkir: <strong>Rp {{ format_number($order->shipping_amount, 2, true) }}</strong></p>
            @endif
            @if($order->reference)
                <p>Referensi: <strong>{{ $order->reference }}</strong></p>
            @endif
            <p>Total item: <strong>{{ $order->items->count() }}</strong> · Qty: <strong>{{ format_number($totalQty, 2, true) }}</strong></p>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:36px">No</th>
                <th>Item</th>
                <th style="width:90px">SKU</th>
                <th style="width:70px">Unit</th>
                <th class="text-end" style="width:80px">Qty</th>
                <th style="width:140px">Keterangan</th>
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
                        $variantLabel = trim(collect([$attrs])->filter()->implode(' · '));
                    }
                    $sku = $item->variant?->sku ?: ($item->product?->sku ?? '-');
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
                    <td>{{ $sku }}</td>
                    <td>{{ $item->unit?->symbol ?: ($item->unit?->name ?? '-') }}</td>
                    <td class="text-end">{{ format_number($item->quantity, 2, true) }}</td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($order->notes)
        <div class="note-box">
            <strong>Catatan:</strong> {{ $order->notes }}
        </div>
    @endif

    <div class="note-box">
        Barang telah diperiksa dan diterima dalam kondisi baik. Surat jalan ini bukan bukti pembayaran.
    </div>

    <div class="sign-grid">
        <div class="sign-box">
            <div class="role">Pengirim</div>
            <div class="line">( ........................ )</div>
        </div>
        <div class="sign-box">
            <div class="role">Driver / Kurir</div>
            <div class="line">( ........................ )</div>
        </div>
        <div class="sign-box">
            <div class="role">Penerima</div>
            <div class="line">( ........................ )</div>
        </div>
    </div>

    <div class="footer">
        <div>Dicetak: {{ now()->format('d M Y H:i') }}</div>
        <div>Surat Jalan · {{ $order->sales_number }}</div>
    </div>
@endsection
