<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseModule extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'training.course_modules';

    protected $fillable = [
        'company_id', 'course_id', 'title', 'description', 'sort_order',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(CourseMaterial::class, 'module_id', 'id')->orderBy('sort_order');
    }
}
