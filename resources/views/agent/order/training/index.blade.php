@extends('layouts.agent-order')

@section('title', 'Pelatihan | ')

@section('content')
    <header class="shop-page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="small text-muted text-uppercase">Portal Agen · Web Order</div>
            <h1 class="shop-page-title mb-0">Pelatihan</h1>
            <p class="text-muted small mb-0">Materi pelatihan untuk agen.</p>
        </div>
        <a href="{{ route('agent-order.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-arrow-left me-1"></i>Kembali
        </a>
    </header>

    <div class="row g-3 g-md-4">
        @forelse ($courses as $course)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('agent-order.training.show', $course->id) }}"
                    class="card border-0 shadow-sm h-100 agent-course-card text-decoration-none text-body">
                    <div class="agent-asset-thumb position-relative">
                        @if ($course->thumbnail_url)
                            <img src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}" loading="lazy">
                        @else
                            <div class="agent-asset-thumb-ph d-flex align-items-center justify-content-center">
                                <i class="ti ti-school fs-1 text-muted"></i>
                            </div>
                        @endif
                    </div>
                    <div class="card-body p-2 p-md-3">
                        @if ($course->category)
                            <span class="badge bg-label-secondary mb-2">{{ $course->category->name }}</span>
                        @endif
                        <h2 class="h6 fw-semibold mb-1 text-truncate" title="{{ $course->title }}">{{ $course->title }}</h2>
                        @if ($course->description)
                            <p class="small text-muted mb-0">{{ Str::limit(strip_tags($course->description), 120) }}</p>
                        @else
                            <p class="small text-muted mb-0">Materi pelatihan untuk agen</p>
                        @endif
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-sm shop-order-card">
                    <div class="card-body shop-empty-state text-center text-muted py-5">
                        <i class="ti ti-school-off d-block fs-1 mb-2 opacity-50"></i>
                        Belum ada materi pelatihan.
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection
