<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Batch Labels - {{ $receive->receive_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #222;
            margin: 0;
            padding: 12px;
        }
        .page-title {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 6px;
        }
        .labels {
            width: 100%;
            border-collapse: collapse;
        }
        .labels td {
            width: 50%;
            vertical-align: top;
            padding: 6px;
        }
        .label-card {
            border: 1.5px solid #333;
            border-radius: 4px;
            padding: 10px 12px;
            min-height: 110px;
            page-break-inside: avoid;
        }
        .label-product {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 2px;
            line-height: 1.3;
        }
        .label-code {
            font-size: 9px;
            color: #555;
            margin-bottom: 8px;
        }
        .label-batch {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin: 6px 0 4px;
            word-break: break-all;
        }
        .label-batch-caption {
            font-size: 8px;
            text-transform: uppercase;
            color: #777;
            letter-spacing: 0.4px;
        }
        .label-meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 10px;
        }
        .label-meta td {
            padding: 1px 0;
            width: auto;
        }
        .label-meta .k { color: #666; width: 55px; }
        .label-footer {
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px dashed #bbb;
            font-size: 8px;
            color: #777;
        }
    </style>
</head>
<body>
    @php
        $copies = max(1, (int) ($copies ?? 1));
        $cards = collect();
        foreach ($labels as $item) {
            for ($c = 0; $c < $copies; $c++) {
                $cards->push($item);
            }
        }
        $chunks = $cards->chunk(2);
    @endphp

    <div class="page-title">
        {{ $company?->brand_name ?: ($company?->name ?: config('app.name')) }}
        &mdash; Batch Labels &mdash; {{ $receive->receive_number }}
        &mdash; {{ $receive->receive_date?->format('d/m/Y') }}
        &mdash; PO {{ $receive->purchaseOrder?->purchase_number }}
    </div>

    <table class="labels">
        @foreach($chunks as $pair)
            <tr>
                @foreach($pair as $item)
                    <td>
                        <div class="label-card">
                            <div class="label-product">{{ $item->product?->name ?? '-' }}</div>
                            <div class="label-code">
                                {{ $item->product?->code ?: '-' }}
                                @if($item->variant?->sku)
                                    | {{ $item->variant->sku }}
                                @endif
                            </div>

                            <div class="label-batch-caption">Kode Batch</div>
                            <div class="label-batch">{{ $item->batch_number }}</div>

                            <table class="label-meta">
                                <tr>
                                    <td class="k">Expired</td>
                                    <td>: <strong>{{ $item->expiry_date?->format('d/m/Y') ?: '-' }}</strong></td>
                                </tr>
                            </table>

                            <div class="label-footer">
                                {{ $receive->receive_number }}
                                @if($receive->warehouse?->code)
                                    | {{ $receive->warehouse->code }}
                                @endif
                                | {{ $receive->receive_date?->format('d/m/Y') }}
                            </div>
                        </div>
                    </td>
                @endforeach
                @if($pair->count() === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>
</body>
</html>
