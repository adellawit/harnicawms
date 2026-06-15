<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillOfMaterial extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'manufacturing.bill_of_materials';

    protected $fillable = [
        'company_id',
        'product_id',
        'product_variant_id',
        'output_unit_id',
        'output_quantity',
        'name',
        'version',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'output_quantity' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function outputUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'output_unit_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class, 'bom_id', 'id');
    }
}
