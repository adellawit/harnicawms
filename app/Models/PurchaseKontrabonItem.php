<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseKontrabonItem extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'product.purchase_kontrabon_items';

    protected $fillable = [
        'kontrabon_id',
        'purchase_order_id',
        'po_total',
        'supplier_invoice_number',
        'supplier_invoice_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'other_cost_amount',
        'total',
        'notes',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'supplier_invoice_date' => 'date',
        'po_total' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'other_cost_amount' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    public function kontrabon(): BelongsTo
    {
        return $this->belongsTo(PurchaseKontrabon::class, 'kontrabon_id', 'id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProductPurchaseOrder::class, 'purchase_order_id', 'id');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return asset('storage/'.$this->attachment_path);
    }

    public function getHasAttachmentAttribute(): bool
    {
        return filled($this->attachment_path);
    }
}
