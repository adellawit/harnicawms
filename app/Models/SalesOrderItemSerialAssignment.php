<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItemSerialAssignment extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'transaction.sales_order_item_serial_assignments';

    protected $fillable = [
        'dispatch_id',
        'sales_order_item_id',
        'product_label_serial_id',
        'scanned_by',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(SalesOrderBarcodeDispatch::class, 'dispatch_id');
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(ProductLabelSerial::class, 'product_label_serial_id');
    }

    public function scannedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
