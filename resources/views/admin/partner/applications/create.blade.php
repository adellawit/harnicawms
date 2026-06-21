<x-app-layout>
    @section('title', 'Tambah Pendaftaran Partner | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Partner Network', 'url' => route('partner.reports.index')],
            ['label' => 'Applications', 'url' => route('partner.applications.index')],
            ['label' => 'Tambah', 'active' => true],
        ]" />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <form method="POST" action="{{ route('partner.applications.store') }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-header"><h5 class="card-title mb-0">Data Pendaftaran Partner</h5></div>
            <div class="card-body">
                @include('admin.partner.applications._form')
            </div>
            <div class="card-footer">
                <button class="btn btn-primary">Simpan</button>
                <a href="{{ route('partner.applications.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
