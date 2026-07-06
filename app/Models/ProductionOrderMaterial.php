<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderMaterial extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'manufacturing.production_order_materials';

    protected $fillable = [
        'production_order_id',
        'component_product_id',
        'component_variant_id',
        'unit_id',
        'qty_consumed',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'qty_consumed' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id', 'id');
    }

    public function componentVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'component_variant_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id', 'id');
    }
}
