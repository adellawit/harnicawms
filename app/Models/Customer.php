<?php

namespace App\Models;

use App\Models\Partner\Agent;
use App\Models\Partner\PartnerApplication;
use App\Models\Partner\Reseller;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_COMPANY = 'company';

    public const TYPE_PARTNER_LEAD = 'PARTNER_LEAD';

    public const TYPE_AGENT = 'AGENT';

    public const TYPE_RESELLER = 'RESELLER';

    protected $connection = 'pgsql';

    protected $table = 'customer.customers';

    protected $fillable = [
        'id',
        'customer_group_id',
        'code',
        'name',
        'tax_number',
        'tax_name',
        'tax_address',
        'contact_person',
        'email',
        'phone',
        'mobile',
        'address_ktp',
        'address_shipping',
        'address',
        'city',
        'province',
        'postal_code',
        'country',
        'lat',
        'long',
        'identity_type',
        'identity_number',
        'attachments',
        'username',
        'password',
        'has_app_access',
        'customer_type',
        'notes',
        'points_balance',
        'total_points_earned',
        'total_points_redeemed',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'has_app_access' => 'boolean',
        'is_active' => 'boolean',
        'password' => 'hashed',
        'attachments' => 'array',
        'lat' => 'decimal:8',
        'long' => 'decimal:8',
        'points_balance' => 'integer',
        'total_points_earned' => 'integer',
        'total_points_redeemed' => 'integer',
    ];

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id', 'id');
    }

    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class, 'customer_id', 'id');
    }

    public function reseller(): HasOne
    {
        return $this->hasOne(Reseller::class, 'customer_id', 'id');
    }

    public function latestPartnerApplication(): HasOne
    {
        // Avoid latestOfMany() — PostgreSQL cannot MAX(uuid) (Laravel uses id as tie-breaker).
        return $this->hasOne(PartnerApplication::class, 'customer_id', 'id')
            ->orderByDesc('created_at');
    }

    public function isPartnerAgent(): bool
    {
        if ($this->relationLoaded('agent')) {
            return $this->agent !== null;
        }

        return $this->agent()->exists();
    }

    public function isPartnerReseller(): bool
    {
        if ($this->relationLoaded('reseller')) {
            return $this->reseller !== null;
        }

        return $this->reseller()->exists();
    }

    public function partnerRole(): ?string
    {
        if ($this->isPartnerAgent()) {
            return 'agent';
        }

        if ($this->isPartnerReseller()) {
            return 'reseller';
        }

        if ($this->customer_type === self::TYPE_PARTNER_LEAD) {
            return 'partner_lead';
        }

        return null;
    }

    public function partnerRoleLabel(): ?string
    {
        return match ($this->partnerRole()) {
            'agent' => 'Agent',
            'reseller' => 'Reseller',
            'partner_lead' => 'Partner Lead',
            default => null,
        };
    }

    public function customerTypeLabel(): string
    {
        return match ($this->customer_type) {
            self::TYPE_INDIVIDUAL => 'Individual',
            self::TYPE_COMPANY => 'Company',
            self::TYPE_PARTNER_LEAD => 'Partner Lead',
            self::TYPE_AGENT => 'Agent',
            self::TYPE_RESELLER => 'Reseller',
            default => $this->customer_type
                ? ucwords(str_replace('_', ' ', strtolower($this->customer_type)))
                : '-',
        };
    }

    public function getBranchId(): ?string
    {
        return $this->customerGroup?->branch_id;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return '?';
        }

        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[1], 0, 1));
    }
}
