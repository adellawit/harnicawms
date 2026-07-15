<x-app-layout>
    @section('title', 'Edit Promotion | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Promotions', 'url' => route('promotions.index')],
                ['label' => $promo->code, 'url' => route('promotions.show', $promo->id)],
                ['label' => 'Edit', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('promotions.update', $promo->id) }}">
            @csrf
            @method('PUT')
            @include('admin.promotions._form')
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Update</button>
            <a href="{{ route('promotions.show', $promo->id) }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush
    @push('page-js')
        @include('admin.promotions._form-scripts')
    @endpush
</x-app-layout>
