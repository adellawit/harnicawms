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
        $boxBrandLabel = str_starts_with(strtoupper($distributorName), 'HARNICA ')
            ? $distributorName
            : 'HARNICA '.$distributorName;
    @endphp
    <div class="label-box">
        <div class="label-qr">
            <img src="{{ $qrSrc }}" alt="QR">
        </div>
        <div class="label-text">
            <div class="serial">{{ $label['serial'] }}</div>
            <div class="distributor-name">{{ $boxBrandLabel }}</div>
        </div>
    </div>
@endif
