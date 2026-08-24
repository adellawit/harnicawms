@extends('layouts.agent-order')

@section('title', 'Materi Pemasaran | ')

@section('shop_body_class')
    agent-materials-page
@endsection

@push('body-top')
    <div class="bg-shapes" aria-hidden="true">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
    </div>
@endpush

@section('content')
    <header class="shop-page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="small text-muted text-uppercase">Portal Agen · Web Order</div>
            <h1 class="shop-page-title mb-0">Materi Pemasaran</h1>
            <p class="text-muted small mb-0">Brosur, poster, template WA, video untuk jualan Anda.</p>
        </div>
        <a href="{{ route('agent-order.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-arrow-left me-1"></i>Kembali
        </a>
    </header>

    @php
        $typeFilters = ['all' => 'Semua', 'image' => 'Gambar', 'pdf' => 'PDF', 'video' => 'Video', 'text' => 'WA'];
        $activeTypeKey = in_array($activeType, ['image', 'pdf', 'video', 'text'], true) ? $activeType : 'all';
        $filterParams = fn (array $extra = []) => array_filter(array_merge([
            'category_id' => $activeCategoryId ?: null,
            'type' => $activeTypeKey !== 'all' ? $activeTypeKey : null,
        ], $extra));
    @endphp

    <div class="shop-chips d-flex flex-wrap gap-2 mb-2">
        @foreach ($typeFilters as $key => $label)
            @php
                $params = $filterParams(['type' => $key === 'all' ? null : $key]);
            @endphp
            <a href="{{ route('agent-order.materials', $params) }}"
                class="btn btn-sm {{ $activeTypeKey === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if ($categories->isNotEmpty())
        <div class="shop-chips d-flex flex-wrap gap-2 mb-3">
            <a href="{{ route('agent-order.materials', $filterParams(['category_id' => null])) }}"
                class="btn btn-sm {{ empty($activeCategoryId) ? 'btn-primary' : 'btn-outline-secondary' }}">Semua kategori</a>
            @foreach ($categories as $category)
                <a href="{{ route('agent-order.materials', $filterParams(['category_id' => $category->id])) }}"
                    class="btn btn-sm {{ $activeCategoryId === $category->id ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $category->name }}</a>
            @endforeach
        </div>
    @endif

    <div class="row g-3">
        @forelse ($assets as $asset)
            @php
                $type = $asset->type;
                $typeIcon = ['pdf' => 'ti-file-type-pdf', 'video' => 'ti-video', 'text' => 'ti-brand-whatsapp'][$type] ?? 'ti-photo';
            @endphp
            <div class="col-6 col-md-4 col-xl-3">
                <div class="card border-0 shadow-sm h-100 agent-asset-card">
                    <div class="agent-asset-thumb position-relative">
                        <span class="agent-asset-badge position-absolute top-0 start-0 m-2">
                            @include('agent.order.partials._marketing-asset-type-badge', ['asset' => $asset])
                        </span>
                        @php($thumb = $asset->thumbnail_url ?: ($asset->type === 'image' ? $asset->file_url : null))
                        @if ($thumb)
                            <img src="{{ $thumb }}" alt="{{ $asset->title }}" loading="lazy"
                                onerror="this.classList.add('d-none'); this.nextElementSibling.classList.replace('d-none', 'd-flex');">
                            <div class="agent-asset-thumb-ph d-none align-items-center justify-content-center">
                                <i class="ti {{ $typeIcon }} fs-1 text-muted"></i>
                            </div>
                        @else
                            <div class="agent-asset-thumb-ph d-flex align-items-center justify-content-center">
                                <i class="ti {{ $typeIcon }} fs-1 text-muted"></i>
                            </div>
                        @endif
                    </div>
                    <div class="card-body p-2 p-md-3 d-flex flex-column">
                        <div class="fw-semibold small text-truncate">{{ $asset->title }}</div>
                        @if ($asset->category)
                            <div class="text-muted small">{{ $asset->category->name }}</div>
                        @endif
                        @if ($type === 'text' && filled($asset->body_text))
                            <p class="small text-muted mt-2 mb-0 flex-grow-1">{{ Str::limit($asset->body_text, 100) }}</p>
                        @endif
                        @if ($type === 'video' && $asset->video_embed_id)
                            <div class="ratio ratio-16x9 mt-2 mb-1">
                                <iframe src="https://www.youtube.com/embed/{{ $asset->video_embed_id }}" title="{{ $asset->title }}" allowfullscreen loading="lazy"></iframe>
                            </div>
                        @endif
                        <div class="mt-2">
                            @include('agent.order.partials._marketing-asset-actions', ['asset' => $asset])
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-sm shop-order-card">
                    <x-empty-state icon="ti ti-photo-off" title="Belum ada materi pemasaran" />
                </div>
            </div>
        @endforelse
    </div>

    @if ($assets->hasPages())
        <div class="mt-3">
            {{ $assets->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif

    @include('agent.order.partials._marketing-asset-copy-script')
@endsection
