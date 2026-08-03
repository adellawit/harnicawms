<?php

namespace App\Models\Accounting;

use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'accounting.chart_of_accounts';

    public const ACCOUNT_TYPES = [
        'asset' => 'Aset',
        'liability' => 'Liabilitas',
        'equity' => 'Ekuitas',
        'revenue' => 'Pendapatan',
        'expense' => 'Beban',
    ];

    public const NORMAL_BALANCES = [
        'debit' => 'Debit',
        'credit' => 'Kredit',
    ];

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'account_type',
        'normal_balance',
        'cash_flow_category_id',
        'parent_id',
        'level',
        'is_header',
        'is_contra_account',
        'is_cash_bank',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_header' => 'boolean',
        'is_contra_account' => 'boolean',
        'is_cash_bank' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'company_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function cashFlowCategory(): BelongsTo
    {
        return $this->belongsTo(CashFlowCategory::class, 'cash_flow_category_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(AccountMapping::class, 'account_id');
    }

    public function displayLabel(): string
    {
        return trim($this->code.' — '.$this->name);
    }
}
