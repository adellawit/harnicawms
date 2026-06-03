<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DashboardConfiguration extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The database connection and table.
     */
    protected $connection = 'pgsql';
    protected $table = 'configuration.dashboard_configurations';

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
        'section',
        'widget',
        'is_visible',
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

        // Generate UUID when creating a new DashboardConfiguration
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
     * Get dashboard configuration for a role
     * Returns array with section => widget => is_visible structure
     */
    public static function getConfigForRole($roleId)
    {
        $configs = self::where('role_id', $roleId)
            ->whereNull('deleted_at')
            ->get();

        $result = [];
        foreach ($configs as $config) {
            if (!isset($result[$config->section])) {
                $result[$config->section] = [];
            }
            if ($config->widget) {
                $result[$config->section][$config->widget] = $config->is_visible;
            } else {
                // If no widget, it's a section-level configuration
                $result[$config->section]['_enabled'] = $config->is_visible;
            }
        }

        return $result;
    }

    /**
     * Check if a section/widget is visible for a role
     */
    public static function isVisible($roleId, $section, $widget = null)
    {
        $query = self::where('role_id', $roleId)
            ->where('section', $section)
            ->whereNull('deleted_at');

        if ($widget) {
            $query->where('widget', $widget);
        } else {
            $query->whereNull('widget');
        }

        $config = $query->first();

        // If no configuration exists, default to visible (backward compatibility)
        if (!$config) {
            return true;
        }

        return $config->is_visible;
    }
}
