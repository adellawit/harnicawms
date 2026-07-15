<?php

namespace App\Models\Training;

use App\Support\YouTube;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CourseMaterial extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'training.course_materials';

    protected $fillable = [
        'company_id', 'module_id', 'title', 'type', 'file_path', 'youtube_url',
        'estimated_minutes', 'sort_order', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'estimated_minutes' => 'integer',
        'sort_order' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'module_id', 'id');
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::url($this->file_path) : null;
    }

    public function getYoutubeEmbedIdAttribute(): ?string
    {
        return $this->youtube_url ? YouTube::embedId($this->youtube_url) : null;
    }
}
