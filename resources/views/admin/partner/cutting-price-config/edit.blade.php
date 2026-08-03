<x-app-layout>
    @section('title', 'Edit Cutting Price Config | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Network', 'url' => route('partner.reports.index')],
            ['label' => 'Cutting Price Config', 'url' => route('partner.cutting-price-config.index.view')],
            ['label' => 'Edit', 'active' => true],
        ]" />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <h5 class="card-header fw-bold">Edit Cutting Price Config</h5>
            <form method="POST" action="{{ route('partner.cutting-price-config.edit.data') }}" id="postForm">
                @csrf
                <input type="hidden" name="id" value="{{ $config->id }}">
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    @include('admin.partner.cutting-price-config._form', ['config' => $config])
                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('partner.cutting-price-config.index.view') }}" class="btn btn-label-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/js/number-format.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
    @endpush
</x-app-layout>
