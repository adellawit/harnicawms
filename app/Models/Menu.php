<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Menu extends Model
{
    use HasFactory, SoftDeletes;

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
    protected $table = 'master_data.menus';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'parent_id',
        'name',
        'code',
        'text_sidebar',
        'icon',
        'has_page',
        'url_path',
        'route_name',
        'slug',
        'level_sidebar',
        'order_number',
        'created_by',
        'updated_by',
        'deleted_by',
        'is_label',
        'has_create',
        'has_update',
        'has_read',
        'has_delete',
        'has_custom1',
        'has_custom2',
        'has_custom3',
        'has_custom4',
        'has_custom5',
    ];

    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    // Relasi ke child
    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id');
    }

    protected static function boot()
    {
        parent::boot();

        // Generate UUID when creating a new Role
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function iamHasAccess()
    {
        return $this->hasMany(IamHasAccess::class, 'sidebar_menu_id');
    }
}
