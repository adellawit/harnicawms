<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCostHistory extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'product.product_cost_history';

    protected $fillable = [
        'product_id',
        'branch_id',
        'unit_id',
        'cost',
        'cost_type',
        'effective_date',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'cost' => 'decimal:4',
        'effective_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id', 'id');
    }
}
