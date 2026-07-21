<?php

namespace App\Services\Shop;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantStock;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ShopCartService
{
    public const SESSION_KEY = 'customer_shop_cart';

    public function __construct(
        protected ShopContextService $context,
    ) {}

    /**
     * @return array{price_list_id: string|null, items: array<int, array<string, mixed>>}
     */
    public function get(): array
    {
        $cart = Session::get(self::SESSION_KEY, [
            'price_list_id' => null,
            'items' => [],
        ]);

        if (! is_array($cart['items'] ?? null)) {
            $cart['items'] = [];
        }

        return $cart;
    }

    public function count(): int
    {
        return count($this->get()['items']);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * @return array{price_list_id: string|null, items: array<int, array<string, mixed>>}
     */
    public function add(string $variantId, float $quantity = 1): array
    {
        $this->context->assertReady();
        $priceListId = $this->context->priceListId();
        $branchId = $this->context->branchId();
        $companyId = $this->context->companyId();

        $variant = ProductVariant::with('product')
            ->where('id', $variantId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $product = $variant->product;
        if (! $product || ! $product->is_sale_item || $product->branch_id !== $branchId) {
            throw new \InvalidArgumentException('Produk tidak tersedia.');
        }

        $unitId = $product->default_unit_id;
        if (! $unitId) {
            $unitId = ProductVariantPrice::where('variant_id', $variant->id)
                ->where('branch_id', $branchId)
                ->where('price_list_id', $priceListId)
                ->whereNull('deleted_at')
                ->value('unit_id');
        }

        if (! $unitId) {
            throw new \InvalidArgumentException('Unit produk tidak ditemukan.');
        }

        $priceRow = ProductVariantPrice::where('variant_id', $variant->id)
            ->where('branch_id', $branchId)
            ->where('price_list_id', $priceListId)
            ->where('unit_id', $unitId)
            ->whereNull('deleted_at')
            ->first();

        $unitPrice = (float) ($priceRow?->selling_price ?? 0);
        if ($unitPrice <= 0) {
            throw new \InvalidArgumentException('Harga produk belum diatur.');
        }

        $warehouseId = optional(WmsContext::defaultWarehouse($branchId))->id;

        $stockRow = ProductVariantStock::where('product_variant_id', $variant->id)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId), fn ($q) => $q->where('branch_id', $branchId))
            ->where('unit_id', $unitId)
            ->whereNull('deleted_at')
            ->first();

        $stock = (int) ($stockRow?->quantity ?? 0);
        if ($product->is_stock_item && $stock < 1) {
            throw new \InvalidArgumentException('Stok habis.');
        }

        $cart = $this->get();
        $cart['price_list_id'] = $priceListId;
        $items = $cart['items'];
        $key = $variantId.'|'.$unitId;
        $existingIndex = null;

        foreach ($items as $i => $row) {
            if (($row['cart_key'] ?? '') === $key) {
                $existingIndex = $i;
                break;
            }
        }

        $newQty = $quantity;
        if ($existingIndex !== null) {
            $newQty = (float) $items[$existingIndex]['quantity'] + $quantity;
        }

        if ($product->is_stock_item && $newQty > $stock) {
            throw new \InvalidArgumentException('Jumlah melebihi stok tersedia ('.$stock.').');
        }

        $line = [
            'cart_key' => $key,
            'variant_id' => $variant->id,
            'product_id' => $product->id,
            'unit_id' => $unitId,
            'quantity' => $newQty,
            'unit_price' => $unitPrice,
            'product_name' => $product->name,
            'variant_name' => $variant->display_name ?: $variant->sku,
            'sku' => $variant->sku,
            'image' => $variant->image ?? $product->image,
            'stock' => $stock,
            'is_stock_item' => (bool) $product->is_stock_item,
        ];

        if ($existingIndex !== null) {
            $items[$existingIndex] = $line;
        } else {
            $items[] = $line;
        }

        $cart['items'] = array_values($items);
        Session::put(self::SESSION_KEY, $cart);

        return $cart;
    }

    public function update(string $cartKey, float $quantity): array
    {
        $cart = $this->get();
        $items = [];

        foreach ($cart['items'] as $row) {
            if (($row['cart_key'] ?? '') !== $cartKey) {
                $items[] = $row;
                continue;
            }

            if ($quantity <= 0) {
                continue;
            }

            if ($row['is_stock_item'] && $quantity > (float) $row['stock']) {
                throw new \InvalidArgumentException('Jumlah melebihi stok.');
            }

            $row['quantity'] = $quantity;
            $items[] = $row;
        }

        $cart['items'] = $items;
        Session::put(self::SESSION_KEY, $cart);

        return $cart;
    }

    public function remove(string $cartKey): array
    {
        return $this->update($cartKey, 0);
    }

    /**
     * @return array{subtotal: float, tax_amount: float, total: float, item_count: int}
     */
    public function summarize(): array
    {
        $cart = $this->get();
        $subtotal = 0.0;
        $itemCount = 0;

        foreach ($cart['items'] as $row) {
            $qty = (float) $row['quantity'];
            $subtotal += $qty * (float) $row['unit_price'];
            $itemCount += (int) $qty;
        }

        $taxEnabled = (bool) config('shop.tax_enabled', true);
        $taxRate = $this->context->taxRate();
        $taxAmount = $taxEnabled ? round($subtotal * $taxRate / 100, 4) : 0;
        $total = $subtotal + $taxAmount;

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'tax_enabled' => $taxEnabled,
            'total' => $total,
            'item_count' => $itemCount,
        ];
    }

    /**
     * Build checkout request payload for PosCheckoutService.
     */
    public function toCheckoutRequest(): Request
    {
        $cart = $this->get();
        $summary = $this->summarize();

        $items = [];
        foreach ($cart['items'] as $row) {
            $items[] = [
                'variant_id' => $row['variant_id'],
                'unit_id' => $row['unit_id'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'discount_type' => 'percent',
                'discount_value' => 0,
            ];
        }

        return Request::create('/', 'POST', [
            'price_list_id' => $cart['price_list_id'] ?? $this->context->priceListId(),
            'customer_id' => $this->context->customer()->id,
            'branch_id' => $this->context->branchId(),
            'company_id' => $this->context->companyId(),
            'items' => $items,
            'tax_rate' => $summary['tax_rate'],
            'tax_enabled' => $summary['tax_enabled'],
            'discount_type' => 'percent',
            'discount_value' => 0,
        ]);
    }
}
