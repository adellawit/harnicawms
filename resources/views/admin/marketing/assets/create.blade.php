<x-app-layout>
    @section('title', 'Tambah Aset | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Marketing Center', 'url' => route('marketing.assets.index')],
            ['label' => 'Tambah Aset', 'active' => true],
        ]" />
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif
        <form method="POST" action="{{ route('marketing.assets.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="card mb-4"><div class="card-header"><h5 class="card-title mb-0">Detail Aset</h5></div>
                <div class="card-body">@include('admin.marketing.assets._form', ['asset' => null])</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan</button>
            <a href="{{ route('marketing.assets.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>
</x-app-layout>
