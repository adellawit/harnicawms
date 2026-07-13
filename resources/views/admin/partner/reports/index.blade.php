<x-app-layout>
    @section('title', 'Network | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Customer'],
            ['label' => 'Network', 'active' => true],
        ]" />

        <div class="row mb-4">
            <div class="col-md-3 mb-3"><x-dashboard.kpi-card title="Applications" :value="$summary['applications']" icon="ti ti-clipboard-list" icon-color="primary" /></div>
            <div class="col-md-3 mb-3"><x-dashboard.kpi-card title="Agents" :value="$summary['agents']" icon="ti ti-users" icon-color="success" /></div>
            <div class="col-md-3 mb-3"><x-dashboard.kpi-card title="Resellers" :value="$summary['resellers']" icon="ti ti-user-star" icon-color="info" /></div>
            <div class="col-md-3 mb-3"><x-dashboard.kpi-card title="Replenishment" :value="'Rp ' . number_format((float) $summary['replenishment_total'], 0)" icon="ti ti-truck" icon-color="warning" /></div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Stock Summary per Agent Warehouse</h5>
                <div>
                    <a href="{{ route('partner.network-map.index') }}" class="btn btn-sm btn-primary"><i class="ti ti-map me-1"></i> Peta Jaringan</a>
                    <a href="{{ route('partner.applications.index') }}" class="btn btn-sm btn-outline-primary">Applications</a>
                    <a href="{{ route('partner.agents.index') }}" class="btn btn-sm btn-outline-primary">Agents</a>
                    <a href="{{ route('partner.resellers.index') }}" class="btn btn-sm btn-outline-primary">Resellers</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Agent</th><th>Warehouse</th><th class="text-end">Qty Stok</th></tr></thead>
                    <tbody>
                        @forelse($agentStocks as $row)
                            <tr>
                                <td><a href="{{ route('partner.agents.show', $row['agent']->id) }}">{{ $row['agent']->code }} - {{ $row['agent']->name }}</a></td>
                                <td>{{ $row['warehouse']?->name ?? '-' }}</td>
                                <td class="text-end">{{ number_format((float) $row['stock_qty'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted">Belum ada Agent.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
