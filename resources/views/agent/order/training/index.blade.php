@extends('layouts.agent-order')

@section('title', 'Pelatihan | ')

@section('shop_body_class')
    agent-training-page
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
                        <x-thumb :url="$course->thumbnail_url" type="course" :alt="$course->title" style="width:100%;height:100%" />
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
                    <x-empty-state icon="ti ti-school-off" title="Belum ada materi pelatihan" />
                </div>
            </div>
        @endforelse
    </div>
@endsection
