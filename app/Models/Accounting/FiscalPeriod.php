<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalPeriod extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'accounting.fiscal_periods';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_LOCKED = 'locked';

    public const STATUSES = [
        self::STATUS_OPEN => 'Open',
        self::STATUS_CLOSED => 'Closed',
        self::STATUS_LOCKED => 'Locked',
    ];

    protected $fillable = [
        'fiscal_calendar_id',
        'period_no',
        'name',
        'code',
        'start_date',
        'end_date',
        'status',
        'is_adjustment',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'period_no' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_adjustment' => 'boolean',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(FiscalCalendar::class, 'fiscal_calendar_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
