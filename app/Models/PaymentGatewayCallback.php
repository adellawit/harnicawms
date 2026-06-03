<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayCallback extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'transaction.payment_gateway_callbacks';

    protected $fillable = [
        'sales_order_id',
        'sales_order_payment_id',
        'gateway',
        'source',
        'external_id',
        'gateway_reference',
        'invoice_status',
        'process_result',
        'error_message',
        'payload',
        'ip_address',
        'user_agent',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id', 'id');
    }

    public function salesOrderPayment(): BelongsTo
    {
        return $this->belongsTo(SalesOrderPayment::class, 'sales_order_payment_id', 'id');
    }
}
