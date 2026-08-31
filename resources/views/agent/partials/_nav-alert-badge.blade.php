@php
    $count = (int) ($count ?? 0);
    $label = $label ?? 'transaksi POS reseller belum lunas';
@endphp
@if ($count > 0)
    <span class="badge bg-danger rounded-pill shop-nav-alert-badge"
        title="{{ $count }} {{ $label }}"
        aria-label="{{ $count }} {{ $label }}">{{ $count > 99 ? '99+' : $count }}</span>
@endif
