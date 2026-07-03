<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseKontrabonPayment extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'product.purchase_kontrabon_payments';

    protected $fillable = [
        'kontrabon_id',
        'payment_date',
        'amount',
        'payment_reference',
        'payment_method',
        'payment_notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:4',
    ];

    public function kontrabon(): BelongsTo
    {
        return $this->belongsTo(PurchaseKontrabon::class, 'kontrabon_id', 'id');
    }
}
