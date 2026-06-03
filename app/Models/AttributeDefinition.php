<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttributeDefinition extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.attribute_definitions';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'type',
        'description',
        'validation_rules',
        'sort_order',
        'is_variant_attribute',
        'is_filterable',
        'is_required',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'validation_rules' => 'array',
        'is_variant_attribute' => 'boolean',
        'is_filterable' => 'boolean',
        'is_required' => 'boolean',
    ];

    public function attributeValues(): HasMany
    {
        return $this->hasMany(AttributeValue::class, 'attribute_definition_id', 'id')
            ->whereNull('deleted_at')
            ->orderBy('sort_order');
    }

    public function attributeValuesWithTrashed(): HasMany
    {
        return $this->hasMany(AttributeValue::class, 'attribute_definition_id', 'id');
    }
}
