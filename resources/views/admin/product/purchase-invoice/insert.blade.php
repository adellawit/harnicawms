<x-app-layout>
    @section('title', 'Create Invoice | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => 'javascript:void(0);'],
                ['label' => 'Invoice', 'url' => route('product.purchase-invoice.index.view')],
                ['label' => 'Create Kontrabon', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        @include('admin.product.purchase-invoice._form')
    </div>
</x-app-layout>
