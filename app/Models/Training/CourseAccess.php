<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CourseAccess extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'training.course_access';

    protected $fillable = ['user_id', 'course_id', 'first_opened_at', 'last_accessed_at', 'last_material_id'];

    protected $casts = [
        'first_opened_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];
}
