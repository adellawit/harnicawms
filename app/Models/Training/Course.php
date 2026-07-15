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
        'thumbnail_asset_id',
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

    public function thumbnailAsset(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Marketing\Asset::class, 'thumbnail_asset_id', 'id');
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail_asset_id && $this->thumbnailAsset) {
            return $this->thumbnailAsset->file_url;
        }
        return $this->thumbnail_path ? Storage::url($this->thumbnail_path) : null;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
