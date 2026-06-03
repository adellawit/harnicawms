<x-app-layout>
    @section('title', 'Edit Price List | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Price Lists', 'url' => route('product.price-list.index.view')],
                ['label' => 'Edit', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <h5 class="card-header fw-bold">Edit Price List</h5>
            <form method="POST" action="{{ route('product.price-list.edit.data') }}" id="postForm">
                @csrf
                <input type="hidden" name="id" value="{{ $priceList->id }}" />
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
                            <input type="text" id="code" name="code" class="form-control" placeholder="e.g., NORMAL" value="{{ old('code', $priceList->code) }}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="e.g., Harga Normal" value="{{ old('name', $priceList->name) }}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="channel_type">Channel Type</label>
                            <select id="channel_type" name="channel_type" class="select2 form-select">
                                <option value="">-- None --</option>
                                <option value="pos" {{ old('channel_type', $priceList->channel_type) == 'pos' ? 'selected' : '' }}>POS / Kasir</option>
                                <option value="marketplace" {{ old('channel_type', $priceList->channel_type) == 'marketplace' ? 'selected' : '' }}>Marketplace</option>
                                <option value="delivery" {{ old('channel_type', $priceList->channel_type) == 'delivery' ? 'selected' : '' }}>Delivery / Ojol</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="external_channel_code">External Channel Code</label>
                            <input type="text" id="external_channel_code" name="external_channel_code" class="form-control" placeholder="e.g., gofood, grabfood" value="{{ old('external_channel_code', $priceList->external_channel_code) }}" />
                            <small class="text-muted">Optional: gofood, grabfood, shopeefood, tokopedia, shopee, lazada</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sort_order">Sort Order</label>
                            <input type="number" id="sort_order" name="sort_order" class="form-control" value="{{ old('sort_order', $priceList->sort_order) }}" min="0" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="2" placeholder="Optional description">{{ old('description', $priceList->description) }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @if(old('is_active', $priceList->is_active)) checked @endif>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('product.price-list.index.view') }}" class="btn btn-outline-dark me-2">Cancel</a>
        <button type="button" class="btn btn-primary" id="btn-submit">Save</button>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/block-ui/block-ui.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(function() {
                $('#btn-submit').click(function() { $('#postForm').submit(); });
                $('#postForm').submit(function() {
                    $(this).block({ message: '<div class="spinner-border text-primary"></div>', timeout: 1000, css: { backgroundColor: "transparent", border: 0 }, overlayCSS: { backgroundColor: "#fff", opacity: .8 } });
                });
            });
        </script>
    @endpush
</x-app-layout>
