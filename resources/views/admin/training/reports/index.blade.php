<x-app-layout>
    @section('title', 'Laporan Training | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Training Academy', 'url' => route('training.courses.index')],
            ['label' => 'Laporan', 'active' => true],
        ]" />

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Progress Belajar Agent</h5>
                <small class="text-muted">{{ $coursesTotal }} course published</small></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Agent</th><th>Kode</th><th class="text-end">Course Selesai</th><th class="text-end">Materi Selesai</th><th>Aktivitas Terakhir</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td><code>{{ $row['code'] }}</code></td>
                                <td class="text-end">{{ $row['courses_completed'] }} / {{ $coursesTotal }}</td>
                                <td class="text-end">{{ $row['materials_completed'] }}</td>
                                <td>{{ $row['last_activity'] ? \Illuminate\Support\Carbon::parse($row['last_activity'])->diffForHumans() : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Belum ada agent.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
