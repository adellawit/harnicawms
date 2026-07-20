<x-app-layout>
    @section('title', 'Pengaturan Academy | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Training Academy', 'url' => route('training.courses.index')],
            ['label' => 'Pengaturan', 'active' => true],
        ]" />

        @if (session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Pengaturan Academy</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('training.settings.update') }}">
                    @csrf
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="show_progress_percentage" value="0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="show_progress_percentage"
                            name="show_progress_percentage"
                            value="1"
                            @checked(old('show_progress_percentage', $setting->show_progress_percentage))
                        >
                        <label class="form-check-label" for="show_progress_percentage">
                            Tampilkan persentase pembelajaran ke Agent
                        </label>
                    </div>
                    <p class="text-muted small mb-4">
                        Saat dimatikan, progress bar dan info persentase pembelajaran (termasuk jumlah materi
                        selesai dan estimasi waktu tersisa) tidak akan tampil sama sekali ke Agent di halaman
                        Academy — bukan disembunyikan sebagian.
                    </p>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
