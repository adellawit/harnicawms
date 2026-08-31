<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class PurchaseOrderCatalog
{
    /** @var array<string, list<string>> */
    public const SUPPLIER_ITEM_TYPE_MAP = [
        'raw_material' => ['raw_material'],
        'product' => ['finished_good'],
    ];

    /**
     * @return list<string>|null  null = no filter (show all purchase items)
     */
    public static function itemTypeKeysForSupplier(?string $supplierTypeKey): ?array
    {
        if ($supplierTypeKey === null || $supplierTypeKey === '') {
            return null;
        }

        return self::SUPPLIER_ITEM_TYPE_MAP[$supplierTypeKey] ?? null;
    }

    public static function supplierTypeKey(?Supplier $supplier): ?string
    {
        if (! $supplier) {
            return null;
        }

        $supplier->loadMissing('supplierType');

        return $supplier->supplierType?->key;
    }

    /**
     * @param  Collection<int, Product>|iterable<int, Product>  $products
     * @return Collection<int, Product>
     */
    public static function filterProductsForSupplier(iterable $products, ?Supplier $supplier): Collection
    {
        $collection = $products instanceof Collection ? $products : collect($products);
        $allowedKeys = self::itemTypeKeysForSupplier(self::supplierTypeKey($supplier));

        return $collection
            ->filter(fn (Product $product) => (bool) $product->is_purchase_item)
            ->when($allowedKeys !== null, function (Collection $query) use ($allowedKeys) {
                return $query->filter(function (Product $product) use ($allowedKeys) {
                    $product->loadMissing('itemType');

                    return in_array($product->itemType?->key, $allowedKeys, true);
                });
            })
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function productsPayload(iterable $products): array
    {
        $collection = $products instanceof Collection ? $products : collect($products);

        return $collection->map(function (Product $product) {
            $product->loadMissing(['itemType', 'defaultUnit', 'unitConversions', 'variants.variantAttributes.attributeDefinition', 'variants.variantAttributes.attributeValue']);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'item_type_key' => $product->itemType?->key,
                'item_type_label' => $product->itemType?->value,
                'is_purchase_item' => (bool) $product->is_purchase_item,
                'default_unit_id' => $product->default_unit_id,
                'unit_conversions' => $product->unitConversions,
                'variants' => $product->variants,
                'option_label' => self::productOptionLabel($product),
            ];
        })->values()->all();
    }

    public static function productOptionLabel(Product $product): string
    {
        $product->loadMissing('itemType');
        $type = trim((string) ($product->itemType?->value ?? ''));

        return $type === '' ? $product->name : $product->name.' - '.$type;
    }

    public static function supplierOptionLabel(Supplier $supplier): string
    {
        $supplier->loadMissing('supplierType');
        $type = trim((string) ($supplier->supplierType?->value ?? ''));

        return $type === '' ? $supplier->name : $supplier->name.' - '.$type;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function suppliersPayload(iterable $suppliers): array
    {
        $collection = $suppliers instanceof Collection ? $suppliers : collect($suppliers);

        return $collection->map(function (Supplier $supplier) {
            $supplier->loadMissing('supplierType');

            return [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'contact' => $supplier->contact,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'is_ppn' => (bool) $supplier->is_ppn,
                'ppn_rate' => $supplier->ppn_rate,
                'supplier_type_key' => $supplier->supplierType?->key,
                'supplier_type_label' => $supplier->supplierType?->value,
                'option_label' => self::supplierOptionLabel($supplier),
            ];
        })->values()->all();
    }
}
