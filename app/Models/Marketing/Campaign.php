<?php

namespace App\Models\Marketing;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Campaign extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'marketing.campaigns';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'banner_path',
        'promotion_id',
        'reactivates_reseller',
        'is_active',
        'starts_at',
        'ends_at',
        'priority',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'reactivates_reseller' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CampaignParticipant::class, 'campaign_id');
    }

    public function getBannerUrlAttribute(): ?string
    {
        return $this->banner_path
            ? Storage::disk('public')->url($this->banner_path)
            : null;
    }

    public function scopeActiveNow($query, ?string $at = null)
    {
        $at = $at ?: now()->toDateTimeString();

        return $query->where('is_active', true)
            ->where(function ($q) use ($at) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }

    public static function generateCode(?string $companyId = null): string
    {
        $prefix = 'CMP-'.date('Ym').'-';

        $last = static::withTrashed()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1) + 1) : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
