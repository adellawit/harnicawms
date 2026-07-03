<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $purchase->po_kind_label }} - {{ $purchase->purchase_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 0; padding: 24px; }
        .header-table { width: 100%; border-bottom: 2px solid #333; margin-bottom: 14px; padding-bottom: 10px; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .company-name { font-size: 16px; font-weight: bold; margin: 0 0 4px; }
        .company-meta { font-size: 10px; color: #444; line-height: 1.5; }
        .doc-box { text-align: right; }
        .doc-box h2 { margin: 0; font-size: 15px; text-transform: uppercase; }
        .doc-type { font-size: 11px; color: #555; margin-top: 4px; }
        .doc-number { font-size: 13px; font-weight: bold; margin-top: 4px; }
        .meta { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .meta td { vertical-align: top; padding: 2px 8px 2px 0; }
        .meta .label { font-weight: bold; width: 110px; color: #444; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th { background: #f0f0f0; border: 1px solid #bbb; padding: 5px 4px; font-size: 9px; text-align: left; }
        table.items td { border: 1px solid #ccc; padding: 4px; vertical-align: top; font-size: 10px; }
        table.items .text-right { text-align: right; }
        table.items .text-center { text-align: center; }
        .footer-grid { width: 100%; margin-top: 12px; border-collapse: collapse; }
        .footer-grid td { vertical-align: top; }
        .notes-box { padding: 8px; border: 1px solid #ddd; background: #fafafa; min-height: 60px; font-size: 10px; }
        .totals { width: 260px; border-collapse: collapse; margin-left: auto; }
        .totals td { padding: 3px 6px; font-size: 10px; }
        .totals .label { text-align: right; color: #444; }
        .totals .value { text-align: right; font-weight: bold; width: 110px; }
        .totals .grand td { border-top: 2px solid #333; font-size: 12px; padding-top: 6px; }
        .signatures { width: 100%; margin-top: 30px; border-collapse: collapse; }
        .signatures td { width: 33.33%; text-align: center; vertical-align: top; padding: 0 10px; }
        .signatures .role { font-weight: bold; font-size: 11px; margin-bottom: 46px; }
        .signatures .sign-line { border-bottom: 1px solid #333; width: 85%; margin: 0 auto 6px; }
        .footer { margin-top: 24px; font-size: 9px; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    @php
        $docTitle = match($purchase->po_kind ?? 'standalone') {
            'master' => 'Contract Purchase Order',
            'sub' => 'Release Order',
            default => 'Purchase Order',
        };
    @endphp

    <table class="header-table">
        <tr>
            <td width="55%">
                <div class="company-name">{{ $company?->brand_name ?: ($company?->name ?: config('app.name')) }}</div>
                <div class="company-meta">
                    @if($company?->legal_name && $company?->legal_name !== $company?->name)
                        {{ $company->legal_name }}<br>
                    @endif
                    @if($company?->address){{ $company->address }}@endif
                    @if($company?->city){{ $company->city ? ', '.$company->city : '' }}@endif
                    @if($company?->province){{ $company->province ? ', '.$company->province : '' }}@endif
                    <br>
                    @if($company?->phone)Telp: {{ $company->phone }}@endif
                    @if($company?->npwp){{ $company?->phone ? ' | ' : '' }}NPWP: {{ $company->npwp }}@endif
                </div>
            </td>
            <td width="45%" class="doc-box">
                <h2>{{ $docTitle }}</h2>
                <div class="doc-type">Type: {{ $purchase->po_kind_label }}</div>
                <div class="doc-number">No. {{ $purchase->purchase_number }}</div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td width="50%">
                <table class="meta">
                    <tr><td class="label">Tanggal</td><td>: {{ $purchase->purchase_date?->format('d/m/Y') ?: '-' }}</td></tr>
                    <tr><td class="label">Tgl Kirim</td><td>: {{ $purchase->expected_delivery_date?->format('d/m/Y') ?: '-' }}</td></tr>
                    @if($purchase->parent)
                        <tr><td class="label">Ref. CPO</td><td>: {{ $purchase->parent->purchase_number }}</td></tr>
                    @endif
                </table>
            </td>
            <td width="50%">
                <table class="meta">
                    <tr><td class="label">Kepada Yth</td><td>: {{ $purchase->supplier_name }}</td></tr>
                    @if($purchase->attention_to)
                        <tr><td class="label">Up.</td><td>: {{ $purchase->attention_to }}</td></tr>
                    @elseif($purchase->supplier_contact)
                        <tr><td class="label">Up.</td><td>: {{ $purchase->supplier_contact }}</td></tr>
                    @endif
                    @if($purchase->supplier_address)
                        <tr><td class="label">Alamat</td><td>: {{ $purchase->supplier_address }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="text-center" style="width:24px;">No</th>
                <th style="width:70px;">Kode</th>
                <th>Nama Barang</th>
                <th class="text-center" style="width:40px;">Qty</th>
                <th class="text-center" style="width:45px;">Satuan</th>
                <th class="text-center" style="width:45px;">Karton</th>
                @if($showPrices)
                    <th class="text-right" style="width:65px;">Harga</th>
                    <th class="text-right" style="width:55px;">Disc</th>
                    <th class="text-right" style="width:70px;">Jumlah</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($purchase->items as $i => $item)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $item->product?->code ?: ($item->variant?->sku ?: '-') }}</td>
                    <td>
                        {{ $item->product?->name ?: '-' }}
                        @if($item->variant?->sku)
                            <br><span style="font-size:9px;color:#666;">SKU: {{ $item->variant->sku }}</span>
                        @endif
                    </td>
                    <td class="text-right">{{ format_number((float) $item->quantity, 2, true) }}</td>
                    <td class="text-center">{{ $item->unit ? ($item->unit->symbol ?: $item->unit->name) : '-' }}</td>
                    <td class="text-right" style="font-size:9px;">{{ $item->carton_display_label !== '-' ? $item->carton_display_label : '-' }}</td>
                    @if($showPrices)
                        <td class="text-right">{{ format_number((float) $item->unit_price, 2, true) }}</td>
                        <td class="text-right">{{ format_number((float) $item->discount_amount, 2, true) }}</td>
                        <td class="text-right">{{ format_number((float) $item->subtotal, 2, true) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="footer-grid">
        <tr>
            <td width="55%" style="padding-right: 12px;">
                <div class="notes-box">
                    <strong>Keterangan:</strong><br>
                    {{ $purchase->notes ?: '-' }}
                    @if($purchase->ship_to_address)
                        <br><br><strong>Ship to:</strong><br>{{ $purchase->ship_to_address }}
                    @endif
                </div>
            </td>
            <td width="45%">
                @if($showPrices)
                    <table class="totals">
                        <tr><td class="label">Sub Total</td><td class="value">{{ format_number((float) $purchase->subtotal, 2, true) }}</td></tr>
                        <tr><td class="label">Disc</td><td class="value">{{ format_number((float) $purchase->discount_amount, 2, true) }}</td></tr>
                        <tr><td class="label">PPN</td><td class="value">{{ format_number((float) $purchase->tax_amount, 2, true) }}</td></tr>
                        <tr><td class="label">Biaya Lain</td><td class="value">{{ format_number((float) ($purchase->other_cost_amount ?? 0), 2, true) }}</td></tr>
                        <tr class="grand"><td class="label">Total</td><td class="value">{{ format_number((float) $purchase->total, 2, true) }}</td></tr>
                    </table>
                @endif
            </td>
        </tr>
    </table>

    <table class="signatures">
        <tr>
            <td><div class="role">Dibuat</div><div class="sign-line"></div><div>( ........................... )</div></td>
            <td><div class="role">Diketahui</div><div class="sign-line"></div><div>( ........................... )</div></td>
            <td><div class="role">Disetujui</div><div class="sign-line"></div><div>( ........................... )</div></td>
        </tr>
    </table>

    <div class="footer">Dicetak {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
