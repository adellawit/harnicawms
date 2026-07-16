<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderOverhead extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'manufacturing.production_order_overheads';

    protected $fillable = [
        'production_order_id',
        'description',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'sort_order' => 'integer',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id', 'id');
    }
}
