<x-app-layout>
    @section('title', 'Terima Hasil Produksi | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production', 'url' => route('production.index')],
                ['label' => 'Production In-House', 'url' => route('production.index')],
                ['label' => $order->order_number, 'url' => route('production.show', $order->id)],
                ['label' => 'Terima Hasil', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        @php
            $outputUnit = $order->outputUnit?->symbol ?? $order->outputUnit?->name ?? '';
            $bomItems = $order->bom?->items ?? collect();
            $outputPerBatch = (float) ($order->bom?->output_quantity ?: 1);
            $plannedScale = $outputPerBatch > 0 ? (float) $order->planned_qty / $outputPerBatch : (float) $order->planned_qty;
        @endphp

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><small class="text-muted">Produk Jadi</small><div class="fw-medium">{{ $order->variant?->display_name ?? $order->product?->name }}</div></div>
                    <div class="col-md-4"><small class="text-muted">Qty Rencana</small><div class="fw-medium">{{ rtrim(rtrim(number_format((float) $order->planned_qty, 4), '0'), '.') }} {{ $outputUnit }}</div></div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('production.receive.store', $order->id) }}" onsubmit="return confirm('Kirim hasil produksi? Bahan baku akan dipotong dan stok produk jadi bertambah. Tindakan ini final dan tidak bisa diedit.')">
            @csrf
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Qty Aktual Produksi</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Qty Aktual <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="actual_qty" id="actualQty" class="form-control" value="{{ old('actual_qty', (float) $order->planned_qty) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Satuan <span class="text-danger">*</span></label>
                            <select name="actual_unit_id" id="actualUnitId" class="form-select" required>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit['id'] }}" @selected(old('actual_unit_id', $order->output_unit_id) === $unit['id'])>
                                        {{ $unit['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expired Produk Jadi <span class="text-muted small">(FEFO)</span></label>
                            <input type="date" name="output_expiry_date" class="form-control" value="{{ old('output_expiry_date') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Kebutuhan Bahan Baku</h5></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Bahan</th>
                                <th class="text-end">Qty Rencana</th>
                                <th class="text-end">Qty Aktual Terpakai</th>
                                <th class="text-end">Sisa/Hemat</th>
                            </tr>
                        </thead>
                        <tbody id="materialRows">
                            @foreach ($bomItems as $item)
                                @php
                                    $unitLabel = $item->unit?->symbol ?? $item->unit?->name ?? '';
                                    $componentProduct = $item->componentVariant?->product ?? $item->componentProduct;
                                    $isSmallestUnit = $componentProduct && $item->unit_id === $componentProduct->getSmallestUnitId();
                                    $expected = \App\Support\ProductionQuantityNormalizer::snapDisplayQty((float) $item->quantity * $plannedScale, $isSmallestUnit);
                                @endphp
                                <tr
                                    data-per-batch-qty="{{ (float) $item->quantity }}"
                                    data-expected="{{ $expected }}"
                                    data-unit="{{ $unitLabel }}"
                                    data-is-smallest="{{ $isSmallestUnit ? 1 : 0 }}"
                                >
                                    <td>{{ $item->componentVariant?->display_name ?? $item->componentProduct?->name }}</td>
                                    <td class="text-end expected-cell">{{ rtrim(rtrim(number_format($expected, 4), '0'), '.') }} {{ $unitLabel }}</td>
                                    <td class="text-end actual-cell">-</td>
                                    <td class="text-end sisa-cell">-</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Kirim & Terima</button>
            <a href="{{ route('production.show', $order->id) }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>

    @push('page-js')
    <script>
        const outputPerBatch = {{ $outputPerBatch }};
        const unitFactors = @json($unitFactors);

        function snapQty(n, isSmallestUnit) {
            if (isSmallestUnit) {
                return Math.round(n);
            }
            const r = Math.round(n);
            return Math.abs(n - r) < 0.001 ? r : n;
        }

        function formatQty(n, isSmallestUnit) {
            return (+snapQty(n, isSmallestUnit)).toFixed(4).replace(/\.?0+$/, '');
        }

        function recalc() {
            const actualQty = parseFloat(document.getElementById('actualQty').value || '0');
            const selectedUnitId = document.getElementById('actualUnitId').value;
            const factor = unitFactors[selectedUnitId] ?? 1;
            const actualQtyInOutputUnit = actualQty * factor;
            const actualScale = outputPerBatch > 0 ? actualQtyInOutputUnit / outputPerBatch : actualQtyInOutputUnit;

            document.querySelectorAll('#materialRows tr').forEach(function (tr) {
                const perBatchQty = parseFloat(tr.dataset.perBatchQty || '0');
                const expected = parseFloat(tr.dataset.expected || '0');
                const unit = tr.dataset.unit || '';
                const isSmallestUnit = tr.dataset.isSmallest === '1';
                const actualUsed = snapQty(perBatchQty * actualScale, isSmallestUnit);
                const sisa = snapQty(expected - actualUsed, isSmallestUnit);

                tr.querySelector('.actual-cell').textContent = formatQty(actualUsed, isSmallestUnit) + (unit ? ' ' + unit : '');
                const sisaCell = tr.querySelector('.sisa-cell');
                sisaCell.textContent = formatQty(sisa, isSmallestUnit) + (unit ? ' ' + unit : '');
                sisaCell.classList.toggle('text-success', sisa > 0);
                sisaCell.classList.toggle('text-danger', sisa < 0);
            });
        }

        document.getElementById('actualQty')?.addEventListener('input', recalc);
        document.getElementById('actualUnitId')?.addEventListener('change', recalc);
        recalc();
    </script>
    @endpush
</x-app-layout>
