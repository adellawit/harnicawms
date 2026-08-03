<?php

namespace App\Models\Accounting;

use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BeginningBalance extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'accounting.beginning_balances';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_POSTED => 'Posted',
    ];

    protected $fillable = [
        'company_id',
        'fiscal_calendar_id',
        'journal_id',
        'status',
        'posted_at',
        'posted_by',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'company_id');
    }

    public function fiscalCalendar(): BelongsTo
    {
        return $this->belongsTo(FiscalCalendar::class, 'fiscal_calendar_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BeginningBalanceLine::class, 'beginning_balance_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function totalDebit(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines->sum('credit');
    }

    public function difference(): float
    {
        return round($this->totalDebit() - $this->totalCredit(), 2);
    }
}
