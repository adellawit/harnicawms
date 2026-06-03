<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCollection extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.product_collections';

    protected $fillable = [
        'company_id',
        'branch_id',
        'parent_id',
        'name',
        'code',
        'slug',
        'description',
        'image',
        'start_date',
        'end_date',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCollection::class, 'parent_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProductCollection::class, 'parent_id', 'id');
    }
}
