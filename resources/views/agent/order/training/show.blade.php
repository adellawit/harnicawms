@extends('layouts.agent-order')

@section('title', $course->title . ' | ')

@section('content')
    <header class="shop-page-header d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div class="min-w-0">
            <div class="small text-muted text-uppercase">Portal Agen · Pelatihan</div>
            @if ($course->category)
                <span class="badge bg-label-secondary mb-2">{{ $course->category->name }}</span>
            @endif
            <h1 class="shop-page-title mb-1">{{ $course->title }}</h1>
            @if ($course->description)
                <p class="text-muted small mb-0">{{ $course->description }}</p>
            @endif
        </div>
        <a href="{{ route('agent-order.training') }}" class="btn btn-outline-secondary btn-sm flex-shrink-0">
            <i class="ti ti-arrow-left me-1"></i>Kembali
        </a>
    </header>

    @php
        $hasMaterials = $course->modules->contains(fn ($module) => $module->materials->isNotEmpty());
    @endphp

    @forelse ($course->modules as $module)
        <section class="card border-0 shadow-sm shop-order-card mb-3">
            <div class="card-header bg-white border-bottom-0 pb-0">
                <h2 class="h6 fw-semibold mb-1">{{ $module->title }}</h2>
                @if ($module->description)
                    <p class="small text-muted mb-0">{{ $module->description }}</p>
                @endif
            </div>
            <div class="card-body pt-3">
                @forelse ($module->materials as $material)
                    <article class="agent-training-material mb-4 pb-4 border-bottom">
                        <h3 class="h6 fw-semibold mb-2">
                            <i class="ti {{ $material->effective_type === 'video' ? 'ti-brand-youtube' : ($material->effective_type === 'pdf' ? 'ti-file-type-pdf' : 'ti-photo') }} me-1 text-muted"></i>
                            {{ $material->title }}
                        </h3>
                        @php($et = $material->effective_type)
                        @if ($et === 'video')
                            @if ($material->effective_video_embed_id)
                                <div class="ratio ratio-16x9 mb-2">
                                    <iframe src="https://www.youtube.com/embed/{{ $material->effective_video_embed_id }}" title="{{ $material->title }}" allowfullscreen></iframe>
                                </div>
                            @elseif ($material->effective_video_url)
                                <a href="{{ $material->effective_video_url }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                                    <i class="ti ti-external-link me-1"></i>Buka video
                                </a>
                            @else
                                <div class="text-muted small">Materi belum tersedia.</div>
                            @endif
                        @elseif ($et === 'image' && $material->effective_file_url)
                            <img src="{{ $material->effective_file_url }}" alt="{{ $material->title }}" class="img-fluid rounded mb-2">
                        @elseif ($et === 'pdf' && $material->effective_file_url)
                            <div class="ratio ratio-4x3 mb-2">
                                <iframe src="{{ $material->effective_file_url }}" title="{{ $material->title }}"></iframe>
                            </div>
                            <a href="{{ $material->effective_file_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                <i class="ti ti-download me-1"></i>Buka PDF
                            </a>
                        @else
                            <div class="text-muted small">Materi belum tersedia.</div>
                        @endif
                    </article>
                @empty
                    <div class="text-muted small py-2">Belum ada materi pada modul ini.</div>
                @endforelse
            </div>
        </section>
    @empty
        <div class="card border-0 shadow-sm shop-order-card">
            <div class="card-body shop-empty-state text-center text-muted py-5">
                <i class="ti ti-folder-off d-block fs-1 mb-2 opacity-50"></i>
                Course ini belum memiliki modul.
            </div>
        </div>
    @endforelse

    @if ($course->modules->isNotEmpty() && ! $hasMaterials)
        <div class="alert alert-info mb-0">Course ini belum memiliki materi.</div>
    @endif
@endsection
