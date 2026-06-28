<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseType extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $connection = 'pgsql';

    protected $table = 'master_data.warehouse_types';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'warehouse_type_code', 'code');
    }
}
