<x-app-layout>
    @section('title', 'Detail Partner Application | ')

    @include('admin.partials.product-variant-select2')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Customer'],
            ['label' => 'Network', 'url' => route('partner.reports.index')],
            ['label' => 'Applications', 'url' => route('partner.applications.index')],
            ['label' => $application->application_number, 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if ($errors->any())<x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>@endif

        <div class="d-flex justify-content-end mb-3 gap-2">
            @permission('Partner Application', 'is_update')
                @if ($application->isEditable())
                    <a href="{{ route('partner.applications.edit', $application) }}" class="btn btn-primary btn-sm">
                        <i class="ti ti-edit me-1"></i> Ubah
                    </a>
                @endif
            @endpermission
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title mb-0">Application {{ $application->application_number }}</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4"><small class="text-muted">Tipe</small><div><span class="badge bg-label-primary">{{ $application->partner_type }}</span></div></div>
                            <div class="col-md-4"><small class="text-muted">Status</small><div><span class="badge bg-label-secondary">{{ str_replace('_', ' ', $application->status) }}</span></div></div>
                            <div class="col-md-4"><small class="text-muted">Tanggal Submit</small><div>{{ optional($application->submitted_at)->format('d/m/Y H:i') ?: '-' }}</div></div>
                            <div class="col-md-6"><small class="text-muted">Nama</small><div class="fw-medium">{{ $application->name }}</div></div>
                            <div class="col-md-6"><small class="text-muted">TTL</small><div>{{ $application->birth_place ?: '-' }}, {{ optional($application->birth_date)->format('d/m/Y') ?: '-' }}</div></div>
                            <div class="col-md-6"><small class="text-muted">Kontak</small><div>{{ $application->email ?: '-' }} · {{ $application->phone }}</div></div>
                            <div class="col-md-6"><small class="text-muted">Tanggal Pengisian</small><div>{{ optional($application->filled_at)->format('d/m/Y') ?: '-' }}</div></div>
                            <div class="col-12"><small class="text-muted">Alamat KTP</small><div>{{ $application->address_ktp ?: '-' }}</div></div>
                            <div class="col-12"><small class="text-muted">Alamat Domisili</small><div>{{ $application->address ?: '-' }}{{ $application->city ? ', ' . $application->city : '' }}{{ $application->province ? ', ' . $application->province : '' }}{{ $application->postal_code ? ' ' . $application->postal_code : '' }}</div></div>
                            <div class="col-md-6"><small class="text-muted">Latitude</small><div>{{ $application->latitude ?: '-' }}</div></div>
                            <div class="col-md-6"><small class="text-muted">Longitude</small><div>{{ $application->longitude ?: '-' }}</div></div>
                            @if ($application->latitude && $application->longitude)
                                <div class="col-12">
                                    <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"
                                       href="https://www.openstreetmap.org/?mlat={{ $application->latitude }}&mlon={{ $application->longitude }}#map=16/{{ $application->latitude }}/{{ $application->longitude }}">
                                        <i class="ti ti-map-pin me-1"></i> Buka di OpenStreetMap
                                    </a>
                                </div>
                            @endif
                            <div class="col-md-6"><small class="text-muted">Marketplace</small>
                                <div>
                                    @php
                                        $marketplaces = array_filter([
                                            $application->marketplace_tokopedia ? 'Tokopedia' : null,
                                            $application->marketplace_shopee ? 'Shopee' : null,
                                            $application->marketplace_other,
                                        ]);
                                    @endphp
                                    {{ $marketplaces ? implode(', ', $marketplaces) : '-' }}
                                </div>
                            </div>
                            @if ($application->partner_type === 'RESELLER')
                                <div class="col-md-6"><small class="text-muted">Paket Reseller</small><div>Paket {{ $application->reseller_package ?: '-' }}</div></div>
                            @endif
                            <div class="col-md-6"><small class="text-muted">Jumlah Pembelian Produk</small><div>{{ rtrim(rtrim(number_format((float) $application->requested_purchase_quantity, 4, '.', ''), '0'), '.') }} box</div></div>
                            <div class="col-md-6"><small class="text-muted">Customer/Lead</small><div>{{ $application->customer?->code }} {{ $application->customer?->name }}</div></div>
                            <div class="col-md-6"><small class="text-muted">Assigned Agent</small><div>{{ $application->assignedAgent?->name ?? '-' }}</div></div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h6 class="card-title mb-0">Dokumen Persyaratan</h6></div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Tipe</th><th>File</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse ($application->documents as $doc)
                                    <tr>
                                        <td>{{ $doc->document_type }}</td>
                                        <td><a href="{{ Storage::url($doc->file_path) }}" target="_blank">{{ $doc->original_name }}</a></td>
                                        <td><span class="badge bg-label-secondary">{{ $doc->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted">Tidak ada dokumen.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h6 class="card-title mb-0">Follow-up</h6></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('partner.applications.followup', $application->id) }}" class="mb-4">
                            @csrf
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <select name="application_status" class="form-select">
                                        @foreach(['submitted','in_review','assigned','qualified','rejected'] as $status)
                                            <option value="{{ $status }}" @selected($application->status === $status)>{{ str_replace('_',' ', $status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4"><input type="datetime-local" name="next_followup_at" class="form-control"></div>
                                <div class="col-md-4"><input type="text" name="followup_type" class="form-control" value="manual"></div>
                                <div class="col-12"><textarea name="notes" rows="3" class="form-control" placeholder="Catatan follow-up" required></textarea></div>
                            </div>
                            <button class="btn btn-primary btn-sm mt-2">Simpan Follow-up</button>
                        </form>

                        @forelse ($application->followups as $followup)
                            <div class="border rounded p-2 mb-2">
                                <div class="small text-muted">{{ optional($followup->created_at)->format('d/m/Y H:i') }} · {{ $followup->user?->name ?? $followup->user?->username }}</div>
                                <div>{{ $followup->notes }}</div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Belum ada follow-up.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                @if ($application->partner_type === 'RESELLER' && $application->status !== 'converted')
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="card-title mb-0">Assign / Convert Reseller</h6></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('partner.applications.assign-agent', $application->id) }}" class="mb-3">
                                @csrf
                                <label class="form-label">Assign ke Agent</label>
                                <select name="agent_id" class="form-select mb-2" required>
                                    <option value="">-- Pilih Agent --</option>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" @selected($application->assigned_agent_id === $agent->id)>{{ $agent->code }} - {{ $agent->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-primary btn-sm">Assign</button>
                            </form>
                            <form method="POST" action="{{ route('partner.applications.convert-reseller', $application->id) }}">
                                @csrf
                                <input type="hidden" name="agent_id" value="{{ $application->assigned_agent_id }}">
                                <input type="text" name="code" class="form-control mb-2" placeholder="Kode reseller opsional">
                                <textarea name="notes" rows="2" class="form-control mb-2" placeholder="Catatan"></textarea>
                                <button class="btn btn-success btn-sm" @disabled(!$application->assigned_agent_id)>Convert Reseller</button>
                            </form>
                        </div>
                    </div>
                @endif

                @if ($application->partner_type === 'AGENT' && $application->status !== 'converted')
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="card-title mb-0">Convert Agent</h6></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('partner.applications.convert-agent', $application->id) }}">
                                @csrf
                                <input type="text" name="code" class="form-control mb-2" placeholder="Kode Agent opsional">
                                <input type="text" name="invoice_number" class="form-control mb-2" placeholder="No. invoice awal">
                                <input type="text" name="payment_reference" class="form-control mb-2" placeholder="Referensi pembayaran">
                                <select name="payment_status" class="form-select mb-2">
                                    <option value="unpaid">Belum Dibayar</option>
                                    <option value="partial">Sebagian</option>
                                    <option value="paid">Lunas</option>
                                </select>
                                <p class="small text-muted mb-2">Item pembelian awal opsional.</p>
                                <div id="initial-lines"></div>
                                <button type="button" class="btn btn-sm btn-outline-primary mb-2" onclick="addInitialLine()">Tambah Item</button>
                                <textarea name="notes" rows="2" class="form-control mb-2" placeholder="Catatan"></textarea>
                                <button class="btn btn-success btn-sm">Convert Agent</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('page-js')
    <script>
        let initialLineIndex = 0;
        function addInitialLine() {
            const el = document.createElement('div');
            el.className = 'border rounded p-2 mb-2';
            el.innerHTML = `<select name="lines[${initialLineIndex}][variant_id]" class="form-select mb-2 variant-select" style="width: 100%;"><option value="">-- Pilih produk --</option></select><div class="row g-2"><div class="col-6"><input type="number" min="0" step="any" name="lines[${initialLineIndex}][qty]" class="form-control" placeholder="Qty"></div><div class="col-6"><input type="number" min="0" step="any" name="lines[${initialLineIndex}][unit_price]" class="form-control" placeholder="Harga"></div></div>`;
            document.getElementById('initial-lines').appendChild(el);
            window.initProductVariantSelect2($(el).find('.variant-select'));
            initialLineIndex++;
        }
    </script>
    @endpush
</x-app-layout>
