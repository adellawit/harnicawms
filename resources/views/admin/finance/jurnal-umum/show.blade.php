<x-app-layout>
    @section('title', 'Jurnal Umum Detail | ')

    @push('vendor-css')
        @include('admin.finance.reports._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report" style="padding-bottom: 70px !important;">
        @php
            $hasUpdateEntry = session('permissions.Journal Entry.is_update', false) == 1;
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Jurnal Umum', 'url' => route('finance.jurnal-umum.index.view', ['company_id' => $journal->company_id])],
            ['label' => $journal->journal_no, 'active' => true],
        ]" />

        <div class="card fin-toolbar mb-3">
            <div class="card-body d-flex flex-wrap justify-content-between gap-3 align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="fin-kpi-icon bg-label-primary text-primary"><i class="ti ti-file-text"></i></div>
                    <div>
                        <div class="text-muted small mb-0">{{ $journal->company?->name }}</div>
                        <div class="fin-company"><span class="fin-account-code">{{ $journal->journal_no }}</span></div>
                        <div class="d-flex flex-wrap gap-2 align-items-center mt-1">
                            <span class="small">{{ format_date_id($journal->journal_date) }}</span>
                            @if($journal->isPosted())
                                <span class="badge bg-label-success">Posted</span>
                            @else
                                <span class="badge bg-label-warning">Draft</span>
                            @endif
                            @if($journal->fiscalPeriod)
                                <span class="badge bg-label-secondary">{{ $journal->fiscalPeriod->code }}</span>
                            @endif
                        </div>
                        @if($journal->description)
                            <div class="mt-2 text-body">{{ $journal->description }}</div>
                        @endif
                        <div class="small text-muted font-monospace mt-1">{{ $journal->id }}</div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @if($hasUpdateEntry)
                        <a href="{{ route('finance.journal-entry.edit.view', $journal->id) }}" class="btn btn-sm btn-primary">
                            <i class="ti ti-pencil me-1"></i> {{ $journal->isDraft() ? 'Edit' : 'Open' }}
                        </a>
                    @endif
                    <a href="{{ route('finance.jurnal-umum.index.view', ['company_id' => $journal->company_id]) }}" class="btn btn-sm btn-label-secondary">
                        <i class="ti ti-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div class="card fin-section accent-primary mb-3">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Lines</h5>
                    <div class="fin-section-sub">Journal line items</div>
                </div>
                <div class="text-end">
                    <div class="fin-section-sub">Debit / Credit</div>
                    <div class="fin-amount">
                        {{ format_number($journal->totalDebit(), 2, true) }}
                        <span class="text-muted mx-1">/</span>
                        {{ format_number($journal->totalCredit(), 2, true) }}
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table fin-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Account</th>
                            <th>Description</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end pe-3">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($journal->lines as $line)
                            <tr>
                                <td>{{ $line->line_no }}</td>
                                <td>
                                    <span class="fin-account-code">{{ $line->account?->code }}</span>
                                    {{ $line->account?->name }}
                                </td>
                                <td>{{ $line->description ?: '—' }}</td>
                                <td class="text-end">{{ (float) $line->debit > 0 ? format_number((float) $line->debit, 2, true) : '—' }}</td>
                                <td class="text-end pe-3">{{ (float) $line->credit > 0 ? format_number((float) $line->credit, 2, true) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card fin-section accent-secondary">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Attachments</h5>
                </div>
            </div>
            <div class="card-body">
                @forelse($journal->attachments as $att)
                    <div class="mb-2">
                        <a href="{{ $att->url() }}" target="_blank" rel="noopener">
                            <i class="ti ti-paperclip me-1"></i>{{ $att->original_name }}
                        </a>
                    </div>
                @empty
                    <div class="text-muted">No attachments.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
