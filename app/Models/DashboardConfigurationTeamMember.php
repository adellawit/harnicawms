<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DashboardConfigurationTeamMember extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The database connection and table.
     */
    protected $connection = 'pgsql';
    protected $table = 'configuration.dashboard_configuration_team_members';

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'role_id',
        'employee_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Boot function from Laravel.
     */
    protected static function boot()
    {
        parent::boot();

        // Generate UUID when creating a new record
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Relasi dengan model Role
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Relasi dengan model Employee
     */
    public function employee()
    {
        return $this->belongsTo(Employees::class, 'employee_id');
    }

    /**
     * Get allowed employee IDs for a role
     */
    public static function getAllowedEmployeeIds($roleId)
    {
        return self::where('role_id', $roleId)
            ->whereNull('deleted_at')
            ->pluck('employee_id')
            ->toArray();
    }
}
