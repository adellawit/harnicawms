<x-app-layout>
    @section('title', 'Tambah Cash Flow Category | ')

    @push('vendor-css')
        @include('admin.finance.reports._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report" style="padding-bottom: 70px !important;">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Cash Flow Category', 'url' => route('finance.cash-flow-category.index.view')],
            ['label' => 'Tambah', 'active' => true],
        ]" />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card fin-toolbar mb-3">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fin-kpi-icon bg-label-info text-info"><i class="ti ti-plus"></i></div>
                <div>
                    <div class="text-muted small mb-0">Tambah kategori arus kas</div>
                    <div class="fin-company">New Cash Flow Category</div>
                </div>
            </div>
        </div>

        <div class="card fin-section accent-info">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Category details</h5>
                </div>
            </div>
            <form method="POST" action="{{ route('finance.cash-flow-category.insert.data') }}">
                @csrf
                <div class="card-body">
                    @include('admin.finance.cash-flow-category._form')
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="{{ route('finance.cash-flow-category.index.view') }}" class="btn btn-label-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
