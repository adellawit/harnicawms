<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderOutput extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'manufacturing.production_order_outputs';

    protected $fillable = [
        'production_order_id',
        'product_id',
        'product_variant_id',
        'unit_id',
        'qty_produced',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'qty_produced' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }
}
