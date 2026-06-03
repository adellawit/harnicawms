<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class IamHasAccess extends Model
{
    use HasFactory, SoftDeletes;

    // Jika nama tabel tidak sesuai dengan konvensi Laravel, Anda bisa mendefinisikannya di sini
    protected $table = 'auth.iam_has_accesses';

    // Jika Anda ingin menentukan kolom yang dapat diisi massal
    protected $fillable = [
        'id',
        'iam_access_id',
        'sidebar_menu_id',
        'is_create',
        'is_read',
        'is_update',
        'is_delete',
        'is_custom_1',
        'is_custom_2',
        'is_custom_3',
        'is_custom_4',
        'is_custom_5',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    // Jika Anda ingin menentukan kolom yang tidak dapat diisi massal
    // protected $guarded = ['id'];

    // Relasi dengan model IamAccess
    public function iamAccess()
    {
        return $this->belongsTo(IamAccess::class);
    }

    // Relasi dengan model Menu (sidebarMenu)
    public function sidebarMenu()
    {
        return $this->belongsTo(Menu::class, 'sidebar_menu_id');
    }

    // Alias untuk backward compatibility
    public function menu()
    {
        return $this->sidebarMenu();
    }

     /**
     * Boot function from Laravel.
     */
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
    
}
