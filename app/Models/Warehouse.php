<?php

namespace App\Models;

use App\Models\Partner\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'master_data.warehouses';

    protected $fillable = [
        'company_id',
        'branch_id',
        'owner_type',
        'owner_id',
        'warehouse_type_code',
        'code',
        'name',
        'short_name',
        'legal_name',
        'email',
        'phone',
        'address',
        'city',
        'city_id',
        'province',
        'postal_code',
        'country',
        'is_default',
        'is_inventory_active',
        'is_active',
        'notes',
        'legacy_business_unit_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_inventory_active' => 'boolean',
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

    public function cityRef(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function warehouseType(): BelongsTo
    {
        return $this->belongsTo(WarehouseType::class, 'warehouse_type_code', 'code');
    }

    public function legacyBusinessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'legacy_business_unit_id', 'id');
    }

    public function agentOwner(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'owner_id', 'id')
            ->where('owner_type', 'AGENT');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BranchWarehouseAssignment::class, 'warehouse_id', 'id');
    }

    public function assignedBranches(): BelongsToMany
    {
        return $this->belongsToMany(
            BusinessUnit::class,
            'master_data.branch_warehouse_assignments',
            'warehouse_id',
            'branch_id'
        )->withPivot(['is_default', 'priority']);
    }

    public function branches(): BelongsToMany
    {
        return $this->assignedBranches();
    }

    public function getParentIdAttribute(): ?string
    {
        return $this->company_id;
    }

    public function getBrandNameAttribute(): ?string
    {
        return $this->warehouse_type_code;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }

    public function scopeInventoryActive(Builder $query): Builder
    {
        return $query->active()->where('is_inventory_active', true);
    }

    public function scopeForCompany(Builder $query, ?string $companyId): Builder
    {
        return $query->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId));
    }

    public function scopeForBranchAccess(Builder $query, ?string $branchId): Builder
    {
        return $query->when($branchId, function (Builder $q) use ($branchId) {
            $q->where(function (Builder $inner) use ($branchId) {
                $inner->where('branch_id', $branchId)
                    ->orWhereHas('assignedBranches', fn (Builder $assigned) => $assigned->where('master_data.business_units.id', $branchId));
            });
        });
    }

    public function scopeForOwner(Builder $query, string $ownerType, string $ownerId): Builder
    {
        return $query->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId);
    }

    public static function defaultForBranch(string $branchId): ?self
    {
        return self::inventoryActive()
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->first()
            ?? self::inventoryActive()
                ->whereHas('assignedBranches', function (Builder $query) use ($branchId) {
                    $query->where('master_data.business_units.id', $branchId)
                        ->where('master_data.branch_warehouse_assignments.is_default', true);
                })
                ->orderBy('code')
                ->first()
            ?? self::inventoryActive()
                ->where('branch_id', $branchId)
                ->orderBy('created_at')
                ->first();
    }

    public static function defaultForAgent(string $agentId): ?self
    {
        return self::inventoryActive()
            ->forOwner('AGENT', $agentId)
            ->where('is_default', true)
            ->first()
            ?? self::inventoryActive()
                ->forOwner('AGENT', $agentId)
                ->orderBy('created_at')
                ->first();
    }
}
