<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Barcode — {{ $product->name }}</title>
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}">
    <style>
        :root {
            --label-w: 50mm;
            --label-h: 30mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, sans-serif;
            background: #f5f5f9;
            color: #32475c;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            background: #fff;
            border-bottom: 1px solid #e4e6ef;
            box-shadow: 0 2px 6px rgba(67, 89, 113, 0.08);
        }

        .toolbar h1 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .toolbar small {
            display: block;
            color: #697a8d;
            font-weight: 400;
        }

        .toolbar-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
        }

        .field label {
            display: block;
            font-size: 0.75rem;
            color: #697a8d;
            margin-bottom: 4px;
        }

        .field select,
        .field input {
            height: 36px;
            border: 1px solid #d9dee3;
            border-radius: 6px;
            padding: 0 10px;
            font-size: 0.875rem;
            background: #fff;
        }

        .btn {
            height: 36px;
            border: none;
            border-radius: 6px;
            padding: 0 14px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary { background: #696cff; color: #fff; }
        .btn-secondary { background: #8592a3; color: #fff; }
        .btn-outline { background: #fff; color: #32475c; border: 1px solid #d9dee3; }

        .panel {
            margin: 16px 20px;
            padding: 16px;
            background: #fff;
            border: 1px solid #e4e6ef;
            border-radius: 8px;
        }

        .variant-picks {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .variant-picks label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border: 1px solid #e4e6ef;
            border-radius: 6px;
            font-size: 0.8125rem;
            cursor: pointer;
            user-select: none;
        }

        .variant-picks input { margin: 0; }

        .preview-wrap {
            padding: 20px;
        }

        .labels-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .label-card {
            width: var(--label-w);
            min-height: var(--label-h);
            border: 1px dashed #c7cdd4;
            border-radius: 4px;
            padding: 4px 6px;
            background: #fff;
            text-align: center;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .label-card .product-name {
            font-size: 8px;
            font-weight: 600;
            line-height: 1.2;
            margin-bottom: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .label-card .variant-name {
            font-size: 7px;
            color: #697a8d;
            line-height: 1.2;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .label-card .code-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
        }

        .label-card canvas,
        .label-card svg {
            max-width: 100%;
            height: auto;
        }

        .label-card .qr-wrap {
            display: inline-block;
        }

        .label-card .code-text {
            font-size: 8px;
            font-family: ui-monospace, monospace;
            letter-spacing: 0.02em;
            margin-top: 1px;
        }

        .label-card .sku-text {
            font-size: 7px;
            color: #697a8d;
        }

        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #697a8d;
        }

        @media print {
            body { background: #fff; }
            .toolbar,
            .panel,
            .no-print { display: none !important; }
            .preview-wrap { padding: 0; }
            .labels-grid { gap: 2mm; }
            .label-card {
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <div>
            <h1>Print Barcode <small>{{ $product->name }} ({{ $product->code }})</small></h1>
        </div>
        <div class="toolbar-controls">
            <div class="field">
                <label for="codeType">Tipe Kode</label>
                <select id="codeType">
                    <option value="barcode">Barcode (Batang)</option>
                    <option value="qr">QR Code</option>
                </select>
            </div>
            <div class="field">
                <label for="labelSize">Ukuran Label</label>
                <select id="labelSize">
                    <option value="50x30">50 × 30 mm</option>
                    <option value="40x25">40 × 25 mm</option>
                    <option value="60x40">60 × 40 mm</option>
                </select>
            </div>
            <div class="field">
                <label for="copies">Salinan / Varian</label>
                <input type="number" id="copies" min="1" max="100" value="1">
            </div>
            <button type="button" class="btn btn-outline" id="btnRefresh">
                <i class="ti ti-refresh"></i> Refresh
            </button>
            <button type="button" class="btn btn-primary" id="btnPrint">
                <i class="ti ti-printer"></i> Print
            </button>
            <button type="button" class="btn btn-secondary" onclick="window.close()">Close</button>
        </div>
    </div>

    @if ($labels->isEmpty())
        <div class="empty-state">
            <p>Tidak ada barcode/SKU yang dapat dicetak untuk produk ini.</p>
            <button type="button" class="btn btn-secondary no-print" onclick="window.close()">Close</button>
        </div>
    @else
        <div class="panel no-print">
            <div class="mb-2 fw-semibold" style="font-size:0.875rem;">Pilih varian</div>
            <div class="variant-picks" id="variantPicks">
                @foreach ($labels as $label)
                    <label>
                        <input type="checkbox" class="variant-check" value="{{ $label['id'] }}" checked>
                        <span>{{ $label['variant_label'] }} — {{ $label['barcode'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="preview-wrap">
            <div class="labels-grid" id="labelsGrid"></div>
        </div>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        (function () {
            var labels = @json($labels);

            if (!labels.length) {
                return;
            }

            var grid = document.getElementById('labelsGrid');
            var codeTypeEl = document.getElementById('codeType');
            var labelSizeEl = document.getElementById('labelSize');
            var copiesEl = document.getElementById('copies');

            function selectedVariantIds() {
                return Array.prototype.slice.call(document.querySelectorAll('.variant-check:checked'))
                    .map(function (el) { return el.value; });
            }

            function barcodeFormat(value) {
                return /^\d{13}$/.test(value) ? 'EAN13' : 'CODE128';
            }

            function applyLabelSize() {
                var size = labelSizeEl.value.split('x');
                document.documentElement.style.setProperty('--label-w', size[0] + 'mm');
                document.documentElement.style.setProperty('--label-h', size[1] + 'mm');
            }

            function renderLabels() {
                var selected = selectedVariantIds();
                var type = codeTypeEl.value;
                var copies = Math.max(1, Math.min(100, parseInt(copiesEl.value, 10) || 1));

                applyLabelSize();
                grid.innerHTML = '';

                labels.filter(function (item) {
                    return selected.indexOf(item.id) !== -1;
                }).forEach(function (item) {
                    for (var c = 0; c < copies; c++) {
                        var card = document.createElement('div');
                        card.className = 'label-card';
                        card.innerHTML =
                            '<div class="product-name"></div>' +
                            '<div class="variant-name"></div>' +
                            '<div class="code-wrap"></div>' +
                            '<div class="code-text"></div>' +
                            '<div class="sku-text"></div>';

                        card.querySelector('.product-name').textContent = item.product_name;
                        card.querySelector('.variant-name').textContent = item.variant_label;
                        card.querySelector('.code-text').textContent = item.barcode;
                        card.querySelector('.sku-text').textContent = 'SKU: ' + item.sku;

                        var wrap = card.querySelector('.code-wrap');

                        if (type === 'qr') {
                            var qrHost = document.createElement('div');
                            qrHost.className = 'qr-wrap';
                            wrap.appendChild(qrHost);
                            /* eslint-disable no-new */
                            new QRCode(qrHost, {
                                text: item.barcode,
                                width: 64,
                                height: 64,
                                correctLevel: QRCode.CorrectLevel.M,
                            });
                        } else {
                            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                            wrap.appendChild(svg);
                            try {
                                JsBarcode(svg, item.barcode, {
                                    format: barcodeFormat(item.barcode),
                                    width: 1.2,
                                    height: 34,
                                    displayValue: false,
                                    margin: 0,
                                });
                            } catch (e) {
                                wrap.innerHTML = '<small style="color:#ea5455;">Invalid barcode</small>';
                            }
                        }

                        grid.appendChild(card);
                    }
                });
            }

            document.getElementById('btnRefresh').addEventListener('click', renderLabels);
            document.getElementById('btnPrint').addEventListener('click', function () {
                window.print();
            });
            codeTypeEl.addEventListener('change', renderLabels);
            labelSizeEl.addEventListener('change', renderLabels);
            copiesEl.addEventListener('change', renderLabels);
            document.getElementById('variantPicks').addEventListener('change', renderLabels);

            renderLabels();
        })();
    </script>
</body>
</html>
