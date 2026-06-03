@props([
    'item' => null,
    'productId' => null,
    'name' => null,
    'image' => null,
    'price' => null,
    'quantity' => 1,
    'notes' => null,
])

@php
    $id = $productId ?? ($item->product_id ?? '');
    $itemName = $name ?? ($item->product_name ?? ($item->name ?? ''));
    $itemImage = $image ?? ($item->product_image ?? ($item->image ?? ''));
    $itemPrice = $price ?? ($item->unit_price ?? 0);
    $itemQuantity = $quantity ?? ($item->quantity ?? 1);
    $itemNotes = $notes ?? ($item->notes ?? '');

    $formattedPrice = 'Rp ' . number_format($itemPrice, 0, ',', '.');
@endphp

<div class="cart-item" data-product-id="{{ $id }}" data-price="{{ $itemPrice }}">
    <img src="{{ $itemImage ?: 'https://placehold.co/100x100/f8f8f8/9aa4b8?text=Product' }}"
         alt="{{ $itemName }}"
         class="item-image"
         onerror="this.src='https://placehold.co/100x100/f8f8f8/9aa4b8?text=Product'">
    <div class="item-details">
        <div class="item-name" title="{{ $itemName }}">{{ $itemName }}</div>
        <div class="item-price">{{ $formattedPrice }}</div>
        @if($itemNotes)
        <div class="item-notes"><small class="text-muted">{{ $itemNotes }}</small></div>
        @endif
    </div>
    <div class="item-actions">
        <div class="quantity-control">
            <button type="button" class="btn-minus">
                <i class="ti ti-minus" style="font-size: 0.75rem;"></i>
            </button>
            <input type="number" value="{{ $itemQuantity }}" min="1" class="quantity-input" readonly>
            <button type="button" class="btn-plus">
                <i class="ti ti-plus" style="font-size: 0.75rem;"></i>
            </button>
        </div>
        <div class="item-actions-buttons">
            <button type="button" class="btn-edit" title="Edit" data-product-id="{{ $id }}">
                <i class="ti ti-pencil" style="font-size: 0.75rem;"></i>
            </button>
            <button type="button" class="btn-delete" title="Delete" data-product-id="{{ $id }}">
                <i class="ti ti-trash" style="font-size: 0.75rem;"></i>
            </button>
        </div>
    </div>
</div>
