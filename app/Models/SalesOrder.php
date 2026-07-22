<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'transaction.sales_orders';

    protected $fillable = [
        'sales_number',
        'sales_date',
        'order_type',
        'customer_id',
        'customer_name',
        'customer_contact',
        'customer_address',
        'company_id',
        'branch_id',
        'warehouse_id',
        'price_list_id',
        'method_payment_id',
        'status',
        'payment_status',
        'expected_delivery_date',
        'fulfilled_at',
        'paid_at',
        'subtotal',
        'tax_rate',
        'tax_enabled',
        'tax_amount',
        'discount_amount',
        'discount_type',
        'discount_value',
        'item_discount_total',
        'shipping_amount',
        'total',
        'membership_points_earned',
        'membership_points_redeemed',
        'membership_redeem_discount_amount',
        'membership_configuration_id',
        'notes',
        'reference',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'sales_date' => 'date',
        'expected_delivery_date' => 'date',
        'fulfilled_at' => 'datetime',
        'paid_at' => 'datetime',
        'tax_enabled' => 'boolean',
        'subtotal' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'discount_value' => 'decimal:4',
        'item_discount_total' => 'decimal:4',
        'shipping_amount' => 'decimal:4',
        'total' => 'decimal:4',
        'membership_points_earned' => 'integer',
        'membership_points_redeemed' => 'integer',
        'membership_redeem_discount_amount' => 'decimal:4',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalesOrderPayment::class, 'sales_order_id', 'id');
    }

    public function barcodeDispatch(): HasOne
    {
        return $this->hasOne(SalesOrderBarcodeDispatch::class);
    }

    public function paymentGatewayCallbacks(): HasMany
    {
        return $this->hasMany(PaymentGatewayCallback::class, 'sales_order_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id', 'id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(ProductPriceList::class, 'price_list_id', 'id');
    }

    public function methodPayment(): BelongsTo
    {
        return $this->belongsTo(MethodPayment::class, 'method_payment_id', 'id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function isCancellableByCustomer(): bool
    {
        if ($this->order_type !== 'web') {
            return false;
        }

        if ($this->status === 'cancelled' || $this->payment_status === 'paid') {
            return false;
        }

        return ! in_array($this->status, ['completed', 'void'], true);
    }
}
