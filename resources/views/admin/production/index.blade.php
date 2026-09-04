<x-app-layout>
    @section('title', 'Production Order | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        @php
            $hasCreatePermission = session('permissions.Production Order.is_create', true) == 1
                || session('permissions.Production Order.is_create', false) === true
                || true;
            $statusFilter = request('status', '');
            $isFilter = $statusFilter !== '';

            $statusMap = [
                'draft' => ['label' => 'Draft', 'tone' => 'secondary'],
                'in_progress' => ['label' => 'Process', 'tone' => 'info'],
                'pending_receiving' => ['label' => 'Pending Receiving', 'tone' => 'warning'],
                'completed' => ['label' => 'Completed', 'tone' => 'success'],
                'cancelled' => ['label' => 'Cancelled', 'tone' => 'danger'],
            ];
        @endphp

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => 'javascript:void(0);'],
                ['label' => 'Production Order', 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card">
            <div class="card-datatable text-nowrap">
                <table class="table table-bordered" id="table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">No</th>
                            <th>Production No.</th>
                            <th>Date</th>
                            <th>Finished Good</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Grand Total</th>
                            <th>Status</th>
                            <th style="width:90px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $i => $o)
                            @php
                                $outputUnit = $o->outputUnit?->symbol ?? $o->outputUnit?->name ?? '';
                                $qty = (float) $o->produced_qty > 0
                                    ? (float) $o->produced_qty
                                    : (float) $o->planned_qty;
                                $qtyLevels = $o->product && $o->output_unit_id && $qty > 0
                                    ? \App\Support\ProductionQuantityDisplay::qtyLevelBreakdown($o->product, $qty, $o->output_unit_id)
                                    : [];
                                $qtyPackaging = $o->product && $o->output_unit_id && $qty > 0
                                    ? \App\Support\ProductionQuantityDisplay::packagingBreakdown($o->product, $qty, $o->output_unit_id)
                                    : [];
                                $statusMeta = $statusMap[$o->status] ?? ['label' => ucfirst(str_replace('_', ' ', $o->status)), 'tone' => 'secondary'];
                                $materialCost = (float) ($o->total_material_cost ?? 0);
                                $overheadCost = (float) ($o->overhead_cost ?? 0);
                                $totalCost = $materialCost + $overheadCost;
                                $unitCost = (float) ($o->output_unit_cost ?? 0);
                                if ($unitCost <= 0 && $qty > 0 && $totalCost > 0) {
                                    $unitCost = round($totalCost / $qty, 4);
                                }
                                $unitCostLevels = $o->product && $o->output_unit_id && $unitCost > 0
                                    ? \App\Support\ProductionQuantityDisplay::unitCostLevelBreakdown($o->product, $unitCost, $o->output_unit_id)
                                    : [];
                                $grandTotal = $totalCost > 0
                                    ? $totalCost
                                    : ($unitCost > 0 ? $unitCost * $qty : 0);
                                $warehouseName = $o->outputWarehouse?->name ?? $o->sourceWarehouse?->name;
                            @endphp
                            <tr data-status="{{ $o->status }}">
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <a href="{{ route('production.show', $o->id) }}" class="fw-semibold text-heading">
                                        {{ $o->order_number }}
                                    </a>
                                </td>
                                <td data-order="{{ optional($o->production_date)->format('Y-m-d') }}">
                                    {{ optional($o->production_date)->format('d M Y') ?: '-' }}
                                </td>
                                <td>
                                    <div class="fw-medium">{{ $o->variant?->display_name ?? $o->product?->name }}</div>
                                    @if ($warehouseName)
                                        <small class="text-muted">{{ $warehouseName }}</small>
                                    @endif
                                </td>
                                <td class="text-end" data-order="{{ $qty }}">
                                    @if ($qty > 0)
                                        <div class="fw-semibold">
                                            {{ format_number($qty, 2, true) }}
                                            @if ($outputUnit)
                                                <small class="text-muted">{{ $outputUnit }}</small>
                                            @endif
                                        </div>
                                        @if (count($qtyLevels) > 1)
                                            <div class="small text-muted mt-1 lh-sm">
                                                @foreach ($qtyLevels as $level)
                                                    @if (! $level['is_base'])
                                                        <div>{{ format_number($level['qty'], 4, true) }} {{ $level['label'] }}</div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @elseif (count($qtyPackaging) > 1)
                                            <div class="small text-muted mt-1">
                                                {{ collect($qtyPackaging)->map(fn ($r) => format_number($r['qty'], 4, true).' '.$r['label'])->implode(' · ') }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end" data-order="{{ $unitCost }}">
                                    @if ($unitCost > 0)
                                        <div class="fw-semibold">
                                            Rp {{ format_number($unitCost, 2, true) }}
                                            @if ($outputUnit)
                                                <small class="text-muted">/ {{ $outputUnit }}</small>
                                            @endif
                                        </div>
                                        @if (count($unitCostLevels) > 1)
                                            <div class="small text-muted mt-1 lh-sm">
                                                @foreach ($unitCostLevels as $level)
                                                    @if (! $level['is_base'])
                                                        <div>Rp {{ format_number($level['unit_cost'], 2, true) }} / {{ $level['label'] }}</div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                        @if ($materialCost > 0 || $overheadCost > 0)
                                            <div class="small text-muted mt-1 lh-sm border-top pt-1">
                                                @if ($materialCost > 0)
                                                    <div>Material: Rp {{ format_number($materialCost, 2, true) }}</div>
                                                @endif
                                                @if ($overheadCost > 0)
                                                    <div>Overhead: Rp {{ format_number($overheadCost, 2, true) }}</div>
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold" data-order="{{ $grandTotal }}">
                                    @if ($grandTotal > 0)
                                        Rp {{ format_number($grandTotal, 2, true) }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-label-{{ $statusMeta['tone'] }}">{{ $statusMeta['label'] }}</span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-label="Actions">
                                            <i class="ti ti-dots-vertical text-primary"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('production.show', $o->id) }}">
                                                    <i class="ti ti-eye me-2 text-info"></i>Detail
                                                </a>
                                            </li>
                                            @if ($o->status === 'draft')
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('production.edit', $o->id) }}">
                                                        <i class="ti ti-pencil me-2 text-warning"></i>Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <form method="POST" action="{{ route('production.start', $o->id) }}" onsubmit="return confirm('Set this production order to Process?')">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="ti ti-player-play me-2 text-primary"></i>Process
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" action="{{ route('production.destroy', $o->id) }}" onsubmit="return confirm('Delete this production order?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="ti ti-trash me-2 text-danger"></i>Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            @endif
                                            @if (in_array($o->status, ['in_progress', 'pending_receiving'], true))
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('production.receive', $o->id) }}">
                                                        <i class="ti ti-package me-2 text-success"></i>Receive
                                                    </a>
                                                </li>
                                            @endif
                                            @if ($o->status === 'completed' && (float) $o->produced_qty > 0)
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('production.barcodes', $o->id) }}">
                                                        <i class="ti ti-barcode me-2 text-primary"></i>Detail Barcode
                                                        @if (($barcodeCounts[$o->id] ?? 0) > 0)
                                                            <span class="badge bg-label-primary ms-1">{{ $barcodeCounts[$o->id] }}</span>
                                                        @endif
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('production.receive.print', $o->id) }}">
                                                        <i class="ti ti-printer me-2 text-info"></i>Print Barcode
                                                    </a>
                                                </li>
                                            @elseif (($barcodeCounts[$o->id] ?? 0) > 0)
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('production.barcodes', $o->id) }}">
                                                        <i class="ti ti-barcode me-2 text-primary"></i>Detail Barcode
                                                        <span class="badge bg-label-primary ms-1">{{ $barcodeCounts[$o->id] }}</span>
                                                    </a>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-modal id="filterModal" title="Filter">
        <div class="mb-0">
            <label class="form-label" for="selectStatus">Status</label>
            <select id="selectStatus" class="select2 form-select" data-allow-clear="true">
                <option value="">All</option>
                <option value="draft" @selected($statusFilter === 'draft')>Draft</option>
                <option value="in_progress" @selected($statusFilter === 'in_progress')>Process</option>
                <option value="pending_receiving" @selected($statusFilter === 'pending_receiving')>Pending Receiving</option>
                <option value="completed" @selected($statusFilter === 'completed')>Completed</option>
                <option value="cancelled" @selected($statusFilter === 'cancelled')>Cancelled</option>
            </select>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn-label-dark" id="btnResetFilter">Reset</button>
            <button type="button" class="btn btn-primary" id="btnFilter" data-bs-dismiss="modal">Filter</button>
        </x-slot:footer>
    </x-modal>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-responsive/datatables.responsive.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons/datatables-buttons.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            $(document).ready(function () {
                var initialStatus = @json($statusFilter);

                var table = $('#table').DataTable({
                    paging: true,
                     
                    order: [[2, 'desc']],
                    columnDefs: [
                        { orderable: false, targets: [0, 8] },
                        { searchable: false, targets: [0, 8] },
                    ],
                    buttons: [
                        {
                            text: '<i class="ti ti-filter me-sm-1"></i> Filter',
                            className: 'btn {{ $isFilter ? "btn-warning" : "btn-primary" }}',
                            action: function () {
                                $('#filterModal').modal('show');
                            }
                        },
                        @if ($hasCreatePermission)
                        {
                            text: '<i class="ti ti-plus me-sm-1"></i> Create',
                            className: 'btn btn-primary',
                            action: function () {
                                window.location = '{{ route("production.create") }}';
                            }
                        }
                        @endif
                    ],
                    language: {
                        emptyTable: 'No production orders found.',
                        zeroRecords: 'No matching production orders.',
                    },
                    drawCallback: function () {
                        this.api().column(0, { search: 'applied', order: 'applied' }).nodes().each(function (cell, i) {
                            cell.innerHTML = i + 1;
                        });
                        document.querySelectorAll('#table .dropdown-toggle[data-bs-toggle="dropdown"]').forEach(function (toggle) {
                            bootstrap.Dropdown.getOrCreateInstance(toggle, {
                                popperConfig: function (defaultConfig) {
                                    return Object.assign({}, defaultConfig, { strategy: 'fixed' });
                                },
                            });
                        });
                    }
                });

                $('div.head-label').html('<h4 class="card-title mb-0">Production Order</h4>');

                $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                    if (settings.nTable.id !== 'table') {
                        return true;
                    }
                    var status = $('#selectStatus').val() || '';
                    if (!status) {
                        return true;
                    }
                    var rowStatus = table.row(dataIndex).node()?.getAttribute('data-status') || '';
                    return rowStatus === status;
                });

                if (initialStatus) {
                    table.draw();
                }

                $('#btnFilter').on('click', function () {
                    table.draw();
                    var status = $('#selectStatus').val() || '';
                    var url = new URL(window.location.href);
                    if (status) {
                        url.searchParams.set('status', status);
                    } else {
                        url.searchParams.delete('status');
                    }
                    window.history.replaceState({}, '', url);
                });

                $('#btnResetFilter').on('click', function () {
                    $('#selectStatus').val('').trigger('change');
                    table.draw();
                    var url = new URL(window.location.href);
                    url.searchParams.delete('status');
                    window.history.replaceState({}, '', url);
                    $('#filterModal').modal('hide');
                });
            });
        </script>
    @endpush
</x-app-layout>
