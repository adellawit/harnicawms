@php
    $type = $label['label_type'] ?? 'box';
    $qrSrc = ($qrBaseUrl ?? '') . ($label['qr_file'] ?? '');
@endphp

@if ($type === 'karton')
    <div class="label-karton">
        <div class="brand">{{ $distributorName }}</div>
        <div class="product-name">{{ $label['product_name'] ?? $productName }}</div>
        <div class="unit-qty">1 {{ $label['unit_label'] ?? '' }}</div>
        @if (! empty($label['content_summary']))
            <div class="content-summary">{{ $label['content_summary'] }}</div>
        @endif
        <div class="barcode-img">
            <img src="{{ $qrSrc }}" alt="Barcode">
        </div>
        <div class="serial">{{ $label['serial'] }}</div>
    </div>
@elseif ($type === 'pack')
    <div class="label-pack">
        <div class="brand">{{ $distributorName }}</div>
        <div class="product-name">{{ $label['product_name'] ?? $productName }}</div>
        @if (! empty($label['content_summary']))
            <div class="content-summary">{{ $label['content_summary'] }}</div>
        @endif
        <div class="barcode-img">
            <img src="{{ $qrSrc }}" alt="Barcode">
        </div>
        <div class="serial">{{ $label['serial'] }}</div>
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
