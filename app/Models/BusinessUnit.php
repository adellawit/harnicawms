<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessUnit extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'pgsql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'master_data.business_units';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'parent_id',
        'type_code',
        'code',
        'name',
        'brand_name',
        'logo',
        'legal_name',
        'npwp',
        'nib',
        'email',
        'phone',
        'address',
        'city',
        'province',
        'postal_code',
        'country',
        'is_pos_active',
        'is_inventory_active',
        'tax_type',
        'tax_percentage',
        'service_charge_percentage',
        'timezone',
        'currency',
        'opening_date',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_pos_active' => 'boolean',
        'is_inventory_active' => 'boolean',
        'is_active' => 'boolean',
        'tax_percentage' => 'decimal:2',
        'service_charge_percentage' => 'decimal:2',
        'opening_date' => 'date',
    ];

    /**
     * Get the parent business unit.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'parent_id', 'id');
    }

    /**
     * Get the child business units.
     */
    public function children(): HasMany
    {
        return $this->hasMany(BusinessUnit::class, 'parent_id', 'id');
    }

    /**
     * Get warehouses associated with this branch.
     */
    public function warehouses()
    {
        return $this->belongsToMany(
            BusinessUnit::class,
            'master_data.business_unit_branches',
            'branch_id',
            'warehouse_id'
        )->withPivot('is_default');
    }

    /**
     * Get branches associated with this warehouse.
     */
    public function branches()
    {
        return $this->belongsToMany(
            BusinessUnit::class,
            'master_data.business_unit_branches',
            'warehouse_id',
            'branch_id'
        )->withPivot('is_default');
    }
}
