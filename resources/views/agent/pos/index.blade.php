@extends('layouts.agent-order')

@section('title', 'POS Agen | ')

@section('content')
    <header class="shop-page-header mb-3">
        <div class="small text-muted text-uppercase">Portal Agen · POS</div>
        <h1 class="shop-page-title mb-1">POS Agen</h1>
        <p class="text-muted small mb-0">Penjualan ke reseller dari gudang agen. (UI penuh — Slice 2.)</p>
    </header>

    @if (request('payment'))
        <div class="alert alert-{{ request('payment') === 'success' ? 'success' : (request('payment') === 'pending' ? 'warning' : 'danger') }} mb-3">
            Pembayaran: {{ request('payment') }}
            @if (request('order_id'))
                · Order {{ request('order_id') }}
            @endif
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Produk</div>
                    <div class="fs-4 fw-bold">{{ $products->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Reseller aktif</div>
                    <div class="fs-4 fw-bold">{{ $resellers->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Gudang agen</div>
                    <div class="small fw-semibold text-break">{{ $agentWarehouseId ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white">
            <h2 class="h6 mb-0">Reseller (buyer)</h2>
        </div>
        <ul class="list-group list-group-flush">
            @forelse ($resellers as $reseller)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>{{ $reseller->name ?: $reseller->customer?->name }}</span>
                    <code class="small">{{ $reseller->customer_id }}</code>
                </li>
            @empty
                <li class="list-group-item text-muted">Belum ada reseller dengan akun customer.</li>
            @endforelse
        </ul>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h6 mb-0">Uji bayar tunai (manual API)</h2>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3">Form minimal untuk smoke test endpoint <code>POST /agent-order/pos/payment</code>. Pilih reseller &amp; metode tunai, lalu isi variant dari katalog.</p>
            <form id="agentPosSmokeForm" class="row g-2">
                @csrf
                <input type="hidden" name="price_list_id" value="{{ $defaultPriceListId }}">
                <input type="hidden" name="tax_rate" value="0">
                <input type="hidden" name="tax_enabled" value="0">
                <div class="col-md-6">
                    <label class="form-label small">Reseller (customer_id)</label>
                    <select name="customer_id" class="form-select form-select-sm" required>
                        <option value="">— Pilih —</option>
                        @foreach ($resellers as $reseller)
                            <option value="{{ $reseller->customer_id }}">{{ $reseller->name ?: $reseller->customer?->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Metode bayar</label>
                    <select name="payment_method_id" class="form-select form-select-sm" required>
                        @foreach ($methodPayments as $mp)
                            <option value="{{ $mp->id }}">{{ $mp->name }} ({{ $mp->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Variant ID</label>
                    <input type="text" name="items[0][variant_id]" class="form-control form-control-sm" placeholder="uuid variant" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Unit ID</label>
                    <input type="text" name="items[0][unit_id]" class="form-control form-control-sm" placeholder="uuid unit" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Qty</label>
                    <input type="number" name="items[0][quantity]" class="form-control form-control-sm" value="1" min="0.000001" step="any" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Harga</label>
                    <input type="number" name="items[0][unit_price]" class="form-control form-control-sm" value="0" min="0" step="any" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Amount paid</label>
                    <input type="number" name="amount_paid" class="form-control form-control-sm" value="999999999" min="0" step="any" required>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm">Kirim pembayaran tunai</button>
                </div>
            </form>
            <pre id="agentPosSmokeResult" class="small bg-light p-2 mt-3 mb-0 d-none"></pre>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('agentPosSmokeForm')?.addEventListener('submit', async function (e) {
            e.preventDefault();
            const form = e.target;
            const fd = new FormData(form);
            const payload = {
                price_list_id: fd.get('price_list_id'),
                payment_method_id: fd.get('payment_method_id'),
                customer_id: fd.get('customer_id') || null,
                tax_rate: 0,
                tax_enabled: false,
                amount_paid: parseFloat(fd.get('amount_paid') || '0'),
                items: [{
                    variant_id: fd.get('items[0][variant_id]'),
                    unit_id: fd.get('items[0][unit_id]'),
                    quantity: parseFloat(fd.get('items[0][quantity]') || '1'),
                    unit_price: parseFloat(fd.get('items[0][unit_price]') || '0'),
                }],
            };
            const out = document.getElementById('agentPosSmokeResult');
            out.classList.remove('d-none');
            out.textContent = 'Loading...';
            try {
                const res = await fetch(@json(route('agent-order.pos.payment')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token()),
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                out.textContent = JSON.stringify(data, null, 2);
            } catch (err) {
                out.textContent = String(err);
            }
        });
    </script>
@endpush
