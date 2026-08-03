@extends('layouts.agent-order')

@section('title', 'Stok Gudang | ')

@section('content')
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
    </header>

    <div class="card border-0 shadow-sm shop-order-card mb-3">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('agent-order.stock') }}" class="row g-2 align-items-center">
                <div class="col-md-8">
                    <input type="search" name="q" class="form-control" value="{{ $search }}"
                           placeholder="Cari nama produk atau SKU…" autocomplete="off">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="ti ti-search me-1"></i>Cari</button>
                    @if ($search !== '')
                        <a href="{{ route('agent-order.stock') }}" class="btn btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm shop-order-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Produk</th>
                        <th>Varian</th>
                        <th>SKU</th>
                        <th>Satuan</th>
                        <th class="text-end">Stok</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $s)
                        @php
                            $qty = (float) $s->quantity;
                            $variantLabel = $s->variant?->variantAttributes
                                ?->map(fn ($va) => $va->attributeValue?->value)
                                ->filter()
                                ->implode(' / ');
                            $variantLabel = $variantLabel ?: ($s->variant?->display_name ?? '-');
                        @endphp
                        <tr>
                            <td>{{ $s->variant?->product?->name ?? '-' }}</td>
                            <td>{{ $variantLabel }}</td>
                            <td class="text-muted">{{ $s->variant?->sku ?? '-' }}</td>
                            <td>{{ optional($s->variant?->product?->defaultUnit)->symbol ?? optional($s->variant?->product?->defaultUnit)->name ?? '-' }}</td>
                            <td class="text-end text-nowrap">
                                {{ rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') }}
                                @if ($qty <= 0)
                                    <span class="badge bg-label-danger ms-1">Habis</span>
                                @elseif ($qty <= $lowThreshold)
                                    <span class="badge bg-label-warning ms-1">Rendah</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Belum ada stok di gudang Anda.</td>
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
