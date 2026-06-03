<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPriceList extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.product_price_lists';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'company_id',
        'branch_id',
        'channel_type',
        'external_channel_code',
        'sort_order',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'company_id', 'id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id', 'id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'price_list_id', 'id');
    }

    public function variantPrices(): HasMany
    {
        return $this->hasMany(ProductVariantPrice::class, 'price_list_id', 'id');
    }

    /**
     * Price list level company (branch_id null) atau level cabang tertentu.
     */
    public function scopeForBusinessContext(Builder $query, ?string $companyId, ?string $branchId): Builder
    {
        return $query
            ->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId))
            ->where(function (Builder $q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId) {
                    $q->orWhere('branch_id', $branchId);
                }
            });
    }
}
