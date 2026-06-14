<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptItem extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'distribution.receipt_items';

    protected $fillable = [
        'receipt_id',
        'order_item_id',
        'product_id',
        'product_variant_id',
        'unit_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'receipt_id', 'id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(ReplenishmentOrderItem::class, 'order_item_id', 'id');
    }
}
