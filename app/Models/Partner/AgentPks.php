<?php

namespace App\Models\Partner;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class AgentPks extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_EXPIRED = 'expired';

    protected $connection = 'pgsql';

    protected $table = 'partner.agent_pks';

    protected $fillable = [
        'agent_id',
        'file_path',
        'file_name',
        'file_mime',
        'file_size',
        'start_date',
        'end_date',
        'status',
        'notes',
        'uploaded_by',
        'reminded_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reminded_at' => 'datetime',
        'file_size' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpiringWithinDays(Builder $query, int $days = 30): Builder
    {
        $today = Carbon::today();

        return $query->active()
            ->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $today->copy()->addDays($days));
    }

    public function daysUntilEnd(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        $today = Carbon::today()->startOfDay();
        $end = $this->end_date->copy()->startOfDay();

        return (int) $today->diffInDays($end, false);
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $remaining = $this->daysUntilEnd();

        return $remaining !== null && $remaining >= 0 && $remaining <= $days;
    }

    public function isExpiredByDate(): bool
    {
        return $this->end_date !== null && $this->end_date->lt(Carbon::today());
    }
}
