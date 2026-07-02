<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPurchaseOrderItem extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'parent_item_id',
        'product_id',
        'variant_id',
        'unit_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'subtotal',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'unit_price' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'subtotal' => 'decimal:4',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProductPurchaseOrder::class, 'purchase_order_id', 'id');
    }

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_item_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id', 'id');
    }

    public function receiveItems(): HasMany
    {
        return $this->hasMany(ProductPurchaseOrderReceiveItem::class, 'purchase_order_item_id', 'id');
    }

    public function getQuantityReceivedAttribute(): float
    {
        return (float) $this->receiveItems()->sum('quantity_received');
    }

    public function getQuantityRemainingAttribute(): float
    {
        return max(0, (float) $this->quantity - $this->quantity_received);
    }
}
