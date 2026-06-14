<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplenishmentOrderItem extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'distribution.replenishment_order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'unit_id',
        'qty_ordered',
        'qty_shipped',
        'qty_received',
        'qty_returned',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'qty_ordered' => 'decimal:6',
        'qty_shipped' => 'decimal:6',
        'qty_received' => 'decimal:6',
        'qty_returned' => 'decimal:6',
        'unit_price' => 'decimal:4',
        'subtotal' => 'decimal:4',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ReplenishmentOrder::class, 'order_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }
}
