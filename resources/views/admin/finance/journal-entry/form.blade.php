<x-app-layout>
    @section('title', ($mode === 'insert' ? 'Journal Entry' : 'Edit Journal').' | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
        <style>
            #linesTable td {
                vertical-align: middle;
                padding-top: 0.5rem;
                padding-bottom: 0.5rem;
            }
            #linesTable .form-control-sm,
            #linesTable .form-select-sm,
            #linesTable .btn-remove-line {
                height: 31px;
                min-height: 31px;
            }
            #linesTable .btn-remove-line {
                width: 31px;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            #linesTable .select2-container--default .select2-selection--single {
                height: 31px !important;
                min-height: 31px !important;
                border-radius: 0.375rem;
            }
            #linesTable .select2-container--default .select2-selection--single .select2-selection__rendered {
                line-height: 29px !important;
                padding-left: 0.75rem;
                padding-right: 2rem;
                font-size: 0.8125rem;
            }
            #linesTable .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 29px !important;
            }
            #linesTable .select2-container--default .select2-selection--single .select2-selection__clear {
                height: 29px;
                margin-right: 1.5rem;
            }
            #linesTable .select2-container {
                width: 100% !important;
            }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 90px !important;">
        @php
            $hasUpdate = session('permissions.Journal Entry.is_update', false) == 1;
            $hasCreate = session('permissions.Journal Entry.is_create', false) == 1;
            $editable = $canEdit && (($mode === 'insert' && $hasCreate) || ($mode === 'edit' && $hasUpdate));
            $lines = old('lines');
            if (! is_array($lines)) {
                if ($journal && $journal->lines->count()) {
                    $lines = $journal->lines->map(fn ($l) => [
                        'account_id' => $l->account_id,
                        'description' => $l->description,
                        'debit' => format_number((float) $l->debit, 2, true),
                        'credit' => format_number((float) $l->credit, 2, true),
                    ])->values()->all();
                } else {
                    $lines = [
                        ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
                        ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
                    ];
                }
            }
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Journal Entry', 'url' => route('finance.journal-entry.index.view', ['company_id' => $companyId])],
            ['label' => $mode === 'insert' ? 'New Entry' : ($journalNo ?? 'Edit'), 'active' => true],
        ]" />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('finance.journal-entry.save') }}" enctype="multipart/form-data" id="journalForm">
            @csrf
            @if($journal)
                <input type="hidden" name="id" value="{{ $journal->id }}">
            @endif
            <input type="hidden" name="action" id="formAction" value="save">

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ $mode === 'insert' ? 'Journal Entry' : 'Edit Journal' }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="company_id">Company <span class="text-danger">*</span></label>
                            <select name="company_id" id="company_id" class="form-select select2" required @disabled(! $editable || $mode === 'edit')>
                                @foreach($companies as $c)
                                    <option value="{{ $c->id }}" @selected(old('company_id', $companyId) == $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @if($mode === 'edit')
                                <input type="hidden" name="company_id" value="{{ $companyId }}">
                            @endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="journal_no">Journal No</label>
                            <input type="text" id="journal_no" class="form-control bg-light" readonly
                                value="{{ $journal ? $journalNo : 'Auto-generated by system' }}"
                                placeholder="Auto-generated by system">
                            <div class="form-text">Generated automatically on save (e.g. JE-2026-0001).</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="journal_date">Date <span class="text-danger">*</span></label>
                            <input type="text" name="journal_date" id="journal_date" class="form-control flatpickr-date"
                                placeholder="DD/MM/YYYY" required
                                value="{{ old('journal_date', $journalDate) }}"
                                @disabled(! $editable)>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="2" maxlength="2000"
                                @disabled(! $editable)>{{ old('description', $journal?->description) }}</textarea>
                        </div>
                        @if($journal)
                            <div class="col-md-12">
                                @if($journal->isPosted())
                                    <span class="badge bg-label-success">Posted</span>
                                @else
                                    <span class="badge bg-label-warning">Draft</span>
                                @endif
                                @if($journal->fiscalPeriod)
                                    <span class="text-muted small ms-2">Period: {{ $journal->fiscalPeriod->code }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Lines</h5>
                    @if($editable)
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddLine">
                            <i class="ti ti-plus me-1"></i> Add Line
                        </button>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table mb-0" id="linesTable">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 220px;">Account</th>
                                <th style="min-width: 200px;">Description</th>
                                <th class="text-end" style="width: 150px;">Debit</th>
                                <th class="text-end" style="width: 150px;">Credit</th>
                                @if($editable)<th style="width: 50px;"></th>@endif
                            </tr>
                        </thead>
                        <tbody id="linesBody">
                            @foreach($lines as $idx => $line)
                                <tr class="line-row">
                                    <td>
                                        <select name="lines[{{ $idx }}][account_id]" class="form-select form-select-sm line-account select2-account" @disabled(! $editable)>
                                            <option value="">— Select —</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}" @selected(($line['account_id'] ?? '') == $acc->id)>
                                                    {{ $acc->code }} — {{ $acc->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="lines[{{ $idx }}][description]" class="form-control form-control-sm"
                                            value="{{ $line['description'] ?? '' }}" maxlength="500" placeholder="Description per COA"
                                            @disabled(! $editable)>
                                    </td>
                                    <td>
                                        <input type="text" name="lines[{{ $idx }}][debit]" inputmode="decimal"
                                            class="form-control form-control-sm text-end number-format line-debit"
                                            value="{{ $line['debit'] ?? '' }}" placeholder="0" @disabled(! $editable)>
                                    </td>
                                    <td>
                                        <input type="text" name="lines[{{ $idx }}][credit]" inputmode="decimal"
                                            class="form-control form-control-sm text-end number-format line-credit"
                                            value="{{ $line['credit'] ?? '' }}" placeholder="0" @disabled(! $editable)>
                                    </td>
                                    @if($editable)
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-icon btn-remove-line"><i class="ti ti-trash text-danger"></i></button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <th colspan="2" class="text-end">Total</th>
                                <th class="text-end" id="totalDebit">0,00</th>
                                <th class="text-end" id="totalCredit">0,00</th>
                                @if($editable)<th></th>@endif
                            </tr>
                            <tr>
                                <th colspan="2" class="text-end">Difference</th>
                                <th colspan="2" class="text-end" id="totalDiff">0,00</th>
                                @if($editable)<th></th>@endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Attachments</h5>
                </div>
                <div class="card-body">
                    @if($editable)
                        <input type="file" name="attachments[]" class="form-control" multiple
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                        <div class="form-text">PDF, image, or Office files. Max 10MB each.</div>
                    @endif
                    @if($journal && $journal->attachments->count())
                        <ul class="list-group list-group-flush mt-3">
                            @foreach($journal->attachments as $att)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <a href="{{ $att->url() }}" target="_blank" rel="noopener">
                                        <i class="ti ti-paperclip me-1"></i>{{ $att->original_name }}
                                    </a>
                                    @if($editable)
                                        <button type="submit" form="delAtt{{ $att->id }}" class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Remove this attachment?')">Remove</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @elseif(! $editable)
                        <div class="text-muted small">No attachments.</div>
                    @endif
                </div>
            </div>
        </form>

        @if($journal)
            @foreach($journal->attachments as $att)
                <form id="delAtt{{ $att->id }}" method="POST" action="{{ route('finance.journal-entry.attachment.delete') }}" class="d-none">
                    @csrf
                    <input type="hidden" name="journal_id" value="{{ $journal->id }}">
                    <input type="hidden" name="attachment_id" value="{{ $att->id }}">
                </form>
            @endforeach
        @endif

        <div class="card">
            <div class="card-body d-flex flex-wrap gap-2 justify-content-end">
                <a href="{{ route('finance.journal-entry.index.view', ['company_id' => $companyId]) }}"
                    class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center"
                    style="height: 2.25rem; min-width: 7.5rem;">
                    <i class="ti ti-arrow-left me-1"></i> Back
                </a>
                @if($editable)
                    <button type="button" id="btnSave"
                        class="btn btn-sm btn-outline-primary d-inline-flex align-items-center justify-content-center"
                        style="height: 2.25rem; min-width: 7.5rem;">
                        <i class="ti ti-device-floppy me-1"></i> Save Draft
                    </button>
                    <button type="button" id="btnPost"
                        class="btn btn-sm btn-primary d-inline-flex align-items-center justify-content-center"
                        style="height: 2.25rem; min-width: 7.5rem;">
                        <i class="ti ti-check me-1"></i> Post
                    </button>
                @elseif($journal?->isPosted() && $hasUpdate)
                    <form method="POST" action="{{ route('finance.journal-entry.unpost') }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="id" value="{{ $journal->id }}">
                        <button type="submit"
                            class="btn btn-sm btn-outline-warning d-inline-flex align-items-center justify-content-center"
                            style="height: 2.25rem; min-width: 7.5rem;"
                            onclick="return confirm('Unpost this journal?')">
                            <i class="ti ti-lock-open me-1"></i> Unpost
                        </button>
                    </form>
                @endif
                @if($journal?->isDraft() && session('permissions.Journal Entry.is_delete', false) == 1)
                    <form method="POST" action="{{ route('finance.journal-entry.delete') }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="id" value="{{ $journal->id }}">
                        <button type="submit"
                            class="btn btn-sm btn-outline-danger d-inline-flex align-items-center justify-content-center"
                            style="height: 2.25rem; min-width: 7.5rem;"
                            onclick="return confirm('Delete this draft journal?')">
                            <i class="ti ti-trash me-1"></i> Delete
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(document).ready(function() {
                var lineIndex = {{ count($lines) }};
                var editable = @json($editable);

                function parseNum(val) {
                    return parseFloat(String(val || 0).replace(/\./g, '').replace(',', '.')) || 0;
                }
                function formatId(val) {
                    return parseFloat(val || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                function getAmount($el) {
                    var cleave = $el.data('cleave');
                    return cleave ? (parseFloat(cleave.getRawValue()) || 0) : parseNum($el.val());
                }

                function initCleave(scope) {
                    $(scope).find('.number-format').each(function() {
                        if ($(this).data('cleave') || $(this).prop('disabled')) return;
                        var raw = parseNum($(this).val());
                        var cleave = new Cleave(this, {
                            numeral: true,
                            numeralThousandsGroupStyle: 'thousand',
                            numeralDecimalMark: ',',
                            delimiter: '.',
                            numeralDecimalScale: 2,
                            onValueChanged: recalc
                        });
                        cleave.setRawValue(String(raw));
                        $(this).data('cleave', cleave);
                    });
                }

                function initSelect2(scope) {
                    $(scope).find('.select2-account').each(function() {
                        if ($(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2('destroy');
                        }
                    });
                    $(scope).find('.select2-account').select2({
                        placeholder: '— Select —',
                        allowClear: true,
                        width: '100%'
                    });
                }

                function selectedAccountIds(exceptSelect) {
                    var ids = [];
                    $('.line-account').each(function() {
                        if (exceptSelect && this === exceptSelect) return;
                        var val = $(this).val();
                        if (val) ids.push(String(val));
                    });
                    return ids;
                }

                function refreshAccountOptions() {
                    $('.line-account').each(function() {
                        var $select = $(this);
                        var current = String($select.val() || '');
                        var used = selectedAccountIds(this);
                        $select.find('option').each(function() {
                            var val = String($(this).attr('value') || '');
                            if (!val) {
                                $(this).prop('disabled', false);
                                return;
                            }
                            var taken = used.indexOf(val) !== -1 && val !== current;
                            $(this).prop('disabled', taken);
                        });
                        // Refresh select2 UI
                        if ($select.hasClass('select2-hidden-accessible')) {
                            $select.trigger('change.select2');
                        }
                    });
                }

                function recalc() {
                    var d = 0, c = 0;
                    $('.line-debit').each(function() { d += getAmount($(this)); });
                    $('.line-credit').each(function() { c += getAmount($(this)); });
                    var diff = Math.round((d - c) * 100) / 100;
                    $('#totalDebit').text(formatId(d));
                    $('#totalCredit').text(formatId(c));
                    $('#totalDiff').text(formatId(diff))
                        .toggleClass('text-success', Math.abs(diff) < 0.01)
                        .toggleClass('text-danger', Math.abs(diff) >= 0.01);
                }

                function syncRaw() {
                    $('.number-format').each(function() {
                        var cleave = $(this).data('cleave');
                        $(this).val(cleave ? (cleave.getRawValue() || '0') : String(parseNum($(this).val())));
                    });
                }

                if (editable) {
                    $('.flatpickr-date').flatpickr({ dateFormat: 'd/m/Y', disableMobile: true, allowInput: true });
                    initSelect2(document);
                    initCleave(document);
                    refreshAccountOptions();
                    recalc();

                    $(document).on('change', '.line-account', function() {
                        refreshAccountOptions();
                    });

                    $('#btnAddLine').on('click', function() {
                        var accounts = @json($accounts->map(fn ($a) => ['id' => $a->id, 'label' => $a->code.' — '.$a->name])->values());
                        var used = selectedAccountIds(null);
                        var opts = accounts.map(function(a) {
                            var disabled = used.indexOf(String(a.id)) !== -1 ? ' disabled' : '';
                            return '<option value="' + a.id + '"' + disabled + '>' + $('<div>').text(a.label).html() + '</option>';
                        }).join('');
                        var html = `<tr class="line-row">
                            <td><select name="lines[${lineIndex}][account_id]" class="form-select form-select-sm line-account select2-account"><option value="">— Select —</option>${opts}</select></td>
                            <td><input type="text" name="lines[${lineIndex}][description]" class="form-control form-control-sm" maxlength="500" placeholder="Description per COA"></td>
                            <td><input type="text" name="lines[${lineIndex}][debit]" class="form-control form-control-sm text-end number-format line-debit" placeholder="0"></td>
                            <td><input type="text" name="lines[${lineIndex}][credit]" class="form-control form-control-sm text-end number-format line-credit" placeholder="0"></td>
                            <td class="text-center"><button type="button" class="btn btn-sm btn-icon btn-remove-line"><i class="ti ti-trash text-danger"></i></button></td>
                        </tr>`;
                        var $row = $(html);
                        $('#linesBody').append($row);
                        initSelect2($row);
                        initCleave($row);
                        refreshAccountOptions();
                        lineIndex++;
                    });

                    $(document).on('click', '.btn-remove-line', function() {
                        if ($('#linesBody .line-row').length <= 2) {
                            alert('Keep at least two lines.');
                            return;
                        }
                        $(this).closest('tr').remove();
                        refreshAccountOptions();
                        recalc();
                    });

                    $('#btnSave').on('click', function() {
                        $('#formAction').val('save');
                        syncRaw();
                        $('#journalForm').submit();
                    });
                    $('#btnPost').on('click', function() {
                        if (!confirm('Post this journal? Debit must equal Credit.')) return;
                        $('#formAction').val('post');
                        syncRaw();
                        $('#journalForm').submit();
                    });

                    @if($mode === 'insert')
                    $('#company_id').on('change', function() {
                        var cid = $(this).val();
                        window.location.href = @json(route('finance.journal-entry.insert.view')) + '?company_id=' + cid;
                    });
                    @endif
                } else {
                    recalc();
                }
            });
        </script>
    @endpush
</x-app-layout>
