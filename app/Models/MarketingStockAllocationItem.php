<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingStockAllocationItem extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'distribution.marketing_stock_allocation_items';

    protected $fillable = [
        'allocation_id',
        'product_id',
        'product_variant_id',
        'unit_id',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_cost' => 'float',
        'total_cost' => 'float',
    ];

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(MarketingStockAllocation::class, 'allocation_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }
}
