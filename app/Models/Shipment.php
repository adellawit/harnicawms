<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'distribution.shipments';

    protected $fillable = [
        'shipment_number',
        'order_id',
        'ship_date',
        'carrier',
        'tracking_number',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'ship_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ReplenishmentOrder::class, 'order_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class, 'shipment_id', 'id');
    }
}
