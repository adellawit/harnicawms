<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseKontrabon extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'product.purchase_kontrabons';

    protected $fillable = [
        'kontrabon_number',
        'kontrabon_date',
        'supplier_id',
        'supplier_name',
        'company_id',
        'branch_id',
        'status',
        'notes',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'paid_amount',
        'payment_date',
        'payment_reference',
        'payment_method',
        'payment_notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'kontrabon_date' => 'date',
        'payment_date' => 'date',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'total' => 'decimal:4',
        'paid_amount' => 'decimal:4',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseKontrabonItem::class, 'kontrabon_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchaseKontrabonPayment::class, 'kontrabon_id', 'id');
    }

    public function getBalanceAmountAttribute(): float
    {
        return max(0, (float) $this->total - (float) ($this->paid_amount ?? 0));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status ?? '') {
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'partial_paid' => 'Partial Paid',
            'paid' => 'Paid',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status ?? '-'),
        };
    }
}
