<?php

namespace App\Models;

use App\Support\StockSourceTypeLabel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCostLayer extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.product_cost_layers';

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'company_id',
        'branch_id',
        'unit_id',
        'quantity',
        'quantity_remaining',
        'unit_cost',
        'source_type',
        'source_id',
        'effective_date',
        'expiry_date',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'quantity_remaining' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'effective_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id', 'id');
    }

    public function getSourceTypeLabelAttribute(): string
    {
        return StockSourceTypeLabel::label($this->source_type);
    }
}
