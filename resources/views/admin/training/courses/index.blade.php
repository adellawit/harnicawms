<x-app-layout>
    @section('title', 'Training Academy | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Training Academy', 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Courses</h5>
                <div>
                    <a href="{{ route('training.categories.index') }}" class="btn btn-outline-secondary btn-sm"><i class="ti ti-tags me-1"></i>Kategori</a>
                    <a href="{{ route('training.reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="ti ti-chart-bar me-1"></i>Laporan</a>
                    <a href="{{ route('training.courses.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus me-1"></i>Buat Course</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Judul</th><th>Kategori</th><th>Modul</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($courses as $course)
                            <tr>
                                <td>{{ $course->title }}</td>
                                <td>{{ $course->category?->name ?? '-' }}</td>
                                <td>{{ $course->modules_count }}</td>
                                <td><span class="badge bg-label-{{ $course->status === 'published' ? 'success' : 'secondary' }}">{{ ucfirst($course->status) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('training.courses.content', $course->id) }}" class="btn btn-sm btn-outline-primary">Isi</a>
                                    <a href="{{ route('training.courses.edit', $course->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <form action="{{ route('training.courses.publish', $course->id) }}" method="POST" class="d-inline">@csrf
                                        <button class="btn btn-sm btn-outline-{{ $course->status === 'published' ? 'warning' : 'success' }}">{{ $course->status === 'published' ? 'Unpublish' : 'Publish' }}</button>
                                    </form>
                                    <form action="{{ route('training.courses.destroy', $course->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus course ini?')">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Belum ada course.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $courses->links() }}</div>
        </div>
    </div>
</x-app-layout>
