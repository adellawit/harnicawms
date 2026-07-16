<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingStockAllocation extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'distribution.marketing_stock_allocations';

    protected $fillable = [
        'allocation_number',
        'allocation_date',
        'company_id',
        'branch_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'allocation_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MarketingStockAllocationItem::class, 'allocation_id');
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id');
    }
}
