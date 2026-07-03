<x-app-layout>
    @section('title', 'Detail Purchase Order | ')
    @push('page-css')
        <style>
            .detail-card { border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; background-color: #f8f9fa; }
            .progress-bar-receive { height: 6px; border-radius: 3px; }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => 'javascript:void(0);'],
                ['label' => 'Purchase Order', 'url' => route('product.purchase-order.index.view')],
                ['label' => 'Detail', 'active' => true]
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Purchase Order - {{ $purchase->purchase_number }}</h5>
                <div>
                    @php
                        $poStatus = $purchase->status_key ?? $purchase->status;
                        $isMasterPo = $purchase->isMaster();
                        $canReceive = \App\Services\PurchaseOrderHierarchyService::canReceive($purchase)
                            && in_array($poStatus, ['process', 'receiving'])
                            && !$purchase->trashed();
                        $canEdit = \App\Services\PurchaseOrderHierarchyService::isEditable($purchase);
                        $hasRemaining = $purchase->items->contains(fn ($item) => $item->quantity_remaining > 0);
                        $canCreateSub = $isMasterPo
                            && !$purchase->trashed()
                            && \App\Services\PurchaseOrderHierarchyService::masterHasRemainingRelease($purchase);
                    @endphp

                    <button type="button" class="btn btn-primary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#pdfModal">
                        <i class="ti ti-printer me-1"></i>Print
                    </button>

                    @if($canCreateSub)
                        <a href="{{ route('product.purchase-order.insert.view', ['po_kind' => 'sub', 'parent_id' => $purchase->id]) }}" class="btn btn-info btn-sm me-1">
                            <i class="ti ti-git-branch me-1"></i>Buat Sub-PO
                        </a>
                    @endif

                    @if($canReceive && $hasRemaining)
                        <a href="{{ route('product.purchase-order.receive.view', $purchase->id) }}" class="btn btn-success btn-sm me-1">
                            <i class="ti ti-package me-1"></i>Receive
                        </a>
                    @endif

                    @if($canEdit)
                        <a href="{{ route('product.purchase-order.edit.view', $purchase->id) }}" class="btn btn-warning btn-sm me-1">
                            <i class="ti ti-pencil me-1"></i>Edit
                        </a>
                        <button type="button" class="btn btn-danger btn-sm me-1" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="ti ti-trash me-1"></i>Delete
                        </button>
                    @elseif($poStatus === 'draft' && !$purchase->trashed() && $isMasterPo)
                        <button type="button" class="btn btn-danger btn-sm me-1" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="ti ti-trash me-1"></i>Delete
                        </button>
                    @endif
                    <a href="{{ route('product.purchase-order.index.view') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td width="40%"><strong>Tipe PO:</strong></td>
                                <td>
                                    @php
                                        $kindColor = match($purchase->po_kind ?? 'standalone') {
                                            'master' => 'primary',
                                            'sub' => 'info',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <x-badge :color="$kindColor">{{ $purchase->po_kind_label }}</x-badge>
                                    @if($isMasterPo && $purchase->release_status)
                                        <span class="ms-1 text-muted">({{ $purchase->release_status_label }})</span>
                                    @endif
                                </td>
                            </tr>
                            @if($purchase->parent)
                            <tr>
                                <td><strong>PO Utama:</strong></td>
                                <td>
                                    <a href="{{ route('product.purchase-order.detail.view', $purchase->parent_id) }}">
                                        {{ $purchase->parent->purchase_number }}
                                    </a>
                                </td>
                            </tr>
                            @endif
                            <tr>
                                <td width="40%"><strong>Purchase Number:</strong></td>
                                <td>{{ $purchase->purchase_number }}</td>
                            </tr>
                            <tr>
                                <td><strong>Purchase Date:</strong></td>
                                <td>{{ $purchase->purchase_date ? $purchase->purchase_date->format('d M Y') : '-' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Expected Delivery:</strong></td>
                                <td>{{ $purchase->expected_delivery_date ? $purchase->expected_delivery_date->format('d M Y') : '-' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    @if($purchase->trashed())
                                        <x-badge color="danger">Deleted</x-badge>
                                    @else
                                        <x-badge :color="in_array($poStatus, ['draft']) ? 'secondary' : (in_array($poStatus, ['received']) ? 'success' : (in_array($poStatus, ['receiving']) ? 'info' : 'warning'))">{{ $purchase->status_label }}</x-badge>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Supplier:</strong></td>
                                <td>{{ $purchase->supplier_name }}</td>
                            </tr>
                            @if($purchase->supplier_contact)
                            <tr>
                                <td><strong>Supplier Contact:</strong></td>
                                <td>{{ $purchase->supplier_contact }}</td>
                            </tr>
                            @endif
                            @if($purchase->supplier_address)
                            <tr>
                                <td><strong>Supplier Address:</strong></td>
                                <td>{{ $purchase->supplier_address }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td width="40%"><strong>Subtotal:</strong></td>
                                <td>{{ format_number($purchase->subtotal, 2, true) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Tax Amount:</strong></td>
                                <td>{{ format_number($purchase->tax_amount, 2, true) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Discount Amount:</strong></td>
                                <td>{{ format_number($purchase->discount_amount, 2, true) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Total:</strong></td>
                                <td><strong class="text-primary">{{ format_number($purchase->total, 2, true) }}</strong></td>
                            </tr>
                        </table>
                        @if($purchase->notes)
                        <p class="mb-0"><strong>Notes:</strong> {{ $purchase->notes }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($isMasterPo && $masterReleaseSummary)
        <div class="card mb-4">
            <h5 class="card-header">Release Summary (PO Utama)</h5>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Unit</th>
                                <th class="text-end">Committed</th>
                                <th class="text-end">Allocated</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Sisa Release</th>
                                <th class="text-end">Belum Diterima</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($masterReleaseSummary as $row)
                            <tr>
                                <td>{{ $row['product_name'] ?? '-' }}{{ !empty($row['product_code']) ? ' (' . $row['product_code'] . ')' : '' }}</td>
                                <td>{{ $row['unit_label'] ?? '-' }}</td>
                                <td class="text-end">{{ format_number($row['committed_qty'], 2, true) }}</td>
                                <td class="text-end">{{ format_number($row['allocated_qty'] ?? $row['released_qty'], 2, true) }}</td>
                                <td class="text-end">{{ format_number($row['received_qty'] ?? 0, 2, true) }}</td>
                                <td class="text-end {{ ($row['remaining_qty'] ?? 0) > 0 ? 'text-warning fw-semibold' : 'text-success' }}">
                                    {{ format_number($row['remaining_qty'], 2, true) }}
                                </td>
                                <td class="text-end {{ ($row['unfulfilled_qty'] ?? 0) > 0 ? 'text-info' : 'text-success' }}">
                                    {{ format_number($row['unfulfilled_qty'] ?? 0, 2, true) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        @if($isMasterPo && $purchase->children->count() > 0)
        <div class="card mb-4">
            <h5 class="card-header">Sub-PO</h5>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Sub-PO Number</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchase->children as $ci => $child)
                            <tr>
                                <td>{{ $ci + 1 }}</td>
                                <td>{{ $child->purchase_number }}</td>
                                <td>{{ $child->purchase_date?->format('d M Y') }}</td>
                                <td><x-badge :color="in_array($child->status_key ?? $child->status, ['draft']) ? 'secondary' : 'warning'">{{ $child->status_label }}</x-badge></td>
                                <td class="text-end">{{ format_number($child->total, 2, true) }}</td>
                                <td class="text-center">
                                    <a href="{{ route('product.purchase-order.detail.view', $child->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Items Table with Receive Progress --}}
        <div class="card mb-4">
            <h5 class="card-header">Items</h5>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Product</th>
                                <th>Variant</th>
                                <th>Unit</th>
                                <th class="text-end">Ordered</th>
                                <th class="text-end">Received</th>
                                <th class="text-center">Progress</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchase->items as $i => $item)
                            @php
                                $ordered = (float) $item->quantity;
                                $received = (float) $item->quantity_received;
                                $pct = $ordered > 0 ? min(100, round($received / $ordered * 100)) : 0;
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $item->product?->name ?? '-' }}{{ $item->product?->code ? ' (' . $item->product->code . ')' : '' }}</td>
                                <td>
                                    @if($item->variant)
                                        {{ $item->variant->sku }}
                                        @php
                                            $variantAttrs = $item->variant->variantAttributes->map(fn($va) => ($va->attributeDefinition->name ?? '') . ': ' . ($va->attributeValue->value ?? ''))->join(', ');
                                        @endphp
                                        @if($variantAttrs)
                                            <br><small class="text-muted">{{ $variantAttrs }}</small>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $item->unit ? ($item->unit->symbol ?: $item->unit->name) : '-' }}</td>
                                <td class="text-end">{{ format_number($ordered, 2, true) }}</td>
                                <td class="text-end">
                                    <span class="{{ $pct >= 100 ? 'text-success fw-bold' : ($received > 0 ? 'text-info' : '') }}">
                                        {{ format_number($received, 2, true) }}
                                    </span>
                                </td>
                                <td class="text-center" style="min-width: 100px;">
                                    <div class="progress progress-bar-receive">
                                        <div class="progress-bar bg-{{ $pct >= 100 ? 'success' : ($pct > 0 ? 'info' : 'secondary') }}" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <small class="text-muted">{{ $pct }}%</small>
                                </td>
                                <td class="text-end">{{ format_number($item->unit_price, 2, true) }}</td>
                                <td class="text-end">{{ format_number($item->subtotal, 2, true) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Receive History --}}
        @if($purchase->receives->count() > 0)
        <div class="card">
            <h5 class="card-header">Receive History</h5>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Receive Number</th>
                                <th>Date</th>
                                <th>Items Received</th>
                                <th>By</th>
                                <th>Notes</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchase->receives as $ri => $rcv)
                            <tr>
                                <td>{{ $ri + 1 }}</td>
                                <td>
                                    <a href="{{ route('product.purchase-order.receive-detail.view', $rcv->id) }}">
                                        {{ $rcv->receive_number }}
                                    </a>
                                </td>
                                <td>{{ $rcv->receive_date?->format('d M Y') }}</td>
                                <td>
                                    {{ $rcv->items->count() }} item(s),
                                    total qty: {{ format_number($rcv->items->sum('quantity_received'), 2, true) }}
                                </td>
                                <td>{{ $rcv->createdByUser ? ($rcv->createdByUser->first_name . ' ' . $rcv->createdByUser->last_name) : '-' }}</td>
                                <td>{{ $rcv->notes ?? '-' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('product.purchase-order.receive-detail.view', $rcv->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfModalLabel">Print Purchase Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Atur tampilan dokumen sebelum mencetak <strong>{{ $purchase->purchase_number }}</strong>.</p>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="showPricesToggle" checked>
                        <label class="form-check-label" for="showPricesToggle">Tampilkan kolom harga</label>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Jika dimatikan, kolom harga satuan, subtotal, dan ringkasan total tidak akan ditampilkan di PDF.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnDownloadPdf">
                        <i class="ti ti-printer me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('page-js')
    <script>
        document.getElementById('btnDownloadPdf')?.addEventListener('click', function () {
            const showPrices = document.getElementById('showPricesToggle').checked ? 1 : 0;
            const url = @json(route('product.purchase-order.pdf', $purchase->id)) + '?show_prices=' + showPrices;
            window.open(url, '_blank');
            bootstrap.Modal.getInstance(document.getElementById('pdfModal'))?.hide();
        });
    </script>
    @endpush

    @if($poStatus === 'draft' && !$purchase->trashed() && ($canEdit || $isMasterPo))
    <x-confirm-modal id="deleteModal" title="Delete Purchase Order" :action="route('product.purchase-order.delete.data')" confirmText="Delete">
        <p class="mb-0">Are you sure you want to delete purchase order <strong>{{ $purchase->purchase_number }}</strong>?</p>
        <input type="hidden" name="id" value="{{ $purchase->id }}" />
    </x-confirm-modal>
    @endif
</x-app-layout>
