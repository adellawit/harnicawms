<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashFlowCategory extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'accounting.cash_flow_categories';

    public const CODE_NONE = 'none';

    public const CODE_OPERATING = 'operating';

    public const CODE_INVESTING = 'investing';

    public const CODE_FINANCING = 'financing';

    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function chartOfAccounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'cash_flow_category_id');
    }

    public function displayLabel(): string
    {
        return trim($this->code.' — '.$this->name);
    }
}
