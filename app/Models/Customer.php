<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

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
