<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBatchStock extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'product.product_batch_stock';

    protected $fillable = [
        'product_batch_id',
        'branch_id',
        'warehouse_id',
        'unit_id',
        'quantity',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id', 'id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id', 'id');
    }
}
