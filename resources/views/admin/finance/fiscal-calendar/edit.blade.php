<x-app-layout>
    @section('title', 'Edit Fiscal Calendar | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Fiscal Calendar', 'url' => route('finance.fiscal-calendar.index.view', ['company_id' => $calendar->company_id])],
            ['label' => 'Edit', 'active' => true],
        ]" />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Edit Fiscal Calendar</h5>
            </div>
            <form method="POST" action="{{ route('finance.fiscal-calendar.edit.data') }}">
                @csrf
                <input type="hidden" name="id" value="{{ $calendar->id }}">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Company</label>
                            <input type="text" class="form-control" value="{{ $calendar->company?->name }}" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fiscal Year</label>
                            <input type="text" class="form-control" value="{{ $calendar->fiscal_year }}" disabled>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                                    @checked(old('is_active', $calendar->is_active))>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" maxlength="255" required
                                value="{{ old('name', $calendar->name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="text" class="form-control" value="{{ format_date_id($calendar->start_date) }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="text" class="form-control" value="{{ format_date_id($calendar->end_date) }}" disabled>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="notes">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2" maxlength="1000">{{ old('notes', $calendar->notes) }}</textarea>
                            <div class="form-text">Date range cannot be changed after periods are generated.</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="{{ route('finance.fiscal-calendar.show.view', $calendar->id) }}" class="btn btn-label-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
