<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPrice extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.product_prices';

    protected $fillable = [
        'product_id',
        'company_id',
        'branch_id',
        'unit_id',
        'purchase_price',
        'selling_price',
        'price_list_id', // New: support multiple price lists
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:4',
        'selling_price' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id', 'id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(ProductPriceList::class, 'price_list_id', 'id');
    }
}
