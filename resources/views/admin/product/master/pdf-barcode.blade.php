<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Print Barcode QR</title>
    <style>
        @page {
            margin: 5mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
        }

        table.sheet {
            width: 100%;
            border-collapse: separate;
            border-spacing: 2mm 1.5mm;
            table-layout: fixed;
        }

        table.sheet td {
            padding: 0;
            vertical-align: top;
        }

        .label-box {
            position: relative;
            width: 50mm;
            height: 15mm;
            border: 0.1mm solid #999;
            overflow: hidden;
            background: #ffffff;
            page-break-inside: avoid;
        }

        .label-box .label-qr {
            position: absolute;
            left: 0;
            top: 0;
            width: 13mm;
            height: 15mm;
            text-align: center;
        }

        .label-box .label-qr img {
            width: 11mm;
            height: 11mm;
            margin-top: 2mm;
        }

        .label-box .label-text {
            position: absolute;
            left: 13mm;
            bottom: 0;
            width: 36.5mm;
            padding: 0 1.5mm 1.4mm 2mm;
        }

        .label-karton {
            width: 50mm;
            height: 50mm;
            border: 0.1mm solid #999;
            background: #ffffff;
            text-align: center;
            padding: 2mm;
            page-break-inside: avoid;
        }

        .label-pack {
            width: 40mm;
            height: 20mm;
            border: 0.1mm solid #999;
            background: #ffffff;
            text-align: center;
            padding: 1.5mm;
            page-break-inside: avoid;
        }

        .brand {
            font-size: 6px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
        }

        .product-name {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            margin-top: 1mm;
        }

        .unit-qty {
            font-size: 7px;
            font-weight: bold;
            margin-top: 1mm;
        }

        .content-summary {
            font-size: 6px;
            line-height: 1.2;
            margin-top: 1mm;
        }

        .distributor-label {
            font-size: 5.5px;
            color: #666666;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
        }

        .distributor-name {
            font-size: 7.5px;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
        }

        .serial {
            font-size: 6.8px;
            font-weight: bold;
            color: #000000;
            font-family: DejaVu Sans Mono, monospace;
            letter-spacing: 0.02em;
            line-height: 1.2;
            white-space: nowrap;
            margin-top: 1mm;
        }

        .barcode-img img {
            width: 34mm;
            height: 10mm;
            margin-top: 1.5mm;
        }

        .label-pack .barcode-img img {
            width: 30mm;
            height: 8mm;
        }
    </style>
</head>
<body>
    @php
        $cols = 3;
        $chunk = array_chunk($labels, $cols);
    @endphp

    <table class="sheet">
        @foreach ($chunk as $rowLabels)
            <tr>
                @for ($c = 0; $c < $cols; $c++)
                    @php $label = $rowLabels[$c] ?? null; @endphp
                    <td style="width: 50mm;">
                        @if ($label)
                            @if (($label['label_type'] ?? 'box') === 'karton')
                                <div class="label-karton">
                                    <div class="brand">{{ $distributorName }}</div>
                                    <div class="product-name">{{ $label['product_name'] ?? $productName }}</div>
                                    <div class="unit-qty">1 {{ $label['unit_label'] ?? '' }}</div>
                                    @if (! empty($label['content_summary']))
                                        <div class="content-summary">{{ $label['content_summary'] }}</div>
                                    @endif
                                    <div class="barcode-img">
                                        <img src="{{ $label['qr_data_uri'] }}" alt="Barcode">
                                    </div>
                                    <div class="serial">{{ $label['serial'] }}</div>
                                </div>
                            @elseif (($label['label_type'] ?? 'box') === 'pack')
                                <div class="label-pack">
                                    <div class="brand">{{ $distributorName }}</div>
                                    <div class="product-name">{{ $label['product_name'] ?? $productName }}</div>
                                    @if (! empty($label['content_summary']))
                                        <div class="content-summary">{{ $label['content_summary'] }}</div>
                                    @endif
                                    <div class="barcode-img">
                                        <img src="{{ $label['qr_data_uri'] }}" alt="Barcode">
                                    </div>
                                    <div class="serial">{{ $label['serial'] }}</div>
                                </div>
                            @else
                                <div class="label-box">
                                    <div class="label-qr">
                                        <img src="{{ $label['qr_data_uri'] }}" alt="QR">
                                    </div>
                                    <div class="label-text">
                                        <div class="distributor-label">distributed by:</div>
                                        <div class="distributor-name">{{ $distributorName }}</div>
                                        <div class="serial">{{ $label['serial'] }}</div>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>
</html>
