<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'distribution.receipts';

    protected $fillable = [
        'receipt_number',
        'order_id',
        'shipment_id',
        'receive_date',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'receive_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ReplenishmentOrder::class, 'order_id', 'id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReceiptItem::class, 'receipt_id', 'id');
    }
}
