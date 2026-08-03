<?php

namespace App\Models\Accounting;

use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountMapping extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'accounting.account_mappings';

    protected $fillable = [
        'company_id',
        'mapping_key',
        'account_id',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'company_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
