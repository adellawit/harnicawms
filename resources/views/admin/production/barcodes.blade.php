<x-app-layout>
    @section('title', 'Production Barcode Detail | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production Order', 'url' => route('production.index')],
                ['label' => $order->order_number, 'url' => route('production.show', $order->id)],
                ['label' => 'Barcode Detail', 'active' => true],
            ]"
        />

        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h5 class="card-title mb-1">Barcode · {{ $order->order_number }}</h5>
                    <div class="text-muted">
                        {{ $order->variant?->display_name ?? $order->product?->name }}
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if ($order->status === 'completed' && (float) $order->produced_qty > 0)
                        <a href="{{ route('production.receive.print', $order->id) }}" class="btn btn-primary">
                            <i class="ti ti-printer me-1"></i> Print / PDF
                        </a>
                    @endif
                    <a href="{{ route('production.show', $order->id) }}" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Total Serial</small>
                        <div class="fw-semibold fs-5">{{ format_number($totalSerials, 0, true) }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Ready (belum terjual)</small>
                        <div class="fw-semibold fs-5 text-success">{{ format_number($totalReady, 0, true) }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Dispatched (sudah keluar / terjual)</small>
                        <div class="fw-semibold fs-5 text-warning">{{ format_number($totalDispatched, 0, true) }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Qty Produksi</small>
                        <div class="fw-medium">
                            {{ format_number($qtyBase ?? 0, 4, true) }}
                            {{ $qtyUnitLabel ?? '' }}
                        </div>
                    </div>
                </div>

                @if (!empty($conversionChain))
                    <div class="mt-3 pt-3 border-top">
                        <small class="text-muted d-block mb-1">Aturan konversi</small>
                        <div class="fw-medium">{{ implode(' · ', $conversionChain) }}</div>
                    </div>
                @endif

                @if (!empty($qtyLevels))
                    <div class="mt-3">
                        <small class="text-muted d-block mb-1">Setara di setiap satuan</small>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($qtyLevels as $level)
                                <span class="badge bg-label-{{ !empty($level['is_base']) ? 'primary' : 'secondary' }}">
                                    {{ format_number($level['qty'], 4, true) }} {{ strtoupper($level['label']) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (!empty($packagingRows))
                    <div class="mt-3">
                        <small class="text-muted d-block mb-1">Pecahan kemasan fisik (tersimpan per satuan)</small>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($packagingRows as $row)
                                <span class="badge bg-label-info">
                                    {{ format_number($row['qty'], 0, true) }} {{ strtoupper($row['label']) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if (!empty($expectedBarcodeRows))
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Detail Barcode Expected vs Aktual</h6>
                    <small class="text-muted">Expected = hierarchy dari qty produksi (tanpa sachet). Aktual = serial terkunci ke receive ini.</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Level</th>
                                <th>Unit</th>
                                <th>Isi label</th>
                                <th class="text-end">Expected</th>
                                <th class="text-end">Aktual Serial</th>
                                <th class="text-end">Selisih</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($expectedBarcodeRows as $row)
                                @php
                                    $ok = $row->variance === 0;
                                @endphp
                                <tr class="{{ $ok ? '' : 'table-warning' }}">
                                    <td>L{{ $row->level }}</td>
                                    <td>{{ $row->unit_label }}</td>
                                    <td class="small text-muted">{{ $row->content_summary ?: '—' }}</td>
                                    <td class="text-end">{{ format_number($row->expected, 0, true) }}</td>
                                    <td class="text-end">{{ format_number($row->actual, 0, true) }}</td>
                                    <td class="text-end fw-semibold">{{ $row->variance > 0 ? '+' : '' }}{{ format_number($row->variance, 0, true) }}</td>
                                    <td>
                                        @if ($ok)
                                            <span class="badge bg-label-success">OK</span>
                                        @elseif ($row->variance > 0)
                                            <span class="badge bg-label-warning">Lebih</span>
                                        @else
                                            <span class="badge bg-label-danger">Kurang</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($summaryRows->isNotEmpty())
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Ringkasan Serial per Unit</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Level</th>
                                <th>Unit</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Ready</th>
                                <th class="text-end">Dispatched</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summaryRows as $row)
                                <tr>
                                    <td>L{{ $row->unit_level }}</td>
                                    <td>{{ $row->unit_label }}</td>
                                    <td class="text-end">{{ format_number($row->total, 0, true) }}</td>
                                    <td class="text-end">{{ format_number($row->ready, 0, true) }}</td>
                                    <td class="text-end">{{ format_number($row->dispatched, 0, true) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Unit</label>
                        <select name="unit_id" class="form-select">
                            <option value="">Semua Unit</option>
                            @foreach ($summaryRows as $row)
                                <option value="{{ $row->unit_id }}" @selected($filters['unit_id'] === $row->unit_id)>
                                    {{ $row->unit_label }} ({{ $row->total }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status Serial</label>
                        <select name="status" class="form-select">
                            <option value="all" @selected($filters['status'] === 'all')>Semua</option>
                            <option value="ready" @selected($filters['status'] === 'ready')>Ready</option>
                            <option value="dispatched" @selected($filters['status'] === 'dispatched')>Dispatched</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Per halaman</label>
                        <select name="per_page" class="form-select">
                            @foreach ([25, 50, 100, 200] as $size)
                                <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-filter me-1"></i> Filter
                        </button>
                        <a href="{{ route('production.barcodes', $order->id) }}" class="btn btn-label-secondary">Reset</a>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px;">#</th>
                            <th>Serial</th>
                            <th>Unit</th>
                            <th>Level</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($serials as $i => $serial)
                            <tr>
                                <td>{{ $serials->firstItem() + $i }}</td>
                                <td><code class="font-monospace">{{ $serial->serial_number }}</code></td>
                                <td>{{ strtoupper($serial->unit?->symbol ?: ($serial->unit?->name ?: '—')) }}</td>
                                <td>L{{ $serial->unit_level }}</td>
                                <td>
                                    @if ($serial->is_dispatched)
                                        <span class="badge bg-label-warning">Dispatched</span>
                                    @else
                                        <span class="badge bg-label-success">Ready</span>
                                    @endif
                                </td>
                                <td>{{ optional($serial->created_at)->format('d M Y H:i') ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    Belum ada barcode untuk production order ini.
                                    @if ($order->status === 'completed')
                                        Serial dikunci saat Receive — pastikan proses receive sudah dijalankan.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($serials->hasPages())
                <div class="card-footer">
                    {{ $serials->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
