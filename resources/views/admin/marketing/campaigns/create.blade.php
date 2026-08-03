<x-app-layout>
    @section('title', 'Tambah Campaign | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Marketing Center', 'url' => route('marketing.assets.index')],
            ['label' => 'Marketing Campaign', 'url' => route('marketing.campaigns.index')],
            ['label' => 'Tambah', 'active' => true],
        ]" />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('marketing.campaigns.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.marketing.campaigns._form')
            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan</button>
            <a href="{{ route('marketing.campaigns.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush
    @stack('page-js')
</x-app-layout>
