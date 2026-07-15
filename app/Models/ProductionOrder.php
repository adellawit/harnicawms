<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionOrder extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'manufacturing.production_orders';

    protected $fillable = [
        'order_number',
        'production_date',
        'company_id',
        'branch_id',
        'source_warehouse_id',
        'output_warehouse_id',
        'output_expiry_date',
        'bom_id',
        'product_id',
        'product_variant_id',
        'output_unit_id',
        'planned_qty',
        'produced_qty',
        'total_material_cost',
        'overhead_cost',
        'output_unit_cost',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'production_date' => 'date',
        'output_expiry_date' => 'date',
        'planned_qty' => 'decimal:6',
        'produced_qty' => 'decimal:6',
        'total_material_cost' => 'decimal:4',
        'overhead_cost' => 'decimal:4',
        'output_unit_cost' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class, 'bom_id', 'id');
    }

    public function outputUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'output_unit_id', 'id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id', 'id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id', 'id');
    }

    public function outputWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'output_warehouse_id', 'id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProductionOrderMaterial::class, 'production_order_id', 'id');
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(ProductionOrderOutput::class, 'production_order_id', 'id');
    }

    public function overheads(): HasMany
    {
        return $this->hasMany(ProductionOrderOverhead::class, 'production_order_id', 'id')
            ->orderBy('sort_order');
    }
}
