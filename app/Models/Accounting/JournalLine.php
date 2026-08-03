<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'accounting.journal_lines';

    protected $fillable = [
        'journal_id',
        'account_id',
        'line_no',
        'description',
        'debit',
        'credit',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'line_no' => 'integer',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
