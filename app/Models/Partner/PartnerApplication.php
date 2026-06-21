<?php

namespace App\Models\Partner;

use App\Models\BusinessUnit;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerApplication extends Model
{
    use HasUuids, SoftDeletes;

    public const TYPE_AGENT = 'AGENT';
    public const TYPE_RESELLER = 'RESELLER';

    protected $connection = 'pgsql';

    protected $table = 'partner.partner_applications';

    protected $fillable = [
        'company_id',
        'customer_id',
        'assigned_agent_id',
        'converted_agent_id',
        'converted_reseller_id',
        'application_number',
        'partner_type',
        'name',
        'email',
        'phone',
        'requested_purchase_quantity',
        'address',
        'city',
        'province',
        'postal_code',
        'status',
        'notes',
        'submitted_at',
        'reviewed_at',
        'assigned_at',
        'converted_at',
        'rejected_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'requested_purchase_quantity' => 'decimal:4',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'converted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'company_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id', 'id');
    }

    public function convertedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'converted_agent_id', 'id');
    }

    public function convertedReseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'converted_reseller_id', 'id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PartnerApplicationDocument::class, 'application_id', 'id');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(PartnerFollowup::class, 'application_id', 'id');
    }

    public function scopeForCompany(Builder $query, ?string $companyId): Builder
    {
        return $query->when($companyId, fn (Builder $q) => $q->where('company_id', $companyId));
    }
}
