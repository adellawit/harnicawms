<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'manufacturing.bom_items';

    protected $fillable = [
        'bom_id',
        'component_product_id',
        'component_variant_id',
        'unit_id',
        'quantity',
        'last_unit_cost',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'last_unit_cost' => 'decimal:4',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class, 'bom_id', 'id');
    }

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
