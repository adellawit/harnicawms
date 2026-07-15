<?php

namespace App\Models\Marketing;

use App\Support\YouTube;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Asset extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'marketing.assets';

    protected $fillable = [
        'company_id', 'category_id', 'title', 'description', 'type',
        'file_path', 'link_url', 'body_text',
        'usable_in_marketing', 'usable_in_training', 'can_be_thumbnail',
        'status', 'sort_order', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'usable_in_marketing' => 'boolean',
        'usable_in_training' => 'boolean',
        'can_be_thumbnail' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeUsableInTraining(Builder $query): Builder
    {
        return $query->where('usable_in_training', true);
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::url($this->file_path) : null;
    }

    /** YouTube embed id for a video link, or null if the link isn't YouTube. */
    public function getVideoEmbedIdAttribute(): ?string
    {
        return $this->link_url ? YouTube::embedId($this->link_url) : null;
    }
}
