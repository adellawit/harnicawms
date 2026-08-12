<x-app-layout>
    @section('title', 'Detail Partner Application | ')

    @php
        $showResellerActions = $application->partner_type === 'RESELLER' && $application->status !== 'converted';
        $showAgentActions = $application->partner_type === 'AGENT' && $application->status !== 'converted';
        $showSidebar = $showResellerActions || $showAgentActions;
    @endphp

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
    @endpush

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
            <div class="{{ $showSidebar ? 'col-lg-8' : 'col-12' }}">
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
                                       href="https://www.google.com/maps?q={{ $application->latitude }},{{ $application->longitude }}">
                                        <i class="ti ti-map-pin me-1"></i> Buka di Google Maps
                                    </a>
                                </div>
                            @endif
                            <div class="col-md-6"><small class="text-muted">Marketplace</small>
                                <div>
                                    @php
                                        $marketplaces = array_filter([
                                            $application->marketplace_tokopedia
                                                ? 'Tokopedia' . ($application->marketplace_tokopedia_account ? ' ('.$application->marketplace_tokopedia_account.')' : '')
                                                : null,
                                            $application->marketplace_shopee
                                                ? 'Shopee' . ($application->marketplace_shopee_account ? ' ('.$application->marketplace_shopee_account.')' : '')
                                                : null,
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
                                    @php
                                        $fileExists = \Illuminate\Support\Facades\Storage::disk('public')->exists($doc->file_path);
                                    @endphp
                                    <tr>
                                        <td>{{ str_replace('_', ' ', $doc->document_type) }}</td>
                                        <td>
                                            @if ($fileExists)
                                                <a href="{{ route('partner.applications.documents.download', [$application->id, $doc->id]) }}" target="_blank">
                                                    {{ $doc->original_name }}
                                                </a>
                                            @else
                                                <span class="text-danger">{{ $doc->original_name }} (file tidak ditemukan)</span>
                                            @endif
                                        </td>
                                        <td><span class="badge bg-label-secondary">{{ $doc->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted">Tidak ada dokumen.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($application->status !== 'converted')
                <div class="card mb-4">
                    <div class="card-header"><h6 class="card-title mb-0">Follow-up</h6></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('partner.applications.followup', $application->id) }}" class="mb-4">
                            @csrf
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="followupApplicationStatus">Status Aplikasi</label>
                                    <select name="application_status" id="followupApplicationStatus" class="form-select select2-followup-status">
                                        @foreach ([
                                            'submitted' => 'Diajukan',
                                            'in_review' => 'Sedang Ditinjau',
                                            'assigned' => 'Ditugaskan',
                                            'qualified' => 'Lolos / Qualified',
                                            'rejected' => 'Ditolak',
                                        ] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('application_status', $application->status) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Pilih status terkini setelah follow-up ini.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="nextFollowupAt">Tanggal Follow-up Berikutnya</label>
                                    <input
                                        type="text"
                                        name="next_followup_at"
                                        id="nextFollowupAt"
                                        class="form-control flatpickr-date"
                                        placeholder="DD/MM/YYYY"
                                        value="{{ old('next_followup_at') }}"
                                        autocomplete="off"
                                    >
                                    <div class="form-text">Opsional. Jadwal kontak ulang berikutnya.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="followupType">Jenis Follow-up</label>
                                    <input
                                        type="text"
                                        name="followup_type"
                                        id="followupType"
                                        class="form-control"
                                        value="{{ old('followup_type') }}"
                                        placeholder="Contoh: telp, bertemu, email"
                                        maxlength="50"
                                    >
                                    <div class="form-text">Bebas diisi sesuai kanal komunikasi yang dipakai.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="followupNotes">Catatan Follow-up <span class="text-danger">*</span></label>
                                    <textarea
                                        name="notes"
                                        id="followupNotes"
                                        rows="3"
                                        class="form-control"
                                        placeholder="Ringkas hasil pembicaraan, komitmen calon partner, atau tindak lanjut."
                                        required
                                    >{{ old('notes') }}</textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm mt-3">
                                <i class="ti ti-device-floppy me-1"></i>Simpan Follow-up
                            </button>
                        </form>

                        <h6 class="mb-2">Riwayat Follow-up</h6>
                        @forelse ($application->followups as $followup)
                            <div class="border rounded p-2 mb-2">
                                <div class="small text-muted">
                                    {{ optional($followup->created_at)->format('d/m/Y H:i') }}
                                    · {{ $followup->user?->name ?? $followup->user?->username ?? '-' }}
                                    @if ($followup->followup_type)
                                        · {{ $followup->followup_type }}
                                    @endif
                                    @if ($followup->next_followup_at)
                                        · Next: {{ $followup->next_followup_at->format('d/m/Y') }}
                                    @endif
                                </div>
                                <div>{{ $followup->notes }}</div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Belum ada follow-up.</p>
                        @endforelse
                    </div>
                </div>
                @endif
            </div>

            @if ($showSidebar)
                <div class="col-lg-4">
                    @if ($showResellerActions)
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

                    @if ($showAgentActions)
                        <div class="card mb-4">
                            <div class="card-header"><h6 class="card-title mb-0">Convert Agent</h6></div>
                            <div class="card-body">
                                <p class="small text-muted mb-3">
                                    Convert akan membuat data Agent, lalu mengarahkan ke <strong>POS</strong> untuk pembayaran awal.
                                    Setelah pembayaran lunas, kode Agent dan nomor invoice awal akan ditampilkan.
                                </p>
                                <form method="POST" action="{{ route('partner.applications.convert-agent', $application->id) }}">
                                    @csrf
                                    <input type="text" name="code" class="form-control mb-2" placeholder="Kode Agent opsional (auto jika kosong)">
                                    <textarea name="notes" rows="2" class="form-control mb-2" placeholder="Catatan"></textarea>
                                    <button class="btn btn-success btn-sm">
                                        <i class="ti ti-shopping-cart me-1"></i>Convert &amp; Lanjut ke POS
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    @endpush

    @push('page-js')
        <script>
            $(function () {
                $('.select2-followup-status').select2({
                    width: '100%',
                    dropdownParent: $('body'),
                    minimumResultsForSearch: Infinity
                });

                $('.flatpickr-date').flatpickr({
                    dateFormat: 'd/m/Y',
                    allowInput: true,
                    disableMobile: true
                });
            });
        </script>
    @endpush
</x-app-layout>
