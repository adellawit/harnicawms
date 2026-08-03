<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeginningBalanceLine extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'accounting.beginning_balance_lines';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SUGGESTED = 'suggested';

    public const SOURCE_AUTO_BALANCE = 'auto_balance';

    protected $fillable = [
        'beginning_balance_id',
        'account_id',
        'debit',
        'credit',
        'source',
        'is_auto_balancing',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'is_auto_balancing' => 'boolean',
    ];

    public function beginningBalance(): BelongsTo
    {
        return $this->belongsTo(BeginningBalance::class, 'beginning_balance_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
