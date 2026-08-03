<?php

namespace App\Models\Partner;

use App\Models\BusinessUnit;
use App\Models\City;
use App\Models\Customer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'partner.agents';

    protected $fillable = [
        'company_id',
        'customer_id',
        'user_id',
        'default_warehouse_id',
        'code',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'city_id',
        'province',
        'postal_code',
        'status',
        'approval_status',
        'approved_at',
        'approved_by',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'company_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id', 'id');
    }

    public function cityRef(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'owner_id', 'id')
            ->where('owner_type', 'AGENT');
    }

    public function resellers(): HasMany
    {
        return $this->hasMany(Reseller::class, 'agent_id', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('approval_status', 'approved')
            ->whereNull('deleted_at');
    }
}
