<x-app-layout>
    @section('title', 'Edit Course | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Training Academy', 'url' => route('training.courses.index')],
            ['label' => 'Edit Course', 'active' => true],
        ]" />
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif
        <form method="POST" action="{{ route('training.courses.update', $course->id) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="card mb-4"><div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Detail Course</h5>
                <a href="{{ route('training.courses.content', $course->id) }}" class="btn btn-sm btn-outline-primary">Kelola Isi</a>
            </div>
                <div class="card-body">@include('admin.training.courses._form', ['course' => $course])</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan</button>
            <a href="{{ route('training.courses.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>
</x-app-layout>
