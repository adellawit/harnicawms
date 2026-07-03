<x-app-layout>
    @section('title', 'Receive Detail | ')

    <div class="container-xxl flex-grow-1 container-p-y">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => 'javascript:void(0);'],
                ['label' => 'Purchase Order', 'url' => route('product.purchase-order.index.view')],
                ['label' => $receive->purchaseOrder->purchase_number, 'url' => route('product.purchase-order.detail.view', $receive->purchase_order_id)],
                ['label' => $receive->receive_number, 'active' => true]
            ]"
        />

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Receive - {{ $receive->receive_number }}</h5>
                <a href="{{ route('product.purchase-order.detail.view', $receive->purchase_order_id) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ti ti-arrow-left me-1"></i>Back to PO
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td width="40%"><strong>Receive Number:</strong></td>
                                <td>{{ $receive->receive_number }}</td>
                            </tr>
                            <tr>
                                <td><strong>Receive Date:</strong></td>
                                <td>{{ $receive->receive_date?->format('d M Y') }}</td>
                            </tr>
                            <tr>
                                <td><strong>PO Number:</strong></td>
                                <td>
                                    <a href="{{ route('product.purchase-order.detail.view', $receive->purchase_order_id) }}">
                                        {{ $receive->purchaseOrder->purchase_number }}
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td width="40%"><strong>Created By:</strong></td>
                                <td>{{ $receive->createdByUser ? ($receive->createdByUser->first_name . ' ' . $receive->createdByUser->last_name) : '-' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Created At:</strong></td>
                                <td>{{ $receive->created_at?->format('d M Y H:i') }}</td>
                            </tr>
                            @if($receive->notes)
                            <tr>
                                <td><strong>Notes:</strong></td>
                                <td>{{ $receive->notes }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h5 class="card-header">Received Items</h5>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Product</th>
                                <th>Variant</th>
                                <th>Unit</th>
                                <th>Batch</th>
                                <th>Expired</th>
                                <th class="text-end">Qty Received</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($receive->items as $i => $item)
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
                                <td>{{ $item->batch_number ?: '-' }}</td>
                                <td>
                                    @if($item->expiry_date)
                                        <span class="badge bg-label-warning">{{ $item->expiry_date->format('d/m/Y') }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-end">{{ format_number($item->quantity_received, 2, true) }}</td>
                                <td>{{ $item->notes ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
