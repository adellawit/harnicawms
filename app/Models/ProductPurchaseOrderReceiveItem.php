<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPurchaseOrderReceiveItem extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'product.purchase_order_receive_items';

    protected $fillable = [
        'receive_id',
        'purchase_order_item_id',
        'product_id',
        'variant_id',
        'unit_id',
        'batch_number',
        'expiry_date',
        'product_batch_id',
        'quantity_received',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:6',
        'expiry_date' => 'date',
    ];

    public function receive(): BelongsTo
    {
        return $this->belongsTo(ProductPurchaseOrderReceive::class, 'receive_id', 'id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(ProductPurchaseOrderItem::class, 'purchase_order_item_id', 'id');
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
}
