<x-app-layout>
    @section('title', 'Beginning Balance | ')

    @push('vendor-css')
        @include('admin.finance.reports._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report" style="padding-bottom: 90px !important;">
        @php
            $hasUpdatePermission = session('permissions.Beginning Balance.is_update', false) == 1;
            $lines = $balance->lines->sortBy(fn ($l) => $l->account?->code ?? '');
            $totalDebit = $balance->totalDebit();
            $totalCredit = $balance->totalCredit();
            $diff = round($totalDebit - $totalCredit, 2);
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Beginning Balance', 'url' => route('finance.beginning-balance.index.view', ['company_id' => $calendar->company_id])],
            ['label' => (string) $calendar->fiscal_year, 'active' => true],
        ]" />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card fin-toolbar mb-3">
            <div class="card-body d-flex flex-wrap justify-content-between gap-3 align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="fin-kpi-icon bg-label-success text-success"><i class="ti ti-scale"></i></div>
                    <div>
                        <div class="text-muted small mb-0">{{ $calendar->company?->name }}</div>
                        <div class="fin-company">FY {{ $calendar->fiscal_year }} — {{ $calendar->name }}</div>
                        <div class="d-flex flex-wrap gap-2 align-items-center mt-1">
                            @if($balance->isPosted())
                                <span class="badge bg-label-success">Posted</span>
                                <span class="text-muted small">{{ optional($balance->posted_at)->format('d/m/Y H:i') }}</span>
                                @if($balance->journal)
                                    <a href="{{ route('finance.jurnal-umum.show.view', $balance->journal->id) }}" class="small">
                                        Journal {{ $balance->journal->journal_no }}
                                    </a>
                                @endif
                            @else
                                <span class="badge bg-label-warning">Draft</span>
                            @endif
                            @if($hasJournals)
                                <span class="badge bg-label-danger">Has journals — locked</span>
                            @endif
                        </div>
                    </div>
                </div>
                <a href="{{ route('finance.beginning-balance.index.view', ['company_id' => $calendar->company_id]) }}"
                    class="btn btn-sm btn-label-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="row g-3 mb-3 fin-kpi">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="fin-kpi-label">Total Debit</div>
                        <div class="fin-kpi-value" id="summaryDebit">{{ format_number($totalDebit, 2, true) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="fin-kpi-label">Total Credit</div>
                        <div class="fin-kpi-value" id="summaryCredit">{{ format_number($totalCredit, 2, true) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="fin-kpi-label">Difference</div>
                        <div class="fin-kpi-value {{ abs($diff) < 0.01 ? 'text-success' : 'text-warning' }}" id="summaryDiff">
                            {{ format_number($diff, 2, true) }}
                        </div>
                        <div class="form-text mb-0">Book auto-fills Opening Balance Equity.</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('finance.beginning-balance.save') }}" id="bbForm">
            @csrf
            <input type="hidden" name="id" value="{{ $balance->id }}">

            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="card-title mb-0">Accounts</h5>
                    <span class="text-muted small">{{ $lines->count() }} accounts</span>
                </div>
                <div class="card-datatable">
                    <table class="table table-hover mb-0" id="bbTable">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th class="text-end" style="width: 160px;">Debit</th>
                                <th class="text-end" style="width: 160px;">Credit</th>
                                <th style="width: 110px;">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lines as $idx => $line)
                                <tr>
                                    <td><code>{{ $line->account?->code }}</code></td>
                                    <td>
                                        {{ $line->account?->name }}
                                        @if($line->is_auto_balancing)
                                            <span class="badge bg-label-info ms-1">Auto</span>
                                        @endif
                                    </td>
                                    <td class="text-capitalize">{{ $line->account?->account_type }}</td>
                                    <td>
                                        <input type="hidden" name="lines[{{ $idx }}][account_id]" value="{{ $line->account_id }}">
                                        <input type="text" inputmode="decimal"
                                            name="lines[{{ $idx }}][debit]"
                                            class="form-control form-control-sm text-end bb-amount bb-debit number-format"
                                            value="{{ old('lines.'.$idx.'.debit', format_number((float) $line->debit, 2, true)) }}"
                                            placeholder="0"
                                            @disabled(! $canEdit || ! $hasUpdatePermission)
                                            @readonly(! $canEdit || ! $hasUpdatePermission)>
                                    </td>
                                    <td>
                                        <input type="text" inputmode="decimal"
                                            name="lines[{{ $idx }}][credit]"
                                            class="form-control form-control-sm text-end bb-amount bb-credit number-format"
                                            value="{{ old('lines.'.$idx.'.credit', format_number((float) $line->credit, 2, true)) }}"
                                            placeholder="0"
                                            @disabled(! $canEdit || ! $hasUpdatePermission)
                                            @readonly(! $canEdit || ! $hasUpdatePermission)>
                                    </td>
                                    <td class="small text-muted">{{ str_replace('_', ' ', $line->source) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No eligible balance-sheet accounts. Create COA detail accounts first.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2" maxlength="1000"
                        @disabled(! $canEdit || ! $hasUpdatePermission)>{{ old('notes', $balance->notes) }}</textarea>
                </div>
            </div>
        </form>

        @if($hasUpdatePermission)
            <div class="card mt-3">
                <div class="card-body d-flex flex-wrap gap-2 justify-content-end">
                    @if($canEdit)
                        <form method="POST" action="{{ route('finance.beginning-balance.suggest') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="id" value="{{ $balance->id }}">
                            <button type="submit"
                                class="btn btn-sm btn-outline-info d-inline-flex align-items-center justify-content-center"
                                style="height: 2.25rem; min-width: 7.5rem;"
                                onclick="return confirm('Replace amounts with suggested values from previous posted FY?')">
                                <i class="ti ti-sparkles me-1"></i> Suggest
                            </button>
                        </form>
                        <button type="submit" form="bbForm"
                            class="btn btn-sm btn-outline-primary d-inline-flex align-items-center justify-content-center"
                            style="height: 2.25rem; min-width: 7.5rem;">
                            <i class="ti ti-device-floppy me-1"></i> Save Draft
                        </button>
                        <button type="button" id="btnBook"
                            class="btn btn-sm btn-primary d-inline-flex align-items-center justify-content-center"
                            style="height: 2.25rem; min-width: 7.5rem;">
                            <i class="ti ti-check me-1"></i> Book
                        </button>
                    @elseif($balance->isPosted() && ! $hasJournals)
                        <form method="POST" action="{{ route('finance.beginning-balance.unpost') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="id" value="{{ $balance->id }}">
                            <button type="submit"
                                class="btn btn-sm btn-outline-warning d-inline-flex align-items-center justify-content-center"
                                style="height: 2.25rem; min-width: 7.5rem;"
                                onclick="return confirm('Unpost beginning balance so it can be edited again?')">
                                <i class="ti ti-lock-open me-1"></i> Unpost
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(document).ready(function() {
                function parseNum(val) {
                    return parseFloat(String(val || 0).replace(/\./g, '').replace(',', '.')) || 0;
                }

                function formatId(val) {
                    return parseFloat(val || 0).toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }

                function getAmount($el) {
                    var cleave = $el.data('cleave');
                    if (cleave) {
                        return parseFloat(cleave.getRawValue()) || 0;
                    }
                    return parseNum($el.val());
                }

                function recalc() {
                    var d = 0, c = 0;
                    $('.bb-debit').each(function() { d += getAmount($(this)); });
                    $('.bb-credit').each(function() { c += getAmount($(this)); });
                    var diff = Math.round((d - c) * 100) / 100;
                    $('#summaryDebit').text(formatId(d));
                    $('#summaryCredit').text(formatId(c));
                    $('#summaryDiff').text(formatId(diff));
                    $('#summaryDiff').toggleClass('text-success', Math.abs(diff) < 0.01).toggleClass('text-warning', Math.abs(diff) >= 0.01);
                }

                @if($canEdit && $hasUpdatePermission)
                $('.number-format').each(function() {
                    if ($(this).data('cleave')) return;
                    var raw = parseNum($(this).val());
                    var cleave = new Cleave(this, {
                        numeral: true,
                        numeralThousandsGroupStyle: 'thousand',
                        numeralDecimalMark: ',',
                        delimiter: '.',
                        numeralDecimalScale: 2,
                        onValueChanged: function() { recalc(); }
                    });
                    cleave.setRawValue(String(raw));
                    $(this).data('cleave', cleave);
                });

                function syncRawBeforeSubmit() {
                    $('.number-format').each(function() {
                        var cleave = $(this).data('cleave');
                        if (cleave) {
                            $(this).val(cleave.getRawValue() || '0');
                        } else {
                            $(this).val(String(parseNum($(this).val())));
                        }
                    });
                }

                $('#bbForm').on('submit', function() {
                    syncRawBeforeSubmit();
                });

                $('#btnBook').on('click', function() {
                    if (!confirm('Book beginning balance? Difference will auto-post to Opening Balance Equity.')) return;
                    syncRawBeforeSubmit();
                    $('#bbForm').attr('action', @json(route('finance.beginning-balance.book')));
                    $('#bbForm').submit();
                });
                @endif

                recalc();
            });
        </script>
    @endpush
</x-app-layout>
