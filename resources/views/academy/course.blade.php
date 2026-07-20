<x-app-layout>
    @section('title', $course->title . ' | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Training Academy', 'url' => route('academy.dashboard')],
            ['label' => $course->title, 'active' => true],
        ]" />

        <div class="card mb-4"><div class="card-body">
            @if ($course->category)<span class="badge bg-label-secondary mb-2">{{ $course->category->name }}</span>@endif
            <h4 class="mb-1">{{ $course->title }}</h4>
            @if ($course->description)<p class="text-muted">{{ $course->description }}</p>@endif
            @if($showProgress)
            <div class="progress mb-2" style="height:8px; max-width:600px"><div class="progress-bar bg-success" style="width: {{ $progress['percent'] }}%"></div></div>
            <small class="text-muted">{{ $progress['completed_count'] }}/{{ $progress['total_materials'] }} materi · {{ $progress['percent'] }}% selesai
                @if($progress['has_minutes']) · {{ $progress['minutes_remaining'] }} menit tersisa @endif</small>
            @endif
        </div></div>

        @forelse ($course->modules as $module)
            <div class="card mb-3">
                <div class="card-header"><strong>{{ $module->title }}</strong>
                    @if($module->description)<div class="text-muted small">{{ $module->description }}</div>@endif</div>
                <div class="list-group list-group-flush">
                    @forelse ($module->materials as $mat)
                        @php($done = isset($completedIds[$mat->id]))
                        <a href="{{ route('academy.materials.show', $mat->id) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span>
                                <i class="ti {{ $mat->effective_type === 'video' ? 'ti-brand-youtube' : ($mat->effective_type === 'pdf' ? 'ti-file-type-pdf' : 'ti-photo') }} me-2"></i>
                                {{ $mat->title }}
                                @if($mat->estimated_minutes)<span class="text-muted small ms-2">{{ $mat->estimated_minutes }} mnt</span>@endif
                            </span>
                            @if ($done)<span class="badge bg-label-success"><i class="ti ti-check"></i> Selesai</span>
                            @else<i class="ti ti-chevron-right text-muted"></i>@endif
                        </a>
                    @empty
                        <div class="list-group-item text-muted">Belum ada materi.</div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="alert alert-info">Course ini belum memiliki modul.</div>
        @endforelse
    </div>
</x-app-layout>
