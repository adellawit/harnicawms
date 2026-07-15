<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductStockMovement extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.product_stock_movements';

    protected $fillable = [
        'product_variant_stock_id',
        'product_variant_id',
        'product_id',
        'company_id',
        'branch_id',
        'warehouse_id',
        'unit_id',
        'stock_mutation_type_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'quantity_before' => 'decimal:6',
        'quantity_after' => 'decimal:6',
    ];

    public function variantStock(): BelongsTo
    {
        return $this->belongsTo(ProductVariantStock::class, 'product_variant_stock_id', 'id');
    }

    /** @deprecated Use variantStock() - alias for backward compatibility */
    public function productStock(): BelongsTo
    {
        return $this->variantStock();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function stockMutationType(): BelongsTo
    {
        return $this->belongsTo(StockMutationType::class, 'stock_mutation_type_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id', 'id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id', 'id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    /** @deprecated Use stockMutationType() - alias for view backward compatibility */
    public function mutationType(): BelongsTo
    {
        return $this->stockMutationType();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }
}
