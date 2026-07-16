@php
    $type = $label['label_type'] ?? 'box';
    $qrSrc = ($qrBaseUrl ?? '') . ($label['qr_file'] ?? '');
    $harnicaLogoSrc = $harnicaLogoSrc ?? ('file://' . str_replace('\\', '/', public_path('assets/img/harnica/logo.png')));
@endphp

@if ($type === 'karton')
    <div class="label-karton">
        <table class="label-table label-table--karton" cellpadding="0" cellspacing="0">
            <tr>
                <td class="label-table__content">
                    <div class="label-logo">
                        <img src="{{ $harnicaLogoSrc }}" alt="Harnica">
                    </div>
                    <div class="product-name">{{ $label['product_name'] ?? $productName }}</div>
                    <div class="unit-qty">1 {{ $label['unit_label'] ?? '' }}</div>
                    @if (! empty($label['content_summary']))
                        <div class="content-summary">{{ $label['content_summary'] }}</div>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label-table__footer label-table__footer--karton">
                    <div class="barcode-img">
                        <img src="{{ $qrSrc }}" alt="Barcode">
                    </div>
                    <div class="serial">{{ $label['serial'] }}</div>
                </td>
            </tr>
        </table>
    </div>
@elseif ($type === 'pack')
    <div class="label-pack">
        <table class="label-table label-table--pack" cellpadding="0" cellspacing="0">
            <tr>
                <td class="label-pack__meta-cell">
                    <div class="product-name">{{ $label['product_name'] ?? $productName }}</div>
                    @if (! empty($label['content_summary']))
                        <div class="content-summary">{{ $label['content_summary'] }}</div>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label-pack__value-cell">
                    <div class="barcode-img">
                        <img src="{{ $qrSrc }}" alt="Barcode">
                    </div>
                    <div class="serial">{{ $label['serial'] }}</div>
                </td>
            </tr>
        </table>
    </div>
@else
    @php
        $distName = $distributorName ?? '';
        $boxBrandLabel = str_starts_with(strtoupper($distName), 'HARNICA ')
            ? $distName
            : 'HARNICA '.$distName;
        $boxPad = '0.5mm';
        $innerH = '7mm';
        $qrCol = '7mm';
        $qrSize = '6mm';
        $qrGap = '0.5mm';
    @endphp
    <div class="label-box" style="width:45mm;height:9mm;border:0.35mm solid #000;background:transparent;overflow:hidden;box-sizing:border-box;padding:{{ $boxPad }};">
        <table class="label-box__table" cellpadding="0" cellspacing="0" style="width:100%;height:{{ $innerH }};border-collapse:collapse;table-layout:fixed;">
            <colgroup>
                <col style="width: {{ $qrCol }};">
                <col>
            </colgroup>
            <tr style="height:{{ $innerH }};">
                <td class="label-box__qr-cell" valign="middle" align="center" style="width:{{ $qrCol }};height:{{ $innerH }};padding:{{ $qrGap }};box-sizing:border-box;vertical-align:middle;text-align:center;">
                    <table cellpadding="0" cellspacing="0" style="width:100%;height:100%;border-collapse:collapse;">
                        <tr>
                            <td align="center" valign="middle" style="width:100%;height:100%;padding:0;text-align:center;vertical-align:middle;">
                                <img src="{{ $qrSrc }}" alt="QR" style="width:{{ $qrSize }};height:{{ $qrSize }};display:block;margin:0 auto;">
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="label-box__text-cell" valign="middle" style="height:{{ $innerH }};vertical-align:middle;text-align:left;padding:0 0.6mm 0 0.9mm;">
                    <div class="distributed-by" style="font-size:4.5pt;line-height:1.1;margin:0;padding:0;color:#000;">Distributed by :</div>
                    <div class="distributor-name" style="font-size:6pt;font-weight:bold;line-height:1.1;margin:0;padding:0;color:#000;text-transform:uppercase;">{{ $boxBrandLabel }}</div>
                </td>
            </tr>
        </table>
    </div>
@endif
