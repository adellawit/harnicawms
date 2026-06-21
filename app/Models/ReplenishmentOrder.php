<?php

namespace App\Models;

use App\Models\Partner\Agent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReplenishmentOrder extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'distribution.replenishment_orders';

    protected $fillable = [
        'order_number',
        'order_date',
        'distributor_id',
        'agent_id',
        'price_list_id',
        'invoice_number',
        'payment_reference',
        'payment_status',
        'paid_at',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'paid_at' => 'datetime',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ReplenishmentOrderItem::class, 'order_id', 'id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'order_id', 'id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'order_id', 'id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnOrder::class, 'order_id', 'id');
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'distributor_id', 'id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'id');
    }
}
