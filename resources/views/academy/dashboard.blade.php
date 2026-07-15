<x-app-layout>
    @section('title', 'Training Academy | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Academy', 'active' => true],
        ]" />

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h4 class="fw-bold mb-1">Training Academy</h4><p class="text-muted mb-0">Tingkatkan kemampuan jualan Anda</p></div>
        </div>

        {{-- Header stats --}}
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100"><div class="card-body d-flex justify-content-between align-items-center">
                    <div><small class="text-muted">Modul Selesai</small>
                        <h3 class="mb-2">{{ $stats['modules_completed'] }} / {{ $stats['modules_total'] }}</h3>
                        <div class="progress" style="height:6px"><div class="progress-bar" role="progressbar"
                            style="width: {{ $stats['modules_total'] ? round($stats['modules_completed'] / $stats['modules_total'] * 100) : 0 }}%"></div></div>
                    </div>
                    <span class="badge bg-label-success rounded p-2"><i class="ti ti-school ti-md"></i></span>
                </div></div>
            </div>
            <div class="col-md-6">
                <div class="card h-100"><div class="card-body d-flex justify-content-between align-items-center">
                    <div><small class="text-muted">Jam Belajar</small>
                        <h3 class="mb-0">@if($stats['has_minutes']){{ number_format($stats['minutes_done'] / 60, 1) }} jam @else — @endif</h3></div>
                    <span class="badge bg-label-primary rounded p-2"><i class="ti ti-clock ti-md"></i></span>
                </div></div>
            </div>
        </div>

        {{-- Sedang Dipelajari --}}
        @if ($continue)
            @php($cp = $continue['progress'])
            <div class="card mb-4"><div class="card-body">
                <h6 class="mb-3"><i class="ti ti-player-play me-1"></i>Sedang Dipelajari</h6>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="flex-grow-1">
                        <strong>{{ $continue['course']->title }}</strong>
                        <div class="text-muted small mb-2">{{ $continue['course']->category?->name }}</div>
                        <div class="progress" style="height:8px; max-width:600px"><div class="progress-bar bg-success" style="width: {{ $cp['percent'] }}%"></div></div>
                        <small class="text-muted">{{ $cp['percent'] }}% selesai @if($cp['has_minutes']) · {{ $cp['minutes_remaining'] }} menit tersisa @endif</small>
                    </div>
                    <a href="{{ $continue['access']->last_material_id
                        ? route('academy.materials.show', $continue['access']->last_material_id)
                        : route('academy.courses.show', $continue['course']->id) }}" class="btn btn-success"><i class="ti ti-player-play me-1"></i>Lanjutkan</a>
                </div>
            </div></div>
        @endif

        {{-- Semua Kursus --}}
        <h5 class="mb-3">Semua Kursus</h5>
        <div class="row g-4">
            @forelse ($courses as $course)
                @php($p = $progressByCourse[$course->id])
                <div class="col-md-4 col-lg-3">
                    <div class="card h-100">
                        <div class="rounded-top" style="height:120px; background: {{ $course->category?->color ?: '#5C9E84' }}; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                            @if ($course->thumbnail_url)
                                <img src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}" style="width:100%; height:100%; object-fit:cover;">
                            @else
                                <i class="ti {{ $course->category?->icon ?: 'ti-book' }}" style="font-size:2.5rem; color:#fff;"></i>
                            @endif
                        </div>
                        <div class="card-body">
                            @if ($course->category)<span class="badge bg-label-secondary mb-2"><i class="ti ti-tag me-1"></i>{{ $course->category->name }}</span>@endif
                            <h6 class="mb-1">{{ $course->title }}</h6>
                            <small class="text-muted d-block mb-2">{{ $p['modules_total'] }} modul</small>
                            <div class="progress mb-2" style="height:6px"><div class="progress-bar bg-success" style="width: {{ $p['percent'] }}%"></div></div>
                            <small class="text-muted">{{ $p['percent'] }}% selesai</small>
                        </div>
                        <div class="card-footer">
                            <a href="{{ route('academy.courses.show', $course->id) }}" class="btn btn-sm w-100 btn-{{ $p['percent'] === 0 ? 'primary' : ($p['percent'] >= 100 ? 'outline-secondary' : 'success') }}">
                                {{ $p['percent'] === 0 ? 'Mulai Kursus' : ($p['percent'] >= 100 ? 'Ulangi' : 'Lanjutkan') }}
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="alert alert-info">Belum ada kursus yang tersedia.</div></div>
            @endforelse
        </div>
    </div>
</x-app-layout>
