<x-app-layout>
    @section('title', 'Edit Cash Flow Category | ')
    @push('vendor-css')
        @include('admin.finance.reports._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report" style="padding-bottom: 70px !important;">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Cash Flow Category', 'url' => route('finance.cash-flow-category.index.view')],
            ['label' => 'Edit', 'active' => true],
        ]" />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card fin-toolbar mb-3">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fin-kpi-icon bg-label-warning text-warning"><i class="ti ti-pencil"></i></div>
                <div>
                    <div class="text-muted small mb-0">Edit kategori arus kas</div>
                    <div class="fin-company">
                        <span class="fin-account-code">{{ $category->code }}</span>{{ $category->name }}
                    </div>
                </div>
            </div>
        </div>

        <div class="card fin-section accent-warning">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Category details</h5>
                </div>
            </div>
            <form method="POST" action="{{ route('finance.cash-flow-category.edit.data') }}">
                @csrf
                <div class="card-body">
                    @include('admin.finance.cash-flow-category._form')
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="{{ route('finance.cash-flow-category.index.view') }}" class="btn btn-label-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
