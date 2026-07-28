<x-app-layout>
    @section('title', 'Detail Reseller | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Customer'],
            ['label' => 'Network', 'url' => route('partner.reports.index')],
            ['label' => 'Resellers', 'url' => route('partner.resellers.index')],
            ['label' => $reseller->name, 'active' => true],
        ]" />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">{{ $errors->first() }}</x-alert>
        @endif

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><small class="text-muted">Kode</small><div><code>{{ $reseller->code }}</code></div></div>
                    <div class="col-md-3"><small class="text-muted">Nama</small><div class="fw-medium">{{ $reseller->name }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Status</small><div><span class="badge bg-label-success">{{ $reseller->status }}</span></div></div>
                    <div class="col-md-3">
                        <small class="text-muted">Agent</small>
                        <div>
                            @if ($reseller->agent)
                                <a href="{{ route('partner.agents.show', $reseller->agent_id) }}">{{ $reseller->agent->code }} · {{ $reseller->agent->name }}</a>
                            @else
                                <span class="badge bg-label-warning">Unassigned</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6"><small class="text-muted">Kontak</small><div>{{ $reseller->email ?: '-' }} · {{ $reseller->phone ?: '-' }}</div></div>
                    <div class="col-md-6"><small class="text-muted">Customer</small><div>
                        @if ($reseller->customer)
                            <a href="{{ route('customer.list.edit.view', $reseller->customer->id) }}">{{ $reseller->customer->code }} {{ $reseller->customer->name }}</a>
                        @else
                            -
                        @endif
                    </div></div>
                    <div class="col-12"><small class="text-muted">Alamat</small><div>{{ $reseller->address ?: '-' }}</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Mapping Agent</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('partner.resellers.mapping.update', $reseller->id) }}" class="row g-3 align-items-end">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="action" value="assign">
                    <div class="col-md-6">
                        <label class="form-label">Agent tujuan</label>
                        <select name="agent_id" class="form-select select2" @disabled($currentAgent) required>
                            <option value="">Pilih Agent</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected(($currentAgent?->id ?? $reseller->agent_id) === $agent->id)>
                                    {{ $agent->code }} · {{ $agent->name }}
                                </option>
                            @endforeach
                        </select>
                        @if ($currentAgent)
                            <input type="hidden" name="agent_id" value="{{ $currentAgent->id }}">
                            <div class="form-text">Anda hanya dapat memetakan Reseller ke Agent Anda sendiri.</div>
                        @endif
                    </div>
                    <div class="col-md-6 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-link me-1"></i>Assign Agent
                        </button>
                        @if ($canUnassign && $reseller->agent_id)
                            <button
                                type="submit"
                                class="btn btn-label-warning"
                                name="action"
                                value="unassign"
                                onclick="return confirm('Lepas mapping Agent untuk Reseller ini?')"
                            >
                                <i class="ti ti-unlink me-1"></i>Unassign
                            </button>
                        @endif
                        <a href="{{ route('partner.resellers.mapping.index') }}" class="btn btn-label-secondary">
                            Bulk Mapping
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(function () {
                $('.select2').select2({ width: '100%', dropdownParent: $('body') });
            });
        </script>
    @endpush
</x-app-layout>
