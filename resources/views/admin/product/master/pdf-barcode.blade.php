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

        /* ── Box 50×12mm ── */
        .label-box {
            position: relative;
            width: 50mm;
            height: 12mm;
            border: 0.1mm solid #999;
            overflow: hidden;
            background: #fff;
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
            padding: 0 1.5mm 1mm 1mm;
        }

        .label-box .serial {
            font-size: 6.5px;
            font-weight: bold;
            font-family: DejaVu Sans Mono, monospace;
            line-height: 1.2;
            white-space: nowrap;
            margin: 0 0 0.4mm;
        }

        .label-box .distributor-name {
            font-size: 6.5px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
        }

        /* ── Karton 50×50mm & Pack 40×40mm ── */
        .label-karton,
        .label-pack {
            border: 0.1mm solid #999;
            background: #fff;
            text-align: center;
            page-break-inside: avoid;
            break-inside: avoid;
            overflow: hidden;
        }

        .label-karton {
            width: 50mm;
            height: 50mm;
            padding: 0.8mm;
        }

        .label-table--karton {
            width: 100%;
            height: 48.4mm;
            border-collapse: collapse;
        }

        .label-table--karton .label-table__content {
            vertical-align: top;
            text-align: center;
            padding: 0;
        }

        .label-table--karton .label-table__footer--karton {
            vertical-align: bottom;
            text-align: center;
            height: 26mm;
            padding: 0;
        }

        .label-karton .label-logo {
            margin-bottom: 0.3mm;
        }

        .label-karton .label-logo img {
            max-width: 46mm;
            max-height: 8mm;
        }

        .label-karton .brand {
            font-size: 7px;
        }

        .label-karton .product-name {
            font-size: 10px;
            margin-top: 0.4mm;
        }

        .label-karton .unit-qty {
            font-size: 8px;
            margin-top: 0.4mm;
        }

        .label-karton .content-summary {
            font-size: 7px;
            margin-top: 0.4mm;
        }

        .label-karton .barcode-img img {
            width: 22mm;
            height: 22mm;
        }

        .label-karton .serial {
            font-size: 7.5px;
            margin-top: 0.4mm;
        }

        .label-pack {
            width: 40mm;
            height: 40mm;
            padding: 1.2mm;
        }

        .label-table--pack {
            width: 100%;
            height: 37.6mm;
            border-collapse: collapse;
        }

        .label-table--pack .label-table__content {
            vertical-align: top;
            text-align: center;
            padding: 0;
        }

        .label-table--pack .label-table__footer--pack {
            vertical-align: bottom;
            text-align: center;
            height: 20mm;
            padding: 2mm 0 0;
        }

        .label-table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
        }

        .label-table__content {
            vertical-align: top;
            text-align: center;
            padding: 0;
        }

        .label-table__footer {
            vertical-align: bottom;
            text-align: center;
            padding: 0;
        }

        .label-logo {
            line-height: 0;
            margin-bottom: 0.5mm;
        }

        .label-pack .label-logo img {
            max-width: 36mm;
            max-height: 7mm;
        }

        .label-pack .brand {
            font-size: 6.5px;
        }

        .label-pack .product-name {
            font-size: 9px;
            margin-top: 0.4mm;
        }

        .label-pack .content-summary {
            font-size: 7px;
            margin-top: 0.4mm;
            margin-bottom: 0;
        }

        .label-pack .barcode-img {
            margin-top: 0;
        }

        .label-pack .barcode-img img {
            width: 16mm;
            height: 16mm;
        }

        .label-pack .serial {
            font-size: 7px;
            margin-top: 0.4mm;
        }

        .brand {
            font-size: 6px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
        }

        .product-name {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            margin-top: 0.5mm;
        }

        .unit-qty {
            font-size: 7px;
            font-weight: bold;
            margin-top: 0.5mm;
            line-height: 1.15;
        }

        .content-summary {
            font-size: 6px;
            line-height: 1.15;
            margin-top: 0.5mm;
        }

        .barcode-img {
            line-height: 0;
        }

        .serial {
            font-size: 6.8px;
            font-weight: bold;
            font-family: DejaVu Sans Mono, monospace;
            line-height: 1.1;
            white-space: nowrap;
            margin-top: 0.5mm;
        }

        .label-wrap--karton {
            width: 50mm;
        }

        .label-wrap--pack {
            width: 40mm;
        }

        /* ── Hierarchy layout ── */
        .hierarchy-page--level-1-2 {
            page-break-after: always;
            break-after: page;
        }

        .hierarchy-page--level-1-2-last {
            page-break-after: auto;
            break-after: auto;
        }

        .level-1-2-section + .level-1-2-section {
            margin-top: 3mm;
        }

        .cut-block {
            border: 0.5mm dashed #555;
            padding: 2mm;
        }

        .cut-block--karton {
            display: inline-block;
            width: auto;
            background: #fafafa;
        }

        .cut-block--pack,
        .cut-block--box-group {
            display: block;
            width: auto;
            background: #fff;
        }

        .cut-block--box-group {
            page-break-inside: avoid;
            break-inside: avoid;
            margin-bottom: 2mm;
        }

        .box-groups-page .cut-block--box-group:last-child {
            margin-bottom: 0;
        }

        .cut-line {
            font-size: 7px;
            font-weight: bold;
            color: #444;
            text-align: center;
            border-bottom: 0.3mm dashed #aaa;
            margin: -2mm -2mm 2mm;
            padding: 1mm 2mm;
            background: #ececec;
            line-height: 1.2;
        }

        table.pack-grid {
            border-collapse: separate;
            border-spacing: 1.5mm;
            table-layout: fixed;
            width: auto;
        }

        table.pack-grid td {
            width: 40mm;
            vertical-align: top;
            padding: 0;
        }

        .pack-grid__item-title {
            font-size: 6px;
            font-weight: bold;
            text-align: center;
            color: #444;
            margin-bottom: 0.5mm;
            line-height: 1;
        }

        table.box-grid {
            border-collapse: separate;
            border-spacing: 2mm 1.5mm;
            table-layout: fixed;
            width: auto;
        }

        table.box-grid td {
            width: 50mm;
            height: 12mm;
            padding: 0;
            vertical-align: top;
        }

        /* ── Single mode ── */
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
        @foreach ($labelTree as $kartonIndex => $kartonNode)
            @php
                $packNodes = $kartonNode['children'] ?? [];
                $packCount = count($packNodes);
                $packUnitLabel = strtoupper($packNodes[0]['unit_label'] ?? 'PACK');
                $packRangeLabel = $packCount > 0 ? '1-'.$packCount : '';
                $packCols = 6;
                $packRows = array_chunk($packNodes, $packCols);
                $hasBoxGroups = collect($packNodes)->contains(fn ($packNode) => count($packNode['children'] ?? []) > 0);
                $isLastKarton = $kartonIndex === count($labelTree) - 1;
                $level12Class = 'hierarchy-page hierarchy-page--level-1-2'.((! $hasBoxGroups && $isLastKarton) ? ' hierarchy-page--level-1-2-last' : '');
            @endphp

            <div class="{{ $level12Class }}">
                <div class="level-1-2-section">
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
                </div>

                @if ($packCount > 0)
                    <div class="level-1-2-section">
                        <div class="cut-block cut-block--pack">
                            <div class="cut-line">
                                &#9986; POTONG &mdash; {{ $packUnitLabel }}
                                @if ($packRangeLabel !== '')
                                    : {{ $packRangeLabel }}
                                @endif
                            </div>
                            <table class="pack-grid" cellpadding="0" cellspacing="0">
                                @foreach ($packRows as $packRow)
                                    <tr>
                                        @foreach ($packRow as $packNode)
                                            <td>
                                                <div class="pack-grid__item-title">{{ $packNode['ordinal'] }} {{ $packUnitLabel }}</div>
                                                @if (! empty($packNode['label']))
                                                    <div class="label-wrap label-wrap--pack">
                                                        @include('admin.product.master.pdf-barcode-label', ['label' => $packNode['label']])
                                                    </div>
                                                @endif
                                            </td>
                                        @endforeach
                                        @for ($c = count($packRow); $c < $packCols; $c++)
                                            <td></td>
                                        @endfor
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            @if ($hasBoxGroups)
                <div class="hierarchy-page hierarchy-page--level-3">
                    <div class="box-groups-page">
                        @foreach ($packNodes as $packNode)
                            @php
                                $packUnitLabel = strtoupper($packNode['unit_label'] ?? 'PACK');
                                $boxChildren = collect($packNode['children'] ?? []);
                                $boxLabels = $boxChildren->pluck('label')->filter()->values()->all();
                                $boxRows = array_chunk($boxLabels, $boxCols);
                                $boxSerials = $boxChildren
                                    ->map(fn ($child) => $child['serial'] ?? ($child['label']['serial'] ?? null))
                                    ->filter()
                                    ->values();
                                $boxRangeLabel = $boxSerials->count() > 1
                                    ? $boxSerials->first().'-'.$boxSerials->last()
                                    : ($boxSerials->first() ?? '');
                            @endphp

                            @if (count($boxRows) > 0)
                                <div class="cut-block cut-block--box-group">
                                    <div class="cut-line">
                                        &#9986; POTONG &mdash; {{ $packNode['ordinal'] }} {{ $packUnitLabel }}
                                        @if ($boxRangeLabel !== '')
                                            : {{ $boxRangeLabel }}
                                        @endif
                                    </div>
                                    <table class="box-grid" cellpadding="0" cellspacing="0">
                                        @foreach ($boxRows as $rowLabels)
                                            <tr>
                                                @for ($c = 0; $c < $boxCols; $c++)
                                                    <td>
                                                        @if (! empty($rowLabels[$c]))
                                                            @include('admin.product.master.pdf-barcode-label', ['label' => $rowLabels[$c]])
                                                        @endif
                                                    </td>
                                                @endfor
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    @else
        @php
            $chunk = array_chunk($labels ?? [], $boxCols);
        @endphp

        <table class="sheet">
            @foreach ($chunk as $rowLabels)
                <tr>
                    @for ($c = 0; $c < $boxCols; $c++)
                        <td>
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
