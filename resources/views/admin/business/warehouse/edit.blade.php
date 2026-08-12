<x-app-layout>
    @section('title', 'Edit Warehouse | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Business', 'url' => route('warehouse.index.view')],
            ['label' => 'Edit Warehouse', 'active' => true],
        ]" />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Edit Warehouse: {{ $warehouse->name }}</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('warehouse.edit.data') }}">
                    @csrf
                    <input type="hidden" name="id" value="{{ $warehouse->id }}">
                    @include('admin.business.warehouse._form', ['warehouse' => $warehouse])
                    <div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                        <a href="{{ route('warehouse.index.view') }}" class="btn btn-label-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('admin.business.warehouse._form-scripts', ['selectedProvinceId' => $selectedProvinceId ?? null])
    @include('admin.master-data.shipping-rate._city-select-js')
</x-app-layout>
