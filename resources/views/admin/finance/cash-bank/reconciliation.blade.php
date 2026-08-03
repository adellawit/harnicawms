<x-app-layout>
    @section('title', 'Bank Reconciliation | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/cleavejs/cleave.css') }}">
        @include('admin.finance.cash-bank._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report" style="padding-bottom: 100px !important;">
        @php
            $diffAbs = abs((float) $recon->difference);
            $isBalanced = $diffAbs < 0.01;
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Cash & Bank', 'url' => route('finance.cash-bank.index.view', ['company_id' => $recon->company_id])],
            ['label' => 'Reconciliation', 'active' => true],
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
            <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="fin-kpi-icon bg-label-success text-success">
                        <i class="ti ti-checklist"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-0">{{ $recon->company?->name }}</div>
                        <div class="fin-company">
                            <span class="fin-account-code">{{ $recon->account?->code }}</span>{{ $recon->account?->name }}
                        </div>
                        <div class="small text-muted">As of {{ format_date_id($recon->reconciliation_date) }}</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($recon->isCompleted())
                        <span class="fin-status-pill bg-label-success text-success"><i class="ti ti-circle-check"></i> Completed</span>
                    @else
                        <span class="fin-status-pill bg-label-warning text-warning"><i class="ti ti-pencil"></i> Draft</span>
                    @endif
                    <a href="{{ route('finance.cash-bank.index.view', ['company_id' => $recon->company_id]) }}"
                        class="btn btn-sm btn-label-secondary">
                        <i class="ti ti-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('finance.cash-bank.reconciliation.save') }}" id="reconForm">
            @csrf
            <input type="hidden" name="id" value="{{ $recon->id }}">
            <input type="hidden" name="action" id="formAction" value="save">

            <div class="row g-3 mb-3">
                <div class="col-md-6 col-xl-3">
                    <div class="cb-recon-metric">
                        <div class="fin-kpi-label">Statement balance</div>
                        @if($canEdit)
                            <input type="text" name="statement_balance" id="statement_balance"
                                class="form-control text-end number-format mt-1"
                                value="{{ format_number((float) $recon->statement_balance, 2, true) }}">
                        @else
                            <div class="fin-kpi-value mt-1">{{ format_number((float) $recon->statement_balance, 2, true) }}</div>
                        @endif
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="cb-recon-metric">
                        <div class="fin-kpi-label">Book balance</div>
                        <div class="fin-kpi-value mt-1" id="bookBalance">{{ format_number((float) $recon->book_balance, 2, true) }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="cb-recon-metric">
                        <div class="fin-kpi-label">Cleared amount</div>
                        <div class="fin-kpi-value mt-1 text-info" id="clearedBalance">{{ format_number((float) $recon->cleared_balance, 2, true) }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="cb-recon-metric {{ $isBalanced ? 'is-ok' : 'is-warn' }}">
                        <div class="fin-kpi-label">Difference</div>
                        <div class="fin-kpi-value mt-1 {{ $isBalanced ? 'text-success' : 'text-warning' }}" id="difference">
                            {{ format_number((float) $recon->difference, 2, true) }}
                        </div>
                        <div class="form-text mb-0">Must be 0 to complete.</div>
                    </div>
                </div>
            </div>

            <div class="card fin-section accent-secondary mb-3">
                <div class="fin-section-head">
                    <div>
                        <h5 class="fin-section-title">Notes</h5>
                        <div class="fin-section-sub">Optional reconciliation remarks</div>
                    </div>
                </div>
                <div class="card-body">
                    <textarea name="notes" id="notes" class="form-control" rows="2" maxlength="1000"
                        @disabled(! $canEdit)>{{ old('notes', $recon->notes) }}</textarea>
                </div>
            </div>

            <div class="card fin-section accent-success mb-3">
                <div class="fin-section-head">
                    <div>
                        <h5 class="fin-section-title">Transactions</h5>
                        <div class="fin-section-sub">Clear lines that appear on the bank statement</div>
                    </div>
                    @if($canEdit)
                        <div class="form-check m-0">
                            <input class="form-check-input" type="checkbox" id="checkAll">
                            <label class="form-check-label" for="checkAll">Clear all</label>
                        </div>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table fin-table mb-0">
                        <thead>
                            <tr>
                                @if($canEdit)<th style="width: 40px;"></th>@endif
                                <th style="width: 10%">Date</th>
                                <th style="width: 16%">Journal</th>
                                <th>Description</th>
                                <th class="text-end" style="width: 12%">Debit</th>
                                <th class="text-end pe-3" style="width: 12%">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lines as $line)
                                @php $jl = $line->journalLine; @endphp
                                <tr class="{{ $line->is_cleared ? 'cb-line-cleared' : '' }}">
                                    @if($canEdit)
                                        <td>
                                            <input type="checkbox" class="form-check-input clear-check"
                                                name="cleared[]" value="{{ $line->journal_line_id }}"
                                                data-debit="{{ (float) ($jl->debit ?? 0) }}"
                                                data-credit="{{ (float) ($jl->credit ?? 0) }}"
                                                @checked($line->is_cleared)>
                                        </td>
                                    @endif
                                    <td>{{ format_date_id($jl?->journal?->journal_date) }}</td>
                                    <td>
                                        @if($jl?->journal_id)
                                            <a href="{{ route('finance.jurnal-umum.show.view', $jl->journal_id) }}" class="text-decoration-none">
                                                <div class="fw-semibold text-primary">{{ $jl?->journal?->journal_no ?: '—' }}</div>
                                                <div class="small text-muted font-monospace">{{ $jl->journal_id }}</div>
                                            </a>
                                        @else
                                            <code>{{ $jl?->journal?->journal_no }}</code>
                                        @endif
                                    </td>
                                    <td>{{ $jl?->description ?: ($jl?->journal?->description ?: '—') }}</td>
                                    <td class="text-end">{{ (float)($jl->debit ?? 0) > 0 ? format_number((float)$jl->debit, 2, true) : '—' }}</td>
                                    <td class="text-end pe-3">{{ (float)($jl->credit ?? 0) > 0 ? format_number((float)$jl->credit, 2, true) : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canEdit ? 6 : 5 }}" class="text-center text-muted py-4">
                                        No uncleared transactions up to this date.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        <div class="card cb-sticky-actions">
            <div class="card-body d-flex flex-wrap gap-2 justify-content-end py-3">
                @if($canEdit)
                    <button type="button" class="btn btn-outline-primary" id="btnSave">
                        <i class="ti ti-device-floppy me-1"></i> Save Draft
                    </button>
                    <button type="button" class="btn btn-primary" id="btnComplete">
                        <i class="ti ti-check me-1"></i> Complete
                    </button>
                @elseif($recon->isCompleted() && session('permissions.Cash & Bank.is_update', false) == 1)
                    <form method="POST" action="{{ route('finance.cash-bank.reconciliation.reopen') }}">
                        @csrf
                        <input type="hidden" name="id" value="{{ $recon->id }}">
                        <button type="submit" class="btn btn-outline-warning"
                            onclick="return confirm('Reopen this reconciliation?')">
                            <i class="ti ti-lock-open me-1"></i> Reopen
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(document).ready(function() {
                var bookBalance = {{ (float) $recon->book_balance }};

                function parseNum(val) {
                    return parseFloat(String(val || 0).replace(/\./g, '').replace(',', '.')) || 0;
                }
                function formatId(val) {
                    return parseFloat(val || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                @if($canEdit)
                var stmtEl = document.getElementById('statement_balance');
                var cleave = new Cleave(stmtEl, {
                    numeral: true,
                    numeralThousandsGroupStyle: 'thousand',
                    numeralDecimalMark: ',',
                    delimiter: '.',
                    numeralDecimalScale: 2,
                    onValueChanged: recalc
                });
                cleave.setRawValue(String(parseNum(stmtEl.value)));

                function recalc() {
                    var cleared = 0;
                    $('.clear-check:checked').each(function() {
                        cleared += (parseFloat($(this).data('debit')) || 0) - (parseFloat($(this).data('credit')) || 0);
                    });
                    var statement = parseFloat(cleave.getRawValue()) || 0;
                    var diff = Math.round((statement - bookBalance) * 100) / 100;
                    var ok = Math.abs(diff) < 0.01;
                    $('#clearedBalance').text(formatId(cleared));
                    $('#difference').text(formatId(diff))
                        .toggleClass('text-success', ok)
                        .toggleClass('text-warning', !ok);
                    $('#difference').closest('.cb-recon-metric')
                        .toggleClass('is-ok', ok)
                        .toggleClass('is-warn', !ok);
                    $('.clear-check').each(function() {
                        $(this).closest('tr').toggleClass('cb-line-cleared', $(this).is(':checked'));
                    });
                }

                function syncRaw() {
                    $('#statement_balance').val(cleave.getRawValue() || '0');
                }

                $(document).on('change', '.clear-check', recalc);
                $('#checkAll').on('change', function() {
                    $('.clear-check').prop('checked', $(this).is(':checked'));
                    recalc();
                });
                $('#btnSave').on('click', function() {
                    $('#formAction').val('save');
                    syncRaw();
                    $('#reconForm').submit();
                });
                $('#btnComplete').on('click', function() {
                    if (!confirm('Complete reconciliation? Difference must be 0.')) return;
                    $('#formAction').val('complete');
                    syncRaw();
                    $('#reconForm').submit();
                });
                recalc();
                @endif
            });
        </script>
    @endpush
</x-app-layout>
