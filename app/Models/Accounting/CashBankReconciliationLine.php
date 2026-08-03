<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashBankReconciliationLine extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'accounting.cash_bank_reconciliation_lines';

    protected $fillable = [
        'reconciliation_id',
        'journal_line_id',
        'is_cleared',
    ];

    protected $casts = [
        'is_cleared' => 'boolean',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(CashBankReconciliation::class, 'reconciliation_id');
    }

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class, 'journal_line_id');
    }
}
