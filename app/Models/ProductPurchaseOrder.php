<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPurchaseOrder extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.purchase_orders';

    protected $fillable = [
        'purchase_number',
        'purchase_date',
        'supplier_id',
        'supplier_name',
        'supplier_contact',
        'supplier_address',
        'company_id',
        'branch_id',
        'status',
        'expected_delivery_date',
        'notes',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'expected_delivery_date' => 'date',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\BusinessUnit::class, 'branch_id', 'id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public function getStatusKeyAttribute(): ?string
    {
        return $this->status;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status ?? '') {
            'draft' => 'Draft',
            'process' => 'Process',
            'receiving' => 'Receiving',
            'payment' => 'Payment',
            'received' => 'Received',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status ?? ''),
        };
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductPurchaseOrderItem::class, 'purchase_order_id', 'id');
    }

    public function receives(): HasMany
    {
        return $this->hasMany(ProductPurchaseOrderReceive::class, 'purchase_order_id', 'id');
    }
}
