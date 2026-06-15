<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnOrder extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'distribution.returns';

    protected $fillable = [
        'return_number',
        'order_id',
        'agent_id',
        'distributor_id',
        'return_date',
        'reason',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'return_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ReplenishmentOrder::class, 'order_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id', 'id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'agent_id', 'id');
    }
}
