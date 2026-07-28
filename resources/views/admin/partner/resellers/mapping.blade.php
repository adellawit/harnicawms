<x-app-layout>
    @section('title', 'Reseller Mapping | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            title="Reseller Mapping"
            subtitle="Petakan Reseller ke Agent. Reseller baru dari register boleh Unassigned sampai di-assign."
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Customer'],
                ['label' => 'Network', 'url' => route('partner.reports.index')],
                ['label' => 'Reseller Mapping', 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">{{ $errors->first() }}</x-alert>
        @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('partner.resellers.mapping.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Filter Agent saat ini</label>
                        <select name="agent_id" class="form-select select2">
                            <option value="">Semua</option>
                            <option value="unassigned" @selected(($filters['agent_id'] ?? null) === 'unassigned')>Unassigned</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected(($filters['agent_id'] ?? null) === $agent->id)>
                                    {{ $agent->code }} · {{ $agent->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cari</label>
                        <input type="search" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Kode / nama / email">
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button class="btn btn-primary" type="submit"><i class="ti ti-search me-1"></i>Filter</button>
                        <a href="{{ route('partner.resellers.mapping.index') }}" class="btn btn-label-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <form method="POST" action="{{ route('partner.resellers.mapping.store') }}" id="bulkMappingForm">
            @csrf
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">Agent tujuan</label>
                            <select name="agent_id" id="bulkAgentId" class="form-select select2" @disabled($currentAgent)>
                                <option value="">Pilih Agent</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}" @selected($currentAgent?->id === $agent->id)>
                                        {{ $agent->code }} · {{ $agent->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($currentAgent)
                                <input type="hidden" name="agent_id" value="{{ $currentAgent->id }}">
                                <div class="form-text">Agent hanya dapat assign ke dirinya sendiri.</div>
                            @endif
                        </div>
                        <div class="col-md-7 d-flex flex-wrap gap-2">
                            <button type="submit" name="action" value="assign" class="btn btn-primary">
                                <i class="ti ti-link me-1"></i>Assign terpilih
                            </button>
                            @if ($canUnassign)
                                <button type="submit" name="action" value="unassign" class="btn btn-label-warning"
                                    onclick="return confirm('Lepas mapping Agent untuk Reseller terpilih?')">
                                    <i class="ti ti-unlink me-1"></i>Unassign terpilih
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">
                                    <input type="checkbox" class="form-check-input" id="checkAllResellers">
                                </th>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Agent saat ini</th>
                                <th>Customer</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($resellers as $reseller)
                                <tr>
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="form-check-input reseller-check"
                                            name="reseller_ids[]"
                                            value="{{ $reseller->id }}"
                                        >
                                    </td>
                                    <td><code>{{ $reseller->code }}</code></td>
                                    <td>
                                        <a href="{{ route('partner.resellers.show', $reseller->id) }}">{{ $reseller->name }}</a>
                                    </td>
                                    <td>
                                        @if ($reseller->agent)
                                            {{ $reseller->agent->code }} · {{ $reseller->agent->name }}
                                        @else
                                            <span class="badge bg-label-warning">Unassigned</span>
                                        @endif
                                    </td>
                                    <td>{{ $reseller->customer?->code }} {{ $reseller->customer?->name }}</td>
                                    <td><span class="badge bg-label-success">{{ $reseller->status }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Tidak ada Reseller untuk filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($resellers->hasPages())
                    <div class="card-footer">{{ $resellers->links() }}</div>
                @endif
            </div>
        </form>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(function () {
                $('.select2').select2({ width: '100%', dropdownParent: $('body') });
                $('#checkAllResellers').on('change', function () {
                    $('.reseller-check').prop('checked', this.checked);
                });
                $('#bulkMappingForm').on('submit', function (e) {
                    if ($('.reseller-check:checked').length === 0) {
                        e.preventDefault();
                        alert('Pilih minimal satu Reseller.');
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
