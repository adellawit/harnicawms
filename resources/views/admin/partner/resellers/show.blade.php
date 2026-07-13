<x-app-layout>
    @section('title', 'Detail Reseller | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Customer'],
            ['label' => 'Network', 'url' => route('partner.reports.index')],
            ['label' => 'Resellers', 'url' => route('partner.resellers.index')],
            ['label' => $reseller->name, 'active' => true],
        ]" />

        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><small class="text-muted">Kode</small><div><code>{{ $reseller->code }}</code></div></div>
                    <div class="col-md-3"><small class="text-muted">Nama</small><div class="fw-medium">{{ $reseller->name }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Status</small><div><span class="badge bg-label-success">{{ $reseller->status }}</span></div></div>
                    <div class="col-md-3"><small class="text-muted">Agent</small><div><a href="{{ route('partner.agents.show', $reseller->agent_id) }}">{{ $reseller->agent?->name }}</a></div></div>
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
    </div>
</x-app-layout>
