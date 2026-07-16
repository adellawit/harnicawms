<?php

namespace App\Models\Training;

use App\Models\Marketing\Asset;
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
        'marketing_asset_id',
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

    public function marketingAsset(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Marketing\Asset::class, 'marketing_asset_id', 'id');
    }

    public function getIsAssetBackedAttribute(): bool
    {
        return $this->marketing_asset_id !== null;
    }

    /** Normalised rendering type: image | pdf | video (local 'youtube' maps to 'video'). */
    public function getEffectiveTypeAttribute(): string
    {
        if ($this->is_asset_backed) {
            return $this->marketingAsset->type; // image | pdf | video
        }
        return $this->type === 'youtube' ? 'video' : $this->type; // image | pdf | video
    }

    public function getEffectiveFileUrlAttribute(): ?string
    {
        return $this->is_asset_backed ? $this->marketingAsset->file_url : $this->file_url;
    }

    public function getEffectiveVideoUrlAttribute(): ?string
    {
        return $this->is_asset_backed ? $this->marketingAsset->link_url : $this->youtube_url;
    }

    public function getEffectiveVideoEmbedIdAttribute(): ?string
    {
        $url = $this->effective_video_url;
        return $url ? \App\Support\YouTube::embedId($url) : null;
    }
}
