<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchWarehouseAssignment extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $connection = 'pgsql';

    protected $table = 'master_data.branch_warehouse_assignments';

    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'is_default',
        'priority',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'priority' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id', 'id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }
}
