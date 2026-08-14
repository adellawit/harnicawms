<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariantPrice extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.product_variant_prices';

    protected $fillable = [
        'variant_id',
        'company_id',
        'branch_id',
        'unit_id',
        'purchase_price',
        'selling_price',
        'price_list_id', // New: support multiple price lists
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:4',
        'selling_price' => 'decimal:4',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'variant_id', 'id'); // Actually via variant
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id', 'id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(ProductPriceList::class, 'price_list_id', 'id');
    }

    /**
     * Catalog card price: cheapest variant in the product's default unit
     * (not min across Sachet/Pack/Karton — those are converted unit prices).
     */
    public static function minCatalogSellingPrice(string $productId, string $branchId, string $priceListId): float
    {
        $defaultUnitId = Product::query()->where('id', $productId)->value('default_unit_id');

        $query = static::query()
            ->whereHas('variant', fn ($q) => $q->where('product_id', $productId)->whereNull('deleted_at'))
            ->where('branch_id', $branchId)
            ->where('price_list_id', $priceListId)
            ->whereNull('deleted_at');

        if ($defaultUnitId) {
            $query->where('unit_id', $defaultUnitId);
        }

        return (float) $query->min('selling_price');
    }
}
