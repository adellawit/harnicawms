<?php

namespace App\Models\Partner;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CuttingPriceConfig extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'partner.cutting_price_configs';

    protected $fillable = [
        'category_id',
        'product_id',
        'unit_code',
        'official_price',
        'map_price',
        'reseller_price_30',
        'reseller_price_60',
        'reseller_price_120',
        'agent_price_600',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'official_price' => 'decimal:4',
        'map_price' => 'decimal:4',
        'reseller_price_30' => 'decimal:4',
        'reseller_price_60' => 'decimal:4',
        'reseller_price_120' => 'decimal:4',
        'agent_price_600' => 'decimal:4',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
