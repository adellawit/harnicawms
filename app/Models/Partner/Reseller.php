<?php

namespace App\Models\Partner;

use App\Models\BusinessUnit;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reseller extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'partner.resellers';

    protected $fillable = [
        'company_id',
        'agent_id',
        'customer_id',
        'code',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'province',
        'postal_code',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'company_id', 'id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(AgentResellerAssignment::class, 'reseller_id', 'id')
            ->where('is_active', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereNull('deleted_at');
    }
}
