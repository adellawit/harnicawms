<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.products';

    protected $fillable = [
        'company_id',
        'branch_id',
        'nature_id',
        'category_id',
        'item_type_id',
        'product_nature_id',
        'procurement_type_id',
        'default_unit_id',
        'name',
        'code',
        'sku',
        'description',
        'is_stock_item',
        'is_sale_item',
        'is_purchase_item',
        'min_stock',
        'max_stock',
        'cogs_account_code',
        'revenue_account_code',
        'created_by',
        'updated_by',
    ];

    /** @deprecated Use item_type_id - alias for view backward compatibility */
    public function getTypeIdAttribute(): ?string
    {
        return $this->item_type_id;
    }

    protected $casts = [
        'is_stock_item' => 'boolean',
        'is_sale_item' => 'boolean',
        'is_purchase_item' => 'boolean',
        'min_stock' => 'decimal:4',
        'max_stock' => 'decimal:4',
    ];

    public function nature(): BelongsTo
    {
        return $this->belongsTo(ProductNature::class, 'nature_id', 'id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id', 'id');
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ParameterDetail::class, 'item_type_id', 'id');
    }

    public function productNature(): BelongsTo
    {
        return $this->belongsTo(ParameterDetail::class, 'product_nature_id', 'id');
    }

    public function procurementType(): BelongsTo
    {
        return $this->belongsTo(ParameterDetail::class, 'procurement_type_id', 'id');
    }

    public function defaultUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'default_unit_id', 'id');
    }

    public function unitConversions(): HasMany
    {
        return $this->hasMany(ProductUnitConversion::class, 'product_id', 'id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id', 'id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductVariantStock::class, 'product_id', 'id');
    }

    public function variantStocks(): HasManyThrough
    {
        return $this->hasManyThrough(ProductVariantStock::class, ProductVariant::class, 'product_id', 'product_variant_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_id', 'id');
    }

    public function scopeStockItems($query)
    {
        return $query->where('is_stock_item', true);
    }

    public function scopeSaleItems($query)
    {
        return $query->where('is_sale_item', true);
    }

    public function scopePurchaseItems($query)
    {
        return $query->where('is_purchase_item', true);
    }

    public function getSmallestUnitId(): string
    {
        $conv = $this->unitConversions->sortByDesc('conversion_level')->first();
        return $conv ? $conv->to_unit_id : $this->default_unit_id;
    }

    public function getFactorToSmallest(): float
    {
        $factor = 1.0;
        $currentUnitId = $this->default_unit_id;
        foreach ($this->unitConversions->sortBy('conversion_level') as $conv) {
            if ($conv->from_unit_id === $currentUnitId) {
                $factor *= (float) $conv->conversion_factor;
                $currentUnitId = $conv->to_unit_id;
            }
        }
        return $factor;
    }

    public function convertQuantity(float $quantity, string $fromUnitId, string $toUnitId): ?float
    {
        if ($fromUnitId === $toUnitId) {
            return $quantity;
        }
        $conv = $this->unitConversions()
            ->where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $toUnitId)
            ->first();
        if ($conv) {
            return round($quantity * (float) $conv->conversion_factor, 6);
        }
        $convReverse = $this->unitConversions()
            ->where('from_unit_id', $toUnitId)
            ->where('to_unit_id', $fromUnitId)
            ->first();
        if ($convReverse) {
            return round($quantity / (float) $convReverse->conversion_factor, 6);
        }

        return null;
    }
}
