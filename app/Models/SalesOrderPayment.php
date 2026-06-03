<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrderPayment extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'transaction.sales_order_payments';

    protected $fillable = [
        'sales_order_id',
        'method_payment_id',
        'payment_code',
        'gateway',
        'gateway_reference',
        'gateway_url',
        'amount',
        'change_amount',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'change_amount' => 'decimal:4',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id', 'id');
    }

    public function methodPayment(): BelongsTo
    {
        return $this->belongsTo(MethodPayment::class, 'method_payment_id', 'id');
    }
}
