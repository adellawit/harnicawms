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

        /* ── Box label 50×12mm (5cm × 1.2cm) ── */
        .label-box {
            position: relative;
            width: 50mm;
            height: 12mm;
            border: 0.1mm solid #999;
            overflow: hidden;
            background: #ffffff;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .label-box .label-qr {
            position: absolute;
            left: 0;
            top: 0;
            width: 12mm;
            height: 12mm;
            text-align: center;
        }

        .label-box .label-qr img {
            width: 10mm;
            height: 10mm;
            margin-top: 1mm;
        }

        .label-box .label-text {
            position: absolute;
            left: 12mm;
            bottom: 0;
            width: 38mm;
            height: auto;
            padding: 0 1.5mm 1mm 1mm;
        }

        .label-box .serial {
            font-size: 6.5px;
            font-weight: bold;
            font-family: DejaVu Sans Mono, monospace;
            letter-spacing: 0.02em;
            line-height: 1.2;
            white-space: nowrap;
            margin-top: 0;
            margin-bottom: 0.4mm;
        }

        .label-box .distributor-name {
            font-size: 6.5px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
        }

        /* ── Karton 50×50mm ── */
        .label-karton {
            display: block;
            width: 50mm;
            max-width: 50mm;
            height: 50mm;
            border: 0.1mm solid #999;
            background: #ffffff;
            text-align: center;
            padding: 2mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* ── Pack 40×40mm ── */
        .label-pack {
            display: block;
            width: 40mm;
            max-width: 40mm;
            height: 40mm;
            border: 0.1mm solid #999;
            background: #ffffff;
            text-align: center;
            padding: 1.5mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .label-wrap {
            margin: 0 0 2mm 0;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .label-wrap--karton {
            width: 50mm;
        }

        .label-wrap--pack {
            width: 40mm;
            margin-bottom: 0;
        }

        /* Pack (kiri) + Box grid (kanan) sejajar atas */
        table.pack-layout {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        table.pack-layout td {
            vertical-align: top;
            padding: 0;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        table.pack-layout .pack-layout__pack {
            width: 40mm;
            padding-right: 2mm;
        }

        table.pack-layout .pack-layout__boxes {
            vertical-align: top;
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
            width: 22mm;
            height: 22mm;
            margin-top: 1.5mm;
        }

        .label-pack .barcode-img img {
            width: 18mm;
            height: 18mm;
        }

        /* ── Hierarchy sections ── */
        .hierarchy-root {
            width: 100%;
        }

        .cut-block {
            border: 0.5mm dashed #555;
            padding: 3mm;
        }

        .cut-block--karton {
            background: #fafafa;
        }

        .cut-block--pack {
            background: #ffffff;
        }

        .cut-line {
            font-size: 7px;
            font-weight: bold;
            color: #444;
            text-align: center;
            border-bottom: 0.3mm dashed #aaa;
            margin: -3mm -3mm 3mm -3mm;
            padding: 1.2mm 2mm;
            background: #ececec;
        }

        table.page-unit {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        table.page-unit td {
            padding: 0;
            vertical-align: top;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        table.page-unit--pack td {
            padding-left: 0;
        }

        /* Grid box: kiri sejajar dengan label Pack di atasnya */
        table.box-grid {
            border-collapse: separate;
            border-spacing: 2mm 1.5mm;
            table-layout: fixed;
            width: auto;
            margin: 0;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        table.box-grid td {
            width: 50mm;
            height: 12mm;
            padding: 0;
            vertical-align: top;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        table.box-grid tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Single-mode */
        table.sheet {
            width: 100%;
            border-collapse: separate;
            border-spacing: 2mm 1.5mm;
            table-layout: fixed;
        }

        table.sheet td {
            width: 50mm;
            height: 12mm;
            padding: 0;
            vertical-align: top;
        }

        .cut-wrap {
            border: 0.4mm dashed #888;
            padding: 1mm;
            display: inline-block;
        }
    </style>
</head>
<body>
    @php
        $qrBaseUrl = $qrBaseUrl ?? '';
        $printMode = $printMode ?? 'single';
        $labelTree = $labelTree ?? null;
        $boxCols = 5;
    @endphp

    @if ($printMode === 'hierarchy' && ! empty($labelTree))
        <div class="hierarchy-root">
            @foreach ($labelTree as $kartonNode)
                <table class="page-unit page-unit--karton" cellpadding="0" cellspacing="0" style="page-break-inside: avoid !important;">
                    <tr>
                        <td>
                            <div class="cut-block cut-block--karton">
                                <div class="cut-line">
                                    &#9986; POTONG &mdash; {{ $kartonNode['ordinal'] }} {{ strtoupper($kartonNode['unit_label'] ?? 'KARTON') }}
                                </div>

                                @if (! empty($kartonNode['label']))
                                    <div class="label-wrap label-wrap--karton">
                                        @include('admin.product.master.pdf-barcode-label', ['label' => $kartonNode['label']])
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>

                @foreach ($kartonNode['children'] ?? [] as $packNode)
                    <table class="page-unit page-unit--pack" cellpadding="0" cellspacing="0" style="page-break-inside: avoid !important;">
                        <tr>
                            <td>
                                <div class="cut-block cut-block--pack">
                                    <div class="cut-line">
                                        &#9986; POTONG &mdash; {{ $packNode['ordinal'] }} {{ strtoupper($packNode['unit_label'] ?? 'PACK') }}
                                    </div>

                                    @php
                                        $boxLabels = collect($packNode['children'] ?? [])
                                            ->pluck('label')
                                            ->filter()
                                            ->values()
                                            ->all();
                                        $boxRows = array_chunk($boxLabels, $boxCols);
                                    @endphp

                                    <table class="pack-layout" cellpadding="0" cellspacing="0">
                                        <tr>
                                            @if (! empty($packNode['label']))
                                                <td class="pack-layout__pack">
                                                    <div class="label-wrap label-wrap--pack">
                                                        @include('admin.product.master.pdf-barcode-label', ['label' => $packNode['label']])
                                                    </div>
                                                </td>
                                            @endif
                                            @if (count($boxRows) > 0)
                                                <td class="pack-layout__boxes">
                                                    <table class="box-grid" cellpadding="0" cellspacing="0">
                                                        @foreach ($boxRows as $rowLabels)
                                                            <tr>
                                                                @for ($c = 0; $c < $boxCols; $c++)
                                                                    <td style="width: 50mm;">
                                                                        @if (! empty($rowLabels[$c]))
                                                                            @include('admin.product.master.pdf-barcode-label', ['label' => $rowLabels[$c]])
                                                                        @endif
                                                                    </td>
                                                                @endfor
                                                            </tr>
                                                        @endforeach
                                                    </table>
                                                </td>
                                            @endif
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </table>
                @endforeach
            @endforeach
        </div>
    @else
        @php
            $chunk = array_chunk($labels ?? [], $boxCols);
        @endphp

        <table class="sheet">
            @foreach ($chunk as $rowLabels)
                <tr>
                    @for ($c = 0; $c < $boxCols; $c++)
                        <td style="width: 50mm;">
                            @if (! empty($rowLabels[$c]))
                                @include('admin.product.master.pdf-barcode-label', ['label' => $rowLabels[$c]])
                            @endif
                        </td>
                    @endfor
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
