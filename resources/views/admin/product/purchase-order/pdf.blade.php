<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Purchase Order - {{ $purchase->purchase_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 0; padding: 24px; }
        .header { border-bottom: 2px solid #333; padding-bottom: 12px; margin-bottom: 18px; }
        .header h1 { margin: 0 0 4px; font-size: 18px; text-transform: uppercase; letter-spacing: 0.5px; }
        .header .company { font-size: 10px; color: #555; line-height: 1.5; }
        .doc-title { text-align: right; margin-top: -48px; }
        .doc-title h2 { margin: 0; font-size: 16px; color: #333; }
        .doc-title .po-number { font-size: 13px; font-weight: bold; margin-top: 4px; }
        .meta { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
        .meta td { vertical-align: top; padding: 2px 8px 2px 0; }
        .meta .label { font-weight: bold; width: 130px; color: #444; }
        .section-title { font-size: 12px; font-weight: bold; margin: 14px 0 6px; text-transform: uppercase; color: #333; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th { background: #f0f0f0; border: 1px solid #bbb; padding: 6px 5px; font-size: 10px; text-align: left; }
        table.items td { border: 1px solid #ccc; padding: 5px; vertical-align: top; }
        table.items .text-right { text-align: right; }
        table.items .text-center { text-align: center; }
        .totals { width: 280px; margin-left: auto; margin-top: 12px; border-collapse: collapse; }
        .totals td { padding: 4px 6px; }
        .totals .label { text-align: right; color: #444; }
        .totals .value { text-align: right; font-weight: bold; width: 120px; }
        .totals .grand td { border-top: 2px solid #333; font-size: 12px; padding-top: 6px; }
        .notes { margin-top: 14px; padding: 8px; background: #f9f9f9; border: 1px solid #ddd; font-size: 10px; }
        .signatures { width: 100%; margin-top: 36px; border-collapse: collapse; }
        .signatures td { width: 33.33%; text-align: center; vertical-align: top; padding: 0 12px; }
        .signatures .role { font-weight: bold; font-size: 11px; margin-bottom: 48px; }
        .signatures .sign-line { border-bottom: 1px solid #333; width: 85%; margin: 0 auto 6px; height: 1px; }
        .signatures .name { font-size: 10px; color: #444; }
        .signatures .hint { font-size: 9px; color: #888; margin-top: 2px; }
        .footer { margin-top: 32px; font-size: 9px; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 8px; }
        .variant-detail { font-size: 9px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company?->legal_name ?: ($company?->name ?: config('app.name')) }}</h1>
        <div class="company">
            @if($company?->address){{ $company->address }}@endif
            @if($company?->city){{ $company->city ? ', ' . $company->city : '' }}@endif
            @if($company?->province){{ $company->province ? ', ' . $company->province : '' }}@endif
            <br>
            @if($company?->phone)Telp: {{ $company->phone }}@endif
            @if($company?->email){{ $company->phone ? ' | ' : '' }}Email: {{ $company->email }}@endif
            @if($company?->npwp){{ ($company->phone || $company->email) ? ' | ' : '' }}NPWP: {{ $company->npwp }}@endif
        </div>
        <div class="doc-title">
            <h2>Purchase Order</h2>
            <div class="po-number">{{ $purchase->purchase_number }}</div>
        </div>
    </div>

    <table class="meta">
        <tr>
            <td width="50%">
                <table class="meta">
                    <tr>
                        <td class="label">Tanggal PO</td>
                        <td>: {{ $purchase->purchase_date ? $purchase->purchase_date->format('d M Y') : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Estimasi Kirim</td>
                        <td>: {{ $purchase->expected_delivery_date ? $purchase->expected_delivery_date->format('d M Y') : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Status</td>
                        <td>: {{ $purchase->status_label }}</td>
                    </tr>
                    @if($purchase->branch)
                    <tr>
                        <td class="label">Cabang</td>
                        <td>: {{ $purchase->branch->name }}</td>
                    </tr>
                    @endif
                </table>
            </td>
            <td width="50%">
                <table class="meta">
                    <tr>
                        <td class="label">Supplier</td>
                        <td>: {{ $purchase->supplier_name }}</td>
                    </tr>
                    @if($purchase->supplier_contact)
                    <tr>
                        <td class="label">Kontak</td>
                        <td>: {{ $purchase->supplier_contact }}</td>
                    </tr>
                    @endif
                    @if($purchase->supplier_address)
                    <tr>
                        <td class="label">Alamat</td>
                        <td>: {{ $purchase->supplier_address }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title">Daftar Barang</div>
    <table class="items">
        <thead>
            <tr>
                <th class="text-center" style="width: 28px;">No</th>
                <th>Produk</th>
                <th>Varian</th>
                <th class="text-center" style="width: 50px;">Satuan</th>
                <th class="text-right" style="width: 70px;">Qty</th>
                @if($showPrices)
                    <th class="text-right" style="width: 85px;">Harga Satuan</th>
                    <th class="text-right" style="width: 85px;">Subtotal</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($purchase->items as $i => $item)
                @php
                    $variantAttrs = $item->variant
                        ? $item->variant->variantAttributes->map(fn ($va) => ($va->attributeDefinition->name ?? '') . ': ' . ($va->attributeValue->value ?? ''))->filter()->join(', ')
                        : '';
                @endphp
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>
                        {{ $item->product?->name ?? '-' }}
                        @if($item->product?->code)
                            <br><span class="variant-detail">{{ $item->product->code }}</span>
                        @endif
                    </td>
                    <td>
                        @if($item->variant)
                            {{ $item->variant->sku }}
                            @if($variantAttrs)
                                <br><span class="variant-detail">{{ $variantAttrs }}</span>
                            @endif
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-center">{{ $item->unit ? ($item->unit->symbol ?: $item->unit->name) : '-' }}</td>
                    <td class="text-right">{{ format_number((float) $item->quantity, 2, true) }}</td>
                    @if($showPrices)
                        <td class="text-right">{{ format_number((float) $item->unit_price, 2, true) }}</td>
                        <td class="text-right">{{ format_number((float) $item->subtotal, 2, true) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($showPrices)
        <table class="totals">
            <tr>
                <td class="label">Subtotal</td>
                <td class="value">{{ format_number((float) $purchase->subtotal, 2, true) }}</td>
            </tr>
            <tr>
                <td class="label">Pajak</td>
                <td class="value">{{ format_number((float) $purchase->tax_amount, 2, true) }}</td>
            </tr>
            <tr>
                <td class="label">Diskon</td>
                <td class="value">{{ format_number((float) $purchase->discount_amount, 2, true) }}</td>
            </tr>
            <tr class="grand">
                <td class="label">Total</td>
                <td class="value">{{ format_number((float) $purchase->total, 2, true) }}</td>
            </tr>
        </table>
    @endif

    @if($purchase->notes)
        <div class="notes">
            <strong>Catatan:</strong> {{ $purchase->notes }}
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <div class="role">Penerima</div>
                <div class="sign-line"></div>
                <div class="name">( ................................ )</div>
                <div class="hint">Nama &amp; Tanda Tangan</div>
            </td>
            <td>
                <div class="role">PJT</div>
                <div class="sign-line"></div>
                <div class="name">( ................................ )</div>
                <div class="hint">Nama &amp; Tanda Tangan</div>
            </td>
            <td>
                <div class="role">Perusahaan</div>
                <div class="sign-line"></div>
                <div class="name">( ................................ )</div>
                <div class="hint">Nama &amp; Tanda Tangan</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Dokumen ini dicetak pada {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
