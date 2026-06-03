@props([
    'product' => null,
    'productId' => null,
    'name' => null,
    'image' => null,
    'category' => null,
    'stock' => 0,
    'price' => 0,
    'natureId' => null,
])

@php
    $id = $productId ?? ($product->id ?? '');
    $productName = $name ?? ($product->name ?? '');
    $productImage = $image ?? ($product->image ?? '');
    $productCategory = $category ?? ($product->nature->name ?? ($product->category ?? '-'));
    $productStock = $stock ?? ($product->stock ?? 0);
    $productPrice = $price ?? ($product->selling_price ?? 0);
    $categoryId = $natureId ?? ($product->nature_id ?? '');

    $stockClass = $productStock > 10 ? 'available' : ($productStock > 0 ? 'low' : 'out');
    $formattedPrice = 'Rp ' . number_format($productPrice, 0, ',', '.');
@endphp

<div class="product-card" data-product-id="{{ $id }}" data-category-id="{{ $categoryId }}">
    <img src="{{ $productImage ?: 'https://placehold.co/300x200/f8f8f8/9aa4b8?text=No+Image' }}"
         alt="{{ $productName }}"
         class="product-image"
         onerror="this.src='https://placehold.co/300x200/f8f8f8/9aa4b8?text=No+Image'">
    <div class="product-info">
        <div class="product-name" title="{{ $productName }}">{{ $productName }}</div>
        <div class="product-category">{{ $productCategory }}</div>
        <div class="product-footer">
            <span class="product-stock {{ $stockClass }}">
                Stock: {{ $productStock }}
            </span>
            <span class="product-price">{{ $formattedPrice }}</span>
        </div>
    </div>
</div>
