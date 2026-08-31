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
        'warehouse_id',
        'parent_id',
        'po_kind',
        'release_sequence',
        'release_status',
        'status',
        'expected_delivery_date',
        'notes',
        'attention_to',
        'ship_to_address',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'other_cost_amount',
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
        'other_cost_amount' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\BusinessUnit::class, 'branch_id', 'id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
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

    public function kontrabonItems(): HasMany
    {
        return $this->hasMany(PurchaseKontrabonItem::class, 'purchase_order_id', 'id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    public function isMaster(): bool
    {
        return ($this->po_kind ?? 'standalone') === 'master';
    }

    public function isSub(): bool
    {
        return ($this->po_kind ?? 'standalone') === 'sub';
    }

    public function isStandalone(): bool
    {
        return ($this->po_kind ?? 'standalone') === 'standalone';
    }

    public function getPoKindLabelAttribute(): string
    {
        return match ($this->po_kind ?? 'standalone') {
            'master' => 'CPO',
            'sub' => 'RO',
            default => 'PO',
        };
    }

    public function getReleaseStatusLabelAttribute(): ?string
    {
        return match ($this->release_status) {
            'open' => 'Belum Release',
            'partial' => 'Partial Release',
            'closed' => 'Fully Released',
            default => null,
        };
    }

    public function receives(): HasMany
    {
        return $this->hasMany(ProductPurchaseOrderReceive::class, 'purchase_order_id', 'id');
    }
}
