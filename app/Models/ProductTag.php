<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductTag extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.product_tags';

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'code',
        'type',
        'color',
        'description',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    public const TYPE_GENERAL = 'general';
    public const TYPE_BEST_SELLER = 'best_seller';
    public const TYPE_PRODUCT_FOCUS = 'product_focus';
    public const TYPE_FEATURED = 'featured';

    public static function typeOptions(): array
    {
        return [
            self::TYPE_GENERAL => 'General',
            self::TYPE_BEST_SELLER => 'Best Seller',
            self::TYPE_PRODUCT_FOCUS => 'Product Focus',
            self::TYPE_FEATURED => 'Featured',
        ];
    }
}
