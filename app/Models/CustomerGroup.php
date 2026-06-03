<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerGroup extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'customer.customer_groups';

    protected $fillable = [
        'id',
        'branch_id',
        'code',
        'name',
        'description',
        'price_list_id',
        'default_discount',
        'allow_credit',
        'credit_limit',
        'payment_term_days',
        'earn_point',
        'point_multiplier',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'allow_credit' => 'boolean',
        'earn_point' => 'boolean',
        'is_active' => 'boolean',
        'default_discount' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'point_multiplier' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id', 'id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(ProductPriceList::class, 'price_list_id', 'id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'customer_group_id', 'id');
    }
}
