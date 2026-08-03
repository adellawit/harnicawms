<x-app-layout>
    @section('title', 'Add Fiscal Calendar | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Fiscal Calendar', 'url' => route('finance.fiscal-calendar.index.view', ['company_id' => $defaultCompanyId])],
            ['label' => 'Add', 'active' => true],
        ]" />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        @if ($openCalendar)
            <x-alert type="warning" class="mb-3">
                Fiscal calendar <strong>{{ $openCalendar->name }}</strong> (FY {{ $openCalendar->fiscal_year }}) is still open for this company.
                Close it before creating a new fiscal calendar.
                <a href="{{ route('finance.fiscal-calendar.show.view', $openCalendar->id) }}" class="alert-link ms-1">View calendar</a>
            </x-alert>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Add Fiscal Calendar</h5>
            </div>
            <form method="POST" action="{{ route('finance.fiscal-calendar.insert.data') }}" id="postForm">
                @csrf
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="company_id">Company <span class="text-danger">*</span></label>
                            <select name="company_id" id="company_id" class="form-select select2" required>
                                @foreach($companies as $c)
                                    <option value="{{ $c->id }}" @selected(old('company_id', $defaultCompanyId) == $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="fiscal_year">Fiscal Year <span class="text-danger">*</span></label>
                            <input type="number" name="fiscal_year" id="fiscal_year" class="form-control" min="2000" max="2100" required
                                value="{{ old('fiscal_year', $defaultYear) }}">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                                    @checked(old('is_active', true))>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" maxlength="255" required
                                placeholder="e.g. FY {{ $defaultYear }}"
                                value="{{ old('name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="start_date">Start Date <span class="text-danger">*</span></label>
                            <input type="text" name="start_date" id="start_date" class="form-control flatpickr-date"
                                placeholder="DD/MM/YYYY" required
                                value="{{ old('start_date', $defaultStart) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="end_date">End Date <span class="text-danger">*</span></label>
                            <input type="text" name="end_date" id="end_date" class="form-control flatpickr-date"
                                placeholder="DD/MM/YYYY" required
                                value="{{ old('end_date', $defaultEnd) }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="notes">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input type="hidden" name="include_adjustment_period" value="0">
                                <input class="form-check-input" type="checkbox" name="include_adjustment_period" id="include_adjustment_period" value="1"
                                    @checked(old('include_adjustment_period'))>
                                <label class="form-check-label" for="include_adjustment_period">
                                    Include adjustment period (Period 13)
                                </label>
                            </div>
                            <div class="form-text">Monthly periods (1–12) are generated automatically from the date range.</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="{{ route('finance.fiscal-calendar.index.view', ['company_id' => $defaultCompanyId]) }}" class="btn btn-label-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" @disabled($openCalendar)>Create Calendar</button>
                </div>
            </form>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(document).ready(function() {
                $('.flatpickr-date').flatpickr({
                    dateFormat: 'd/m/Y',
                    disableMobile: true,
                    allowInput: true
                });
                $('#fiscal_year').on('change', function() {
                    var y = parseInt($(this).val(), 10);
                    if (!y) return;
                    $('#start_date')[0]._flatpickr.setDate(new Date(y, 0, 1));
                    $('#end_date')[0]._flatpickr.setDate(new Date(y, 11, 31));
                    $('#name').attr('placeholder', 'e.g. FY ' + y);
                });
                $('#company_id').on('change', function() {
                    var url = new URL(@json(route('finance.fiscal-calendar.insert.view')), window.location.origin);
                    url.searchParams.set('company_id', $(this).val());
                    var y = $('#fiscal_year').val();
                    if (y) url.searchParams.set('year', y);
                    window.location.href = url.toString();
                });
            });
        </script>
    @endpush
</x-app-layout>
