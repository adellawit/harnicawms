<x-app-layout>

    @section('title', 'Edit Membership Configuration | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Settings', 'url' => 'javascript:void(0);'],
                ['label' => 'Membership Configuration', 'url' => route('crm.membership-configuration.index.view')],
                ['label' => 'Edit', 'active' => true]
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card accordion mt-3">
            <h5 class="card-header fw-bold" style="color: #212529">Edit Membership Configuration</h5>
            <form method="POST" action="{{ route('crm.membership-configuration.edit.data') }}" id="postForm">
                @csrf
                <hr style="margin-bottom: 0.5rem; margin-top: 0;" />
                <div class="accordion-body">
                    <div class="row g-3">
                        <input type="hidden" name="id" value="{{ $configuration->id }}">
                        <div class="col-md-12">
                            <label class="form-label" for="branch_id">Branch<span style="color: red">*</span></label>
                            <select id="branch_id" name="branch_id" class="select2 form-select form-select-lg" data-allow-clear="true">
                                <option value="">Select Branch</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_id', $configuration->branch_id) === $branch->id)>
                                        {{ $branch->code }} - {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="name">Name<span style="color: red">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $configuration->name) }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="transaction_amount_step">Transaction Amount Step<span style="color: red">*</span></label>
                            <input type="number" min="1" id="transaction_amount_step" name="transaction_amount_step" class="form-control" value="{{ old('transaction_amount_step', $configuration->transaction_amount_step) }}" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="points_per_step">Points per Step<span style="color: red">*</span></label>
                            <input type="number" min="1" id="points_per_step" name="points_per_step" class="form-control" value="{{ old('points_per_step', $configuration->points_per_step) }}" />
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $configuration->description) }}</textarea>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="is_default" name="is_default" {{ old('is_default', $configuration->is_default) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_default">
                                    Set as default configuration
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <div>
            <a href="{{ route('crm.membership-configuration.index.view') }}" class="btn btn-outline-dark me-2">
                <span class="ti-xs ti ti-x me-1"></span>Cancel
            </a>
            <button type="submit" class="btn btn-primary me-2" id="btn-submit">
                <span class="ti-xs ti ti-device-floppy me-1"></span>Save
            </button>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/block-ui/block-ui.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush

    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(document).ready(function() {
                $('#btn-submit').on('click', function() {
                    $('#postForm').submit();
                });

                $('#postForm').submit(function() {
                    $("#postForm").block({
                        message: '<div class="spinner-border text-primary" role="status"></div>',
                        timeout: 1e3,
                        css: { backgroundColor: "transparent", border: "0" },
                        overlayCSS: { backgroundColor: "#fff", opacity: .8 }
                    });
                });
            });
        </script>
    @endpush

</x-app-layout>
