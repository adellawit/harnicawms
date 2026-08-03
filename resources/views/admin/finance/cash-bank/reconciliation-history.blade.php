<x-app-layout>
    @section('title', 'Reconciliation History | ')
    @push('vendor-css')
        @include('admin.finance.cash-bank._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report">
        @php
            $completedCount = $items->filter(fn ($i) => $i->isCompleted())->count();
            $draftCount = $items->count() - $completedCount;
        @endphp

        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Cash & Bank', 'url' => route('finance.cash-bank.index.view', ['company_id' => $companyId])],
            ['label' => 'History', 'active' => true],
        ]" />

        <div class="card fin-toolbar mb-3">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="fin-kpi-icon bg-label-secondary text-secondary">
                        <i class="ti ti-history"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-0">Reconciliation history</div>
                        <div class="fin-company">
                            <span class="fin-account-code">{{ $account->code }}</span>{{ $account->name }}
                        </div>
                    </div>
                </div>
                <a href="{{ route('finance.cash-bank.index.view', ['company_id' => $companyId]) }}" class="btn btn-sm btn-label-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="row g-3 mb-3 fin-kpi">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="fin-kpi-label">Total sessions</div>
                        <div class="fin-kpi-value">{{ $items->count() }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="fin-kpi-label">Completed</div>
                        <div class="fin-kpi-value text-success">{{ $completedCount }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="fin-kpi-label">Draft</div>
                        <div class="fin-kpi-value text-warning">{{ $draftCount }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card fin-section accent-secondary">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Sessions</h5>
                    <div class="fin-section-sub">Past and in-progress reconciliations</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table fin-table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end">Statement</th>
                            <th class="text-end">Book</th>
                            <th class="text-end">Difference</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            @php $ok = abs((float) $item->difference) < 0.01; @endphp
                            <tr>
                                <td class="fw-medium">{{ format_date_id($item->reconciliation_date) }}</td>
                                <td>
                                    @if($item->isCompleted())
                                        <span class="badge bg-label-success">Completed</span>
                                    @else
                                        <span class="badge bg-label-warning">Draft</span>
                                    @endif
                                </td>
                                <td class="text-end fin-amount">{{ format_number((float) $item->statement_balance, 2, true) }}</td>
                                <td class="text-end fin-amount">{{ format_number((float) $item->book_balance, 2, true) }}</td>
                                <td class="text-end fin-amount {{ $ok ? 'text-success' : 'text-warning' }}">
                                    {{ format_number((float) $item->difference, 2, true) }}
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('finance.cash-bank.reconciliation.view', $item->id) }}" class="btn btn-sm btn-label-primary">
                                        <i class="ti ti-eye me-1"></i> Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">No reconciliations yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($items->hasPages())
                <div class="card-footer">{{ $items->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
