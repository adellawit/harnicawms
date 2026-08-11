@extends('layouts.agent-order')

@section('title', 'Stok Gudang | ')

@push('styles')
    <style>
        .agent-stock-table td { vertical-align: middle; }
        .agent-stock-table .stock-col-qty { min-width: 160px; max-width: 280px; }
        .agent-stock-unit-detail .stock-unit-line { word-break: break-word; }
        .agent-stock-header-actions .btn-group .btn { font-size: 0.8125rem; }
    </style>
@endpush

@section('content')
    @php
        $displayUnitMode = $displayUnitMode ?? request('display_unit', 'large');
        $unitToggleQuery = collect(request()->query())
            ->except('display_unit')
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();
        $largeUnitUrl = route('agent-order.stock', array_merge($unitToggleQuery, ['display_unit' => 'large']));
        $smallUnitUrl = route('agent-order.stock', array_merge($unitToggleQuery, ['display_unit' => 'small']));
    @endphp

    <header class="shop-page-header d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <a href="{{ route('agent-order.dashboard') }}" class="shop-back-link d-inline-flex align-items-center gap-1 small text-muted text-decoration-none mb-2">
                <i class="ti ti-arrow-left"></i> Beranda
            </a>
            <h1 class="shop-page-title mb-1">Stok Gudang Saya</h1>
            <p class="text-muted small mb-0">
                {{ $warehouseName ?: 'Gudang belum diset' }}
            </p>
        </div>
        <div class="agent-stock-header-actions">
            <div class="btn-group" role="group" aria-label="Tampilan satuan">
                <a href="{{ $largeUnitUrl }}" class="btn btn-sm btn-{{ $displayUnitMode === 'large' ? 'primary' : 'outline-primary' }}">
                    Satuan Besar
                </a>
                <a href="{{ $smallUnitUrl }}" class="btn btn-sm btn-{{ $displayUnitMode === 'small' ? 'primary' : 'outline-primary' }}">
                    Satuan Kecil
                </a>
            </div>
        </div>
    </header>

    <div class="card border-0 shadow-sm shop-order-card mb-3">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('agent-order.stock') }}" class="row g-2 align-items-center">
                @if ($displayUnitMode !== 'large')
                    <input type="hidden" name="display_unit" value="{{ $displayUnitMode }}">
                @endif
                <div class="col-md-8">
                    <input type="search" name="q" class="form-control" value="{{ $search }}"
                           placeholder="Cari nama produk atau SKU…" autocomplete="off">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="ti ti-search me-1"></i>Cari</button>
                    @if ($search !== '')
                        <a href="{{ route('agent-order.stock', $displayUnitMode !== 'large' ? ['display_unit' => $displayUnitMode] : []) }}" class="btn btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm shop-order-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle agent-stock-table">
                <thead class="table-light">
                    <tr>
                        <th>Produk</th>
                        <th>Varian</th>
                        <th>SKU</th>
                        <th class="text-end stock-col-qty">Stok</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $item)
                        @php
                            $displayQty = (float) ($item['quantity'] ?? 0);
                        @endphp
                        <tr>
                            <td>{{ $item['product_name'] ?? '-' }}</td>
                            <td>{{ $item['variant_name'] ?? '-' }}</td>
                            <td class="text-muted">{{ $item['sku'] ?? '-' }}</td>
                            <td class="text-end stock-col-qty">
                                <div class="fw-semibold">
                                    {{ format_number($displayQty, 2, true) }}
                                    <small class="text-muted">{{ $item['unit'] ?? '' }}</small>
                                </div>
                                @if (!empty($item['all_units']) && count($item['all_units']) > 1)
                                    <div class="agent-stock-unit-detail stock-unit-detail mt-1">
                                        <small class="text-muted d-block fw-semibold">Setara di semua satuan:</small>
                                        @foreach ($item['all_units'] as $unitStock)
                                            <small class="text-muted d-block stock-unit-line">
                                                {{ format_number((float) $unitStock['quantity'], 2, true) }} {{ $unitStock['unit'] }}
                                            </small>
                                        @endforeach
                                    </div>
                                @endif
                                @if (!empty($item['conversion_chain_hint']))
                                    <small class="text-muted d-block fst-italic mt-1">{{ $item['conversion_chain_hint'] }}</small>
                                @elseif (!empty($item['conversion_hint']))
                                    <small class="text-muted d-block fst-italic mt-1">{{ $item['conversion_hint'] }}</small>
                                @endif
                                <div class="mt-1">
                                    @if (!empty($item['out']))
                                        <span class="badge bg-label-danger">Habis</span>
                                    @elseif (!empty($item['low']))
                                        <span class="badge bg-label-warning">Rendah</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Belum ada stok di gudang Anda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($stocks->hasPages())
            <div class="card-footer">{{ $stocks->links() }}</div>
        @endif
    </div>
@endsection
