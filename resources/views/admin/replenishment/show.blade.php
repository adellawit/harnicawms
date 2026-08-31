<x-app-layout>
    @section('title', 'Detail Pesanan | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Distribusi'],
                ['label' => 'Replenishment Order', 'url' => route('replenishment.index')],
                ['label' => $order->order_number, 'active' => true],
            ]"
        />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        @php $map = ['draft'=>'secondary','submitted'=>'info','approved'=>'primary','shipped'=>'warning','partially_received'=>'warning','received'=>'success','cancelled'=>'danger']; @endphp
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><small class="text-muted">No. Order</small><div class="fw-medium">{{ $order->order_number }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Distributor</small><div class="fw-medium">{{ $order->distributor?->name }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Agent</small><div class="fw-medium">{{ $order->agent?->code }} - {{ $order->agent?->name }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Status</small><div><span class="badge bg-label-{{ $map[$order->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$order->status)) }}</span></div></div>
                    <div class="col-md-3"><small class="text-muted">Invoice</small><div class="fw-medium">{{ $order->invoice_number ?: '-' }}</div></div>
                    <div class="col-md-3"><small class="text-muted">Pembayaran</small><div class="fw-medium">{{ ucfirst($order->payment_status ?? 'unpaid') }} @if($order->payment_reference) · {{ $order->payment_reference }} @endif</div></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h6 class="card-title mb-0">Item Pesanan</h6></div>
            <div class="table-responsive text-nowrap">
                <table class="table">
                    <thead><tr><th>Produk</th><th class="text-end">Dipesan</th><th class="text-end">Dikirim</th><th class="text-end">Diterima</th><th class="text-end">Diretur</th><th class="text-end">Harga</th></tr></thead>
                    <tbody>
                        @foreach ($order->items as $it)
                            <tr>
                                <td>{{ $it->variant?->display_name ?? $it->product?->name }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($it->qty_ordered,2),'0'),'.') }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($it->qty_shipped,2),'0'),'.') }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($it->qty_received,2),'0'),'.') }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($it->qty_returned,2),'0'),'.') }}</td>
                                <td class="text-end">Rp {{ number_format($it->unit_price,2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row">
            {{-- Pengiriman --}}
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h6 class="card-title mb-0"><i class="ti ti-truck-delivery me-1"></i> Pengiriman</h6></div>
                    <div class="card-body">
                        @if (in_array($order->status, ['submitted','approved','shipped','partially_received']))
                        <form method="POST" action="{{ route('replenishment.ship', $order->id) }}" class="mb-4">
                            @csrf
                            <div class="row g-2 mb-2">
                                <div class="col-4"><input type="text" name="carrier" class="form-control form-control-sm" placeholder="Kurir *" required></div>
                                <div class="col-4"><input type="text" name="tracking_number" class="form-control form-control-sm" placeholder="No. Resi *" required></div>
                                <div class="col-4"><input type="date" name="ship_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}"></div>
                            </div>
                            <table class="table table-sm">
                                <thead><tr><th>Produk</th><th class="text-end">Sisa Kirim</th><th>Qty Kirim</th></tr></thead>
                                <tbody>
                                    @foreach ($order->items as $it)
                                        @php $sisa = (float)$it->qty_ordered - (float)$it->qty_shipped; @endphp
                                        <tr>
                                            <td>{{ $it->variant?->display_name ?? $it->product?->name }}</td>
                                            <td class="text-end">{{ rtrim(rtrim(number_format($sisa,2),'0'),'.') }}</td>
                                            <td><input type="number" step="any" min="0" max="{{ $sisa }}" name="qty[{{ $it->id }}]" class="form-control form-control-sm" value="{{ $sisa > 0 ? $sisa : 0 }}"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <button class="btn btn-warning btn-sm"><i class="ti ti-send me-1"></i> Kirim (stok Distributor keluar, FIFO)</button>
                        </form>
                        @endif

                        <h6 class="mt-2">Riwayat Pengiriman</h6>
                        @forelse ($order->shipments as $sh)
                            <div class="border rounded p-2 mb-2">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="fw-medium">{{ $sh->shipment_number }}</span>
                                        <span class="badge bg-label-{{ $sh->status === 'delivered' ? 'success' : 'warning' }}">{{ $sh->status === 'delivered' ? 'Diterima' : 'Dalam Perjalanan' }}</span>
                                        <div class="small text-muted mt-1">{{ optional($sh->ship_date)->format('d/m/Y') }} · Kurir: {{ $sh->carrier ?? '-' }}</div>
                                        <div class="mt-1">
                                            <span class="badge bg-primary"><i class="ti ti-barcode me-1"></i> Resi: {{ $sh->tracking_number ?? '-' }}</span>
                                        </div>
                                    </div>
                                    @if ($sh->status !== 'delivered')
                                    <form method="POST" action="{{ route('replenishment.receive', [$order->id, $sh->id]) }}" onsubmit="return confirm('Terima barang ini di Agen?')">
                                        @csrf
                                        <button class="btn btn-success btn-sm"><i class="ti ti-truck-loading me-1"></i> Terima</button>
                                    </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">Belum ada pengiriman.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Penerimaan & Retur --}}
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h6 class="card-title mb-0"><i class="ti ti-arrow-back-up me-1"></i> Penerimaan & Retur</h6></div>
                    <div class="card-body">
                        <h6>Riwayat Penerimaan</h6>
                        @forelse ($order->receipts as $rc)
                            <div class="border rounded p-2 mb-2">
                                <span class="fw-medium">{{ $rc->receipt_number }}</span>
                                <span class="small text-muted">· {{ optional($rc->receive_date)->format('d/m/Y') }} · {{ $rc->items->count() }} item</span>
                            </div>
                        @empty
                            <p class="text-muted small">Belum ada penerimaan.</p>
                        @endforelse

                        @php $canReturn = $order->items->sum(fn($i) => (float)$i->qty_received - (float)$i->qty_returned) > 0; @endphp
                        @if ($canReturn)
                        <hr>
                        <h6>Buat Retur</h6>
                        <form method="POST" action="{{ route('replenishment.return', $order->id) }}">
                            @csrf
                            <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Alasan retur">
                            <table class="table table-sm">
                                <thead><tr><th>Produk</th><th class="text-end">Bisa Retur</th><th>Qty Retur</th></tr></thead>
                                <tbody>
                                    @foreach ($order->items as $it)
                                        @php $bisa = (float)$it->qty_received - (float)$it->qty_returned; @endphp
                                        @if ($bisa > 0)
                                        <tr>
                                            <td>{{ $it->variant?->display_name ?? $it->product?->name }}</td>
                                            <td class="text-end">{{ rtrim(rtrim(number_format($bisa,2),'0'),'.') }}</td>
                                            <td><input type="number" step="any" min="0" max="{{ $bisa }}" name="qty[{{ $it->id }}]" class="form-control form-control-sm" value="0"></td>
                                        </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                            <button class="btn btn-outline-danger btn-sm" onclick="return confirm('Proses retur?')"><i class="ti ti-arrow-back-up me-1"></i> Proses Retur</button>
                        </form>
                        @endif

                        @if ($order->returns->count())
                        <hr>
                        <h6>Riwayat Retur</h6>
                        @foreach ($order->returns as $rt)
                            <div class="border rounded p-2 mb-2">
                                <span class="fw-medium">{{ $rt->return_number }}</span>
                                <span class="small text-muted">· {{ optional($rt->return_date)->format('d/m/Y') }} · {{ $rt->reason }}</span>
                            </div>
                        @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('replenishment.index') }}" class="btn btn-outline-secondary">Kembali</a>
    </div>
</x-app-layout>
