<x-app-layout>
    @section('title', 'Edit Method Payment | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Point of Sales'],
                ['label' => 'Method Payment', 'url' => route('pos.method-payment.index')],
                ['label' => 'Edit', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <h5 class="card-header fw-bold">Edit Method Payment</h5>
            <form method="POST" action="{{ route('pos.method-payment.edit.data') }}" id="postForm">
                @csrf
                <input type="hidden" name="id" value="{{ $methodPayment->id }}" />
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Branch</label>
                            <input type="text" class="form-control" value="{{ $methodPayment->branch->name ?? '-' }} ({{ $methodPayment->branch->code ?? '' }})" disabled />
                        </div>
                        @php
                            $settlementType = old('settlement_type', $methodPayment->isPgChannel() ? 'pg_channel' : ($methodPayment->uses_payment_gateway ? 'pg_group' : 'manual'));
                        @endphp
                        @include('admin.pos.method-payment._form-pg', [
                            'settlementType' => $settlementType,
                            'paymentGroupCode' => old('payment_group_code', $methodPayment->payment_group_code),
                            'gatewayChannelCode' => old('gateway_channel_code', $methodPayment->gateway_channel_code),
                        ])

                        <div class="col-md-6">
                            <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
                            <input type="text" id="code" name="code" class="form-control" placeholder="e.g. CASH, TRANSFER, PG_BCA" value="{{ old('code', $methodPayment->code) }}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Cash, Bank Transfer, E-Wallet" value="{{ old('name', $methodPayment->name) }}" required />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="2" placeholder="Brief description of this payment method">{{ old('description', $methodPayment->description) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sort_order">Sort Order</label>
                            <input type="number" id="sort_order" name="sort_order" class="form-control" placeholder="Display order (0, 1, 2...)" min="0" value="{{ old('sort_order', $methodPayment->sort_order ?? 0) }}" />
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $methodPayment->is_active) ? 'checked' : '' }} />
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('pos.method-payment.index') }}" class="btn btn-outline-dark me-2">Cancel</a>
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
                $('#postForm').on('submit', function() {
                    $(this).block({ message: '<div class="spinner-border text-primary"></div>', timeout: 1000, css: { backgroundColor: "transparent", border: 0 }, overlayCSS: { backgroundColor: "#fff", opacity: .8 } });
                });
            });
        </script>
    @endpush
</x-app-layout>
