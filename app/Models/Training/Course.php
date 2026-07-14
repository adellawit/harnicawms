<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'training.courses';

    protected $fillable = [
        'company_id', 'category_id', 'title', 'description', 'thumbnail_path',
        'status', 'published_at', 'sort_order', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class, 'course_id', 'id')->orderBy('sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path ? Storage::url($this->thumbnail_path) : null;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
