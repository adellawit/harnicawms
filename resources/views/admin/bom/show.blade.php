<x-app-layout>
    @section('title', 'BOM Detail | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Production', 'url' => route('production.index')],
                ['label' => 'Bill of Materials', 'url' => route('bom.index')],
                ['label' => $bom->name, 'active' => true],
            ]"
        />

        @php
            $totalOld = collect($itemCosts)->sum('old_line_total');
            $totalNew = collect($itemCosts)->sum('line_total');
            $totalDiff = $totalNew - $totalOld;
        @endphp

        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="card-title mb-1">{{ $bom->name }}</h5>
                    <small class="text-muted">Bill of Materials detail</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('production.create') }}" class="btn btn-sm btn-primary">
                        <i class="ti ti-tool me-1"></i>Create Production Order
                    </a>
                    <a href="{{ route('bom.edit', $bom->id) }}" class="btn btn-sm btn-outline-warning">
                        <i class="ti ti-pencil me-1"></i>Edit
                    </a>
                    <a href="{{ route('bom.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Recipe Name</small>
                        <strong>{{ $bom->name }}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Finished Good</small>
                        <strong>{{ $bom->variant?->display_name ?? $bom->product?->name }}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Output Qty</small>
                        <strong>
                            {{ rtrim(rtrim(number_format($bom->output_quantity, 4), '0'), '.') }}
                            {{ $bom->outputUnit?->symbol ?? $bom->outputUnit?->name ?? '-' }}
                        </strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Components</small>
                        <strong>{{ $bom->items->count() }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card h-100 border">
                    <div class="card-body">
                        <small class="text-muted d-block mb-1">Total HPP Old</small>
                        <h4 class="mb-1 text-muted">Rp {{ number_format($totalOld, 2, ',', '.') }}</h4>
                        <small class="text-muted">Baseline saved on this BOM</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border border-primary">
                    <div class="card-body">
                        <small class="text-muted d-block mb-1">Total HPP New</small>
                        <h4 class="mb-1 text-primary">Rp {{ number_format($totalNew, 2, ',', '.') }}</h4>
                        <small class="text-muted">Current FIFO cost from stock</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border">
                    <div class="card-body">
                        <small class="text-muted d-block mb-1">Difference (New − Old)</small>
                        <h4 class="mb-1 {{ $totalDiff > 0 ? 'text-danger' : ($totalDiff < 0 ? 'text-success' : '') }}">
                            {{ $totalDiff >= 0 ? '+' : '' }}Rp {{ number_format($totalDiff, 2, ',', '.') }}
                        </h4>
                        <small class="text-muted">
                            @if ($totalDiff > 0)
                                Material cost increased
                            @elseif ($totalDiff < 0)
                                Material cost decreased
                            @else
                                No cost change
                            @endif
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-1">Components / Raw Materials</h5>
                <small class="text-muted">
                    <strong>HPP Old</strong> = baseline on BOM ·
                    <strong>HPP New</strong> = current FIFO unit cost
                </small>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Component</th>
                            <th class="text-end">Qty per Output</th>
                            <th>Unit</th>
                            <th class="text-end">HPP Old</th>
                            <th class="text-end">HPP New</th>
                            <th class="text-end">Diff</th>
                            <th class="text-end">Line Total (New)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bom->items as $item)
                            @php
                                $cost = $itemCosts[$item->id] ?? [
                                    'old_unit_cost' => 0,
                                    'unit_cost' => 0,
                                    'old_line_total' => 0,
                                    'line_total' => 0,
                                ];
                                $unitDiff = (float) $cost['unit_cost'] - (float) $cost['old_unit_cost'];
                            @endphp
                            <tr>
                                <td>{{ $item->componentVariant?->display_name ?? $item->componentProduct?->name }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($item->quantity, 4), '0'), '.') }}</td>
                                <td>{{ $item->unit?->symbol ?: ($item->unit?->name ?? $item->unit?->code) }}</td>
                                <td class="text-end text-muted">
                                    @if ($cost['old_unit_cost'] > 0)
                                        Rp {{ number_format($cost['old_unit_cost'], 2, ',', '.') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold text-primary">
                                    @if ($cost['unit_cost'] > 0)
                                        Rp {{ number_format($cost['unit_cost'], 2, ',', '.') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end {{ $unitDiff > 0 ? 'text-danger' : ($unitDiff < 0 ? 'text-success' : 'text-muted') }}">
                                    @if ($cost['old_unit_cost'] > 0 || $cost['unit_cost'] > 0)
                                        {{ $unitDiff >= 0 ? '+' : '' }}Rp {{ number_format($unitDiff, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">
                                    @if ($cost['line_total'] > 0)
                                        Rp {{ number_format($cost['line_total'], 2, ',', '.') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="3" class="text-end">
                                Total per
                                {{ rtrim(rtrim(number_format($bom->output_quantity, 4), '0'), '.') }}
                                {{ $bom->outputUnit?->symbol ?? $bom->outputUnit?->name ?? 'output' }}
                            </td>
                            <td class="text-end text-muted">Rp {{ number_format($totalOld, 2, ',', '.') }}</td>
                            <td class="text-end text-primary">Rp {{ number_format($totalNew, 2, ',', '.') }}</td>
                            <td class="text-end {{ $totalDiff > 0 ? 'text-danger' : ($totalDiff < 0 ? 'text-success' : 'text-muted') }}">
                                {{ $totalDiff >= 0 ? '+' : '' }}Rp {{ number_format($totalDiff, 2, ',', '.') }}
                            </td>
                            <td class="text-end text-primary">Rp {{ number_format($totalNew, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if ($totalNew == 0 && $totalOld == 0)
                <div class="card-footer text-muted small">
                    <i class="ti ti-info-circle me-1"></i>
                    Cost is unavailable — ensure raw materials have inbound stock layers in the material warehouse.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
