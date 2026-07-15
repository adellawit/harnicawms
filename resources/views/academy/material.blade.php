<x-app-layout>
    @section('title', $material->title . ' | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Academy', 'url' => route('academy.dashboard')],
            ['label' => $course->title, 'url' => route('academy.courses.show', $course->id)],
            ['label' => $material->title, 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ $material->title }}</h5>
                @if ($isCompleted)
                    <span class="badge bg-label-success"><i class="ti ti-check me-1"></i>Selesai</span>
                @endif
            </div>
            <div class="card-body">
                @php($et = $material->effective_type)
                @if ($et === 'video')
                    @if ($material->effective_video_embed_id)
                        <div class="ratio ratio-16x9">
                            <iframe src="https://www.youtube.com/embed/{{ $material->effective_video_embed_id }}" title="{{ $material->title }}" allowfullscreen></iframe>
                        </div>
                    @elseif ($material->effective_video_url)
                        <a href="{{ $material->effective_video_url }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="ti ti-external-link me-1"></i>Buka video</a>
                    @else
                        <div class="alert alert-warning mb-0">Materi tidak dapat ditampilkan.</div>
                    @endif
                @elseif ($et === 'image' && $material->effective_file_url)
                    <img src="{{ $material->effective_file_url }}" alt="{{ $material->title }}" class="img-fluid rounded">
                @elseif ($et === 'pdf' && $material->effective_file_url)
                    <div class="ratio" style="--bs-aspect-ratio: 130%">
                        <iframe src="{{ $material->effective_file_url }}" title="{{ $material->title }}"></iframe>
                    </div>
                    <a href="{{ $material->effective_file_url }}" target="_blank" class="btn btn-sm btn-outline-secondary mt-2"><i class="ti ti-download me-1"></i>Buka PDF</a>
                @else
                    <div class="alert alert-warning mb-0">Materi tidak dapat ditampilkan.</div>
                @endif
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <div>
                @if ($prev)<a href="{{ route('academy.materials.show', $prev->id) }}" class="btn btn-outline-secondary"><i class="ti ti-chevron-left me-1"></i>Sebelumnya</a>@endif
            </div>
            <div class="d-flex gap-2">
                @unless ($isCompleted)
                    <form method="POST" action="{{ route('academy.materials.complete', $material->id) }}">@csrf
                        <button class="btn btn-success"><i class="ti ti-check me-1"></i>Tandai selesai</button>
                    </form>
                @endunless
                @if ($next)<a href="{{ route('academy.materials.show', $next->id) }}" class="btn btn-primary">Berikutnya<i class="ti ti-chevron-right ms-1"></i></a>
                @else<a href="{{ route('academy.courses.show', $course->id) }}" class="btn btn-outline-primary">Kembali ke Course</a>@endif
            </div>
        </div>
    </div>
</x-app-layout>
