<?php

namespace App\Models\Partner;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForediPriceTier extends Model
{
    use HasUuids, SoftDeletes;

    public const LEVEL_RESMI = 'RESMI';

    public const LEVEL_MAP = 'MAP';

    public const LEVEL_RESELLER = 'RESELLER';

    public const LEVEL_AGEN = 'AGEN';

    protected $connection = 'pgsql';

    protected $table = 'partner.foredi_price_tiers';

    protected $fillable = [
        'category_id',
        'product_id',
        'level',
        'min_qty',
        'unit_code',
        'price',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'min_qty' => 'decimal:4',
        'price' => 'decimal:4',
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
