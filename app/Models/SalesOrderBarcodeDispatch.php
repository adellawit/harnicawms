<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderBarcodeDispatch extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COMPLETED = 'completed';

    protected $connection = 'pgsql';

    protected $table = 'transaction.sales_order_barcode_dispatches';

    protected $fillable = [
        'sales_order_id',
        'status',
        'dispatched_by',
        'dispatched_at',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SalesOrderItemSerialAssignment::class, 'dispatch_id');
    }

    public function dispatchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }
}
