<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductUnitConversion extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.product_unit_conversions';

    protected $fillable = [
        'product_id',
        'from_unit_id',
        'to_unit_id',
        'conversion_factor',
        'conversion_level',
        'description',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:6',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'from_unit_id', 'id')->withTrashed();
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'to_unit_id', 'id')->withTrashed();
    }
}
