<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrderItem extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'transaction.sales_order_items';

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'product_variant_id',
        'unit_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'discount_type',
        'discount_value',
        'subtotal',
        'notes',
        'is_promo_free',
        'promotion_id',
        'source_warehouse_id',
        'parent_item_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'unit_price' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'discount_value' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'is_promo_free' => 'boolean',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id', 'id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function serialAssignments(): HasMany
    {
        return $this->hasMany(SalesOrderItemSerialAssignment::class);
    }

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_item_id');
    }
}
