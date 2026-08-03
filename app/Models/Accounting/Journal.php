<?php

namespace App\Models\Accounting;

use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Journal extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'accounting.journals';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_POSTED => 'Posted',
    ];

    protected $fillable = [
        'company_id',
        'fiscal_calendar_id',
        'fiscal_period_id',
        'journal_no',
        'journal_date',
        'description',
        'status',
        'journal_type',
        'posted_at',
        'posted_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public const TYPE_MANUAL = 'manual';

    public const TYPE_OPENING_BALANCE = 'opening_balance';

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'company_id');
    }

    public function fiscalCalendar(): BelongsTo
    {
        return $this->belongsTo(FiscalCalendar::class, 'fiscal_calendar_id');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'journal_id')->orderBy('line_no');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(JournalAttachment::class, 'journal_id')->orderBy('created_at');
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
