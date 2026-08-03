<?php

namespace App\Models\Accounting;

use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashBankReconciliation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'accounting.cash_bank_reconciliations';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_COMPLETED => 'Completed',
    ];

    protected $fillable = [
        'company_id',
        'account_id',
        'reconciliation_date',
        'statement_balance',
        'book_balance',
        'cleared_balance',
        'difference',
        'status',
        'notes',
        'completed_at',
        'completed_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'statement_balance' => 'decimal:2',
        'book_balance' => 'decimal:2',
        'cleared_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'company_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CashBankReconciliationLine::class, 'reconciliation_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
