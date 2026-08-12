<x-app-layout>
    @section('title', 'Detail Agent | ')

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
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
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
                    <div class="col-12"><small class="text-muted">Alamat</small><div>{{ $agent->address ?: '-' }}</div></div>
                </div>
            </div>
        </div>

        {{-- PKS --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">PKS / Perjanjian Kerja Sama</h6>
                @php
                    $badgeMap = [
                        'missing' => ['Belum PKS', 'bg-label-warning'],
                        'active' => ['Aktif', 'bg-label-success'],
                        'expiring' => ['Segera berakhir', 'bg-label-danger'],
                        'expired' => ['Expired', 'bg-label-secondary'],
                        'none' => ['Menunggu transaksi pertama', 'bg-label-secondary'],
                    ];
                    [$pksLabel, $pksClass] = $badgeMap[$pksBadge ?? 'none'] ?? $badgeMap['none'];
                @endphp
                <span class="badge {{ $pksClass }}">{{ $pksLabel }}</span>
            </div>
            <div class="card-body">
                @if ($agent->activePks)
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <small class="text-muted">File</small>
                            <div>
                                <a href="{{ route('partner.agents.pks.download', [$agent->id, $agent->activePks->id]) }}">
                                    <i class="ti ti-file-text me-1"></i>{{ $agent->activePks->file_name }}
                                </a>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Periode</small>
                            <div>
                                {{ $agent->activePks->start_date?->format('d/m/Y') }}
                                –
                                {{ $agent->activePks->end_date?->format('d/m/Y') }}
                            </div>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">Sisa hari</small>
                            <div>
                                @php $days = $agent->activePks->daysUntilEnd(); @endphp
                                {{ $days === null ? '-' : ($days < 0 ? 'Expired' : $days . ' hari') }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Diunggah</small>
                            <div>{{ $agent->activePks->uploader?->name ?: $agent->activePks->uploader?->username ?: '-' }}</div>
                        </div>
                        @if ($agent->activePks->notes)
                            <div class="col-12"><small class="text-muted">Catatan</small><div>{{ $agent->activePks->notes }}</div></div>
                        @endif
                    </div>
                @else
                    <p class="text-muted mb-4">Belum ada PKS aktif.</p>
                @endif

                @permission('Partner Agent', 'is_update')
                <div class="border rounded p-3 bg-lighter">
                    <h6 class="mb-3">{{ $agent->activePks ? 'Upload PKS baru (perpanjang)' : 'Upload PKS' }}</h6>
                    @if (! $canUploadPks)
                        <div class="alert alert-warning mb-0">
                            Selesaikan transaksi pertama di POS (pembayaran lunas) sebelum mengunggah PKS.
                        </div>
                    @else
                        <form method="POST" action="{{ route('partner.agents.pks.store', $agent->id) }}" enctype="multipart/form-data" class="row g-3">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label" for="pks_file">File PKS <span class="text-danger">*</span></label>
                                <input type="file" name="file" id="pks_file" class="form-control @error('file') is-invalid @enderror"
                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                                <div class="form-text">PDF, DOC, DOCX, JPG, PNG · max 10 MB</div>
                                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="start_date">Start date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" id="start_date"
                                    class="form-control @error('start_date') is-invalid @enderror"
                                    value="{{ old('start_date') }}" required>
                                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="end_date">End date <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" id="end_date"
                                    class="form-control @error('end_date') is-invalid @enderror"
                                    value="{{ old('end_date') }}" required>
                                @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="pks_notes">Catatan</label>
                                <input type="text" name="notes" id="pks_notes" class="form-control" value="{{ old('notes') }}" maxlength="2000">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ti ti-upload me-1"></i>Simpan PKS
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
                @endpermission
            </div>

            @if ($agent->pksDocuments->isNotEmpty())
                <div class="table-responsive border-top">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Riwayat</th>
                                <th>Periode</th>
                                <th>Status</th>
                                <th>Upload</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($agent->pksDocuments as $pks)
                                <tr>
                                    <td>{{ $pks->file_name }}</td>
                                    <td>{{ $pks->start_date?->format('d/m/Y') }} – {{ $pks->end_date?->format('d/m/Y') }}</td>
                                    <td><span class="badge bg-label-secondary">{{ $pks->status }}</span></td>
                                    <td>{{ $pks->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('partner.agents.pks.download', [$agent->id, $pks->id]) }}" class="btn btn-sm btn-outline-primary">Download</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
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
</x-app-layout>
