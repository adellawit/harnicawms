<x-app-layout>
    @section('title', 'Detail Agent | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Customer'],
            ['label' => 'Network', 'url' => route('partner.reports.index')],
            ['label' => 'Agents', 'url' => route('partner.agents.index')],
            ['label' => $agent->name, 'active' => true],
        ]" />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><small class="text-muted">Kode</small><div><code>{{ $agent->code }}</code></div></div>
                    <div class="col-md-3"><small class="text-muted">Nama</small><div class="fw-medium">{{ $agent->name }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Status</small><div><span class="badge bg-label-success">{{ $agent->status }}</span></div></div>
                    <div class="col-md-3"><small class="text-muted">Approval</small><div>{{ $agent->approval_status }}</div></div>
                    <div class="col-md-6"><small class="text-muted">Kontak</small><div>{{ $agent->email ?: '-' }} · {{ $agent->phone ?: '-' }}</div></div>
                    <div class="col-md-6"><small class="text-muted">Customer</small><div>
                        @if ($agent->customer)
                            <a href="{{ route('customer.list.edit.view', $agent->customer->id) }}">{{ $agent->customer->code }} {{ $agent->customer->name }}</a>
                        @else
                            -
                        @endif
                    </div></div>
                    <div class="col-md-6"><small class="text-muted">Username Login</small><div>{{ $agent->user?->username ?: '-' }}</div></div>
                    <div class="col-md-6"><small class="text-muted">Password Awal</small><div>{{ $agent->user ? 'agent12345 (wajib diganti saat login pertama)' : '-' }}</div></div>
                    <div class="col-md-6"><small class="text-muted">Kota (teks)</small><div>{{ $agent->city ?: '-' }}@if($agent->province), {{ $agent->province }}@endif</div></div>
                    <div class="col-md-6"><small class="text-muted">Kota ongkir (FK)</small><div>
                        @if ($agent->cityRef)
                            {{ $agent->cityRef->name }}@if($agent->cityRef->province) ({{ $agent->cityRef->province->name }})@endif
                        @else
                            <span class="text-muted">Belum diisi</span>
                        @endif
                    </div></div>
                    <div class="col-12"><small class="text-muted">Alamat</small><div>{{ $agent->address ?: '-' }}</div></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h6 class="card-title mb-0">Kota Tujuan Ongkir</h6></div>
            <div class="card-body">
                <p class="text-muted small mb-3">Pilih kota resmi untuk kalkulasi ongkir web-order agen→distributor (Slice 2).</p>
                <form method="POST" action="{{ route('partner.agents.update-city', $agent->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3 align-items-end">
                        @include('admin.master-data.shipping-rate._city-select', [
                            'fieldId' => 'agent_city_id',
                            'fieldName' => 'city_id',
                            'label' => 'Kota (untuk ongkir)',
                            'required' => false,
                            'selectedId' => old('city_id', $agent->city_id),
                            'selectedText' => optional($agent->cityRef)->name,
                            'tooltip' => 'Kota tujuan pengiriman stok dari distributor.',
                        ])
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Simpan Kota</button>
                        </div>
                    </div>
                    @error('city_id')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h6 class="card-title mb-0">Gudang Agent</h6></div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Kode</th><th>Nama</th><th>Default</th><th>Aktif</th></tr></thead>
                            <tbody>
                                @forelse($agent->warehouses as $warehouse)
                                    <tr>
                                        <td><code>{{ $warehouse->code }}</code></td>
                                        <td>{{ $warehouse->name }}</td>
                                        <td>{{ $warehouse->is_default ? 'Ya' : '-' }}</td>
                                        <td>{{ $warehouse->is_active ? 'Ya' : 'Tidak' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada gudang.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h6 class="card-title mb-0">Reseller Aktif</h6></div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Kode</th><th>Nama</th><th>Kontak</th></tr></thead>
                            <tbody>
                                @forelse($agent->resellers as $reseller)
                                    <tr>
                                        <td><code>{{ $reseller->code }}</code></td>
                                        <td><a href="{{ route('partner.resellers.show', $reseller->id) }}">{{ $reseller->name }}</a></td>
                                        <td>{{ $reseller->phone ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted">Belum ada reseller.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @include('admin.master-data.shipping-rate._city-select-js')
</x-app-layout>
