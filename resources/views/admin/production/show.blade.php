<x-app-layout>
    @section('title', 'Production Order Detail | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production Order', 'url' => route('production.index')],
                ['label' => $order->order_number, 'active' => true],
            ]"
        />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        @php
            $outputUnit = $order->outputUnit?->symbol ?? $order->outputUnit?->name ?? '';
            $orderQty = (float) $order->produced_qty > 0
                ? (float) $order->produced_qty
                : (float) $order->planned_qty;
            $qtyLevels = $order->product && $order->output_unit_id && $orderQty > 0
                ? \App\Support\ProductionQuantityDisplay::qtyLevelBreakdown($order->product, $orderQty, $order->output_unit_id)
                : [];
            $materialCost = (float) ($order->total_material_cost ?? 0);
            $overheadCost = (float) ($order->overhead_cost ?? 0);
            $totalCost = $materialCost + $overheadCost;
            $unitCost = (float) ($order->output_unit_cost ?? 0);
            if ($unitCost <= 0 && $orderQty > 0 && $totalCost > 0) {
                $unitCost = round($totalCost / $orderQty, 4);
            }
            $unitCostLevels = $order->product && $order->output_unit_id && $unitCost > 0
                ? \App\Support\ProductionQuantityDisplay::unitCostLevelBreakdown($order->product, $unitCost, $order->output_unit_id)
                : [];
            $statusMap = [
                'draft' => ['label' => 'Draft', 'tone' => 'secondary'],
                'in_progress' => ['label' => 'Process', 'tone' => 'info'],
                'pending_receiving' => ['label' => 'Pending Receiving', 'tone' => 'warning'],
                'completed' => ['label' => 'Completed', 'tone' => 'success'],
                'cancelled' => ['label' => 'Cancelled', 'tone' => 'danger'],
            ];
            $statusMeta = $statusMap[$order->status] ?? ['label' => ucfirst(str_replace('_', ' ', $order->status)), 'tone' => 'secondary'];
            $canProcess = $order->status === 'draft';
            $canReceive = in_array($order->status, ['in_progress', 'pending_receiving'], true);
            $canEdit = $order->status === 'draft';
            $barcodeCount = \App\Models\ProductLabelSerial::query()
                ->where('source_type', \App\Models\ProductLabelSerial::SOURCE_PRODUCTION_ORDER)
                ->where('source_id', $order->id)
                ->count();
            $canViewBarcodes = $barcodeCount > 0 || ($order->status === 'completed' && (float) $order->produced_qty > 0);
        @endphp

        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h5 class="card-title mb-0">{{ $order->order_number }}</h5>
                        <span class="badge bg-label-{{ $statusMeta['tone'] }}">{{ $statusMeta['label'] }}</span>
                    </div>
                    <div class="text-muted">
                        {{ $order->variant?->display_name ?? $order->product?->name }}
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if ($canProcess)
                        <form method="POST" action="{{ route('production.start', $order->id) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Set this production order to Process?')">
                                <i class="ti ti-player-play me-1"></i> Process
                            </button>
                        </form>
                    @endif
                    @if ($canReceive)
                        <a href="{{ route('production.receive', $order->id) }}" class="btn btn-primary">
                            <i class="ti ti-package me-1"></i> Receive
                        </a>
                    @endif
                    @if ($canViewBarcodes)
                        <a href="{{ route('production.barcodes', $order->id) }}" class="btn btn-label-primary">
                            <i class="ti ti-barcode me-1"></i> Detail Barcode
                            @if ($barcodeCount > 0)
                                <span class="badge bg-primary ms-1">{{ $barcodeCount }}</span>
                            @endif
                        </a>
                    @endif
                    @if ($order->status === 'completed' && (float) $order->produced_qty > 0)
                        <a href="{{ route('production.receive.print', $order->id) }}" class="btn btn-label-info">
                            <i class="ti ti-printer me-1"></i> Print Barcode
                        </a>
                    @endif
                    @if ($canEdit)
                        <a href="{{ route('production.edit', $order->id) }}" class="btn btn-label-warning">
                            <i class="ti ti-pencil me-1"></i> Edit
                        </a>
                    @endif
                    <a href="{{ route('production.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Production Date</small>
                        <div class="fw-medium">{{ optional($order->production_date)->format('d M Y') ?: '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">RM Warehouse</small>
                        <div class="fw-medium">{{ $order->sourceWarehouse?->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">FG Warehouse</small>
                        <div class="fw-medium">{{ $order->outputWarehouse?->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Notes</small>
                        <div class="fw-medium">{{ $order->notes ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted d-block mb-1">Qty{{ $outputUnit ? ' (' . $outputUnit . ')' : '' }}</small>
                        <h4 class="mb-1">{{ format_number($orderQty, 2, true) }} @if($outputUnit)<small class="text-muted fw-normal">{{ $outputUnit }}</small>@endif</h4>
                        @if (count($qtyLevels) > 1)
                            <div class="small text-muted lh-sm mt-2">
                                @foreach ($qtyLevels as $level)
                                    @if (! $level['is_base'])
                                        <div>{{ format_number($level['qty'], 4, true) }} {{ $level['label'] }}</div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted d-block mb-1">Unit Cost{{ $outputUnit ? ' / ' . $outputUnit : '' }}</small>
                        <h4 class="mb-1 text-primary">
                            @if ($unitCost > 0)
                                Rp {{ format_number($unitCost, 2, true) }}
                            @else
                                —
                            @endif
                        </h4>
                        @if (count($unitCostLevels) > 1)
                            <div class="small text-muted lh-sm mt-2">
                                @foreach ($unitCostLevels as $level)
                                    @if (! $level['is_base'])
                                        <div>Rp {{ format_number($level['unit_cost'], 2, true) }} / {{ $level['label'] }}</div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted d-block mb-1">Grand Total</small>
                        <h4 class="mb-1">
                            @if ($totalCost > 0)
                                Rp {{ format_number($totalCost, 2, true) }}
                            @else
                                —
                            @endif
                        </h4>
                        <div class="small text-muted lh-sm mt-2">
                            <div>Material: Rp {{ format_number($materialCost, 2, true) }}</div>
                            <div>Overhead: Rp {{ format_number($overheadCost, 2, true) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($order->overheads->isNotEmpty() || $overheadCost > 0)
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">Overhead</h6>
                    <span class="fw-semibold text-primary">Rp {{ format_number($overheadCost, 2, true) }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Description</th>
                                <th class="text-end" style="width:180px;">Amount (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($order->overheads as $overhead)
                                <tr>
                                    <td>{{ $overhead->description }}</td>
                                    <td class="text-end">Rp {{ format_number($overhead->amount, 2, true) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td>Overhead</td>
                                    <td class="text-end">Rp {{ format_number($overheadCost, 2, true) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Materials Consumed</h6>
                        @if ($order->materials->isNotEmpty())
                            <small class="text-muted">Deducted on submit (BOM × production qty)</small>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Material</th>
                                    <th class="text-end">Consumed</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($order->materials as $m)
                                    @php
                                        $materialUnit = $m->unit?->symbol ?? $m->unit?->name ?? '';
                                        $materialProduct = $m->componentVariant?->product ?? $m->componentProduct;
                                        $materialQty = (float) $m->qty_consumed;
                                        $materialConversionHint = $materialProduct && $m->unit_id
                                            ? \App\Support\ProductionQuantityDisplay::conversionSummary($materialProduct, $materialQty, $m->unit_id)
                                            : null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-medium">{{ $m->componentVariant?->display_name ?? $m->componentProduct?->name }}</div>
                                            @if ($materialConversionHint)
                                                <small class="text-muted">{{ $materialConversionHint }}</small>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            {{ format_number($materialQty, 4, true) }}
                                            @if ($materialUnit)<small class="text-muted">{{ $materialUnit }}</small>@endif
                                        </td>
                                        <td class="text-end">Rp {{ format_number($m->unit_cost, 2, true) }}</td>
                                        <td class="text-end">Rp {{ format_number($m->total_cost, 2, true) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">No materials consumed yet.</td></tr>
                                @endforelse
                            </tbody>
                            @if ($order->materials->count())
                                <tfoot class="table-light">
                                    <tr class="fw-bold">
                                        <td colspan="3" class="text-end">Total Material Cost</td>
                                        <td class="text-end">Rp {{ format_number($order->total_material_cost, 2, true) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Receive</h6>
                        @if ($order->outputs->isEmpty() && $canReceive)
                            <small class="text-muted">Complete Receive to add finished goods to warehouse</small>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($order->outputs as $o)
                                    @php
                                        $producedUnit = $o->unit?->symbol ?? $o->unit?->name ?? $outputUnit;
                                        $outputProduct = $o->variant?->product ?? $order->product;
                                        $producedQty = (float) $o->qty_produced;
                                        $outputUnitId = $o->unit_id ?: $order->output_unit_id;
                                        $outputConversionHint = $outputProduct && $outputUnitId
                                            ? \App\Support\ProductionQuantityDisplay::conversionSummary($outputProduct, $producedQty, $outputUnitId)
                                            : null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-medium">{{ $o->variant?->display_name ?? $o->product?->name }}</div>
                                            @if ($outputConversionHint)
                                                <small class="text-muted">{{ $outputConversionHint }}</small>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            {{ format_number($producedQty, 2, true) }}
                                            @if ($producedUnit)<small class="text-muted">{{ $producedUnit }}</small>@endif
                                        </td>
                                        <td class="text-end text-primary">Rp {{ format_number($o->unit_cost, 2, true) }}</td>
                                        <td class="text-end">Rp {{ format_number($o->total_cost, 2, true) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            @if ($canReceive)
                                                Not received yet.
                                                <div class="mt-2">
                                                    <a href="{{ route('production.receive', $order->id) }}" class="btn btn-sm btn-primary">
                                                        <i class="ti ti-package me-1"></i> Receive Now
                                                    </a>
                                                </div>
                                            @else
                                                No output recorded.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
