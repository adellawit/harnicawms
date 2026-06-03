<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, SoftDeletes;

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
    protected $table = 'auth.users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'employee_id',
        'role_id',
        'current_business_unit_id',
        'first_name',
        'last_name',
        'username',
        'email',
        'phone',
        'password',
        'need_update_password',
        'url_image',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
    ];

    /**
     * Get the role associated with the user.
     */
    public function role(): HasOne
    {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employees::class, 'id', 'employee_id');
    }

    /**
     * Get the current business unit associated with the user.
     */
    public function businessUnit(): HasOne
    {
        return $this->hasOne(BusinessUnit::class, 'id', 'current_business_unit_id');
    }

    /**
     * Get the type of the user's current business unit.
     */
    public function getBusinessUnitType(): ?string
    {
        return $this->businessUnit?->type_code;
    }

    /**
     * Check if user is a Holding level user.
     */
    public function isHoldingUser(): bool
    {
        return $this->getBusinessUnitType() === 'HOLDING';
    }

    /**
     * Check if user is a Company level user.
     */
    public function isCompanyUser(): bool
    {
        return $this->getBusinessUnitType() === 'COMPANY';
    }

    /**
     * Check if user is a Branch level user.
     */
    public function isBranchUser(): bool
    {
        return $this->getBusinessUnitType() === 'BRANCH';
    }

    /**
     * Get accessible business unit IDs based on user's current business unit type.
     * - Holding users: can access all holdings, companies, and branches
     * - Company users: can access only their company and its branches
     * - Branch users: can access only their branch
     */
    public function getAccessibleBusinessUnitIds(): array
    {
        if (!$this->current_business_unit_id) {
            return [];
        }

        $businessUnit = $this->businessUnit;

        if (!$businessUnit) {
            return [];
        }

        return match($businessUnit->type_code) {
            'HOLDING' => $this->getAllDescendantIds($businessUnit->id),
            'COMPANY' => $this->getAllDescendantIds($businessUnit->id),
            'BRANCH' => [$businessUnit->id],
            default => [],
        };
    }

    /**
     * Get all descendant business unit IDs recursively.
     */
    protected function getAllDescendantIds(string $parentId): array
    {
        $ids = [$parentId];

        $children = BusinessUnit::where('parent_id', $parentId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($children as $child) {
            $ids = array_merge($ids, $this->getAllDescendantIds($child->id));
        }

        return $ids;
    }

    /**
     * Get business unit IDs the user can switch to.
     * - Holding: all units in the system
     * - Company: the company + all its branches
     * - Branch: parent company + all sibling branches (same company)
     */
    public function getSwitchableBusinessUnitIds(): array
    {
        if (!$this->current_business_unit_id) {
            return [];
        }

        $businessUnit = $this->businessUnit;
        if (!$businessUnit) {
            return [];
        }

        return match ($businessUnit->type_code) {
            'HOLDING' => $this->getAllBusinessUnitIds(),
            'COMPANY' => $this->getAllDescendantIds($businessUnit->id),
            'BRANCH' => $businessUnit->parent_id
                ? array_merge([$businessUnit->parent_id], $this->getChildIds($businessUnit->parent_id))
                : [$businessUnit->id],
            default => [],
        };
    }

    /**
     * Get accessible business unit IDs including parent for queries.
     * For Company users, returns their company ID and all branch IDs.
     * For Branch users, returns their branch ID.
     * For Holding users, returns all business unit IDs.
     */
    public function getAccessibleBusinessUnitIdsForQuery(): array
    {
        if (!$this->current_business_unit_id) {
            return [];
        }

        $businessUnit = $this->businessUnit;

        if (!$businessUnit) {
            return [];
        }

        return match($businessUnit->type_code) {
            'HOLDING' => $this->getAllBusinessUnitIds(),
            'COMPANY' => array_merge([$businessUnit->id], $this->getChildIds($businessUnit->id)),
            'BRANCH' => [$businessUnit->id],
            default => [],
        };
    }

    /**
     * Get all business unit IDs in the system (for Holding users).
     */
    protected function getAllBusinessUnitIds(): array
    {
        return BusinessUnit::whereNull('deleted_at')
            ->pluck('id')
            ->toArray();
    }

    /**
     * Get branch ID for transactional records (PO, SO, etc.).
     * - Branch user: returns current_business_unit_id directly
     * - Company user: returns first child branch, or current_business_unit_id as fallback
     * - Holding / no business unit: returns null
     */
    public function getBranchIdForTransaction(): ?string
    {
        if (!$this->current_business_unit_id) {
            return null;
        }

        $businessUnit = $this->businessUnit;

        if (!$businessUnit) {
            return null;
        }

        return match ($businessUnit->type_code) {
            'BRANCH' => $businessUnit->id,
            'COMPANY' => BusinessUnit::where('parent_id', $businessUnit->id)
                ->where('type_code', 'BRANCH')
                ->whereNull('deleted_at')
                ->value('id') ?? $businessUnit->id,
            default => null,
        };
    }

    /**
     * Get company ID for Product scope based on user's current business unit.
     * - Company user: returns current_business_unit_id
     * - Branch user: returns parent company ID
     * - Holding user: returns null (manages multiple companies)
     * - Super admin / no business unit: returns null
     */
    public function getCompanyIdForProduct(): ?string
    {
        if (!$this->current_business_unit_id) {
            return null;
        }

        $businessUnit = $this->businessUnit;

        if (!$businessUnit) {
            return null;
        }

        return match ($businessUnit->type_code) {
            'COMPANY' => $businessUnit->id,
            'BRANCH' => $businessUnit->parent_id,
            'HOLDING' => null,
            default => null,
        };
    }

    /**
     * Get direct child IDs.
     */
    protected function getChildIds(string $parentId): array
    {
        return BusinessUnit::where('parent_id', $parentId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id', 'id');
    }

    /**
     * Get the URL image attribute with automatic conversion from localhost to production URL.
     * This ensures old URLs stored in database are automatically converted to HTTPS.
     */
    public function getUrlImageAttribute($value)
    {
        if (empty($value)) {
            return config('app.url') . '/assets/img/wms/avatar/user-default.jpg';
        }

        // If URL contains localhost or http://, convert it to HTTPS with current domain
        if (str_contains($value, 'localhost') || str_contains($value, '127.0.0.1') || str_starts_with($value, 'http://')) {
            // Extract the path from the URL
            $path = parse_url($value, PHP_URL_PATH);

            // If path is empty, try to extract from full URL
            if (empty($path)) {
                // Try to get path after domain
                $parts = explode('/', $value);
                $pathIndex = array_search('assets', $parts);
                if ($pathIndex !== false) {
                    $path = '/' . implode('/', array_slice($parts, $pathIndex));
                } else {
                    // Fallback to default
                    return config('app.url') . '/assets/img/wms/avatar/user-default.jpg';
                }
            }

            // Build new URL with current domain and HTTPS
            return config('app.url') . $path;
        }

        // If URL is already HTTPS or relative, return as is
        return $value;
    }
}
