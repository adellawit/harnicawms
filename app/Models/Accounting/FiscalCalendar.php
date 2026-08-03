<?php

namespace App\Models\Accounting;

use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalCalendar extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'accounting.fiscal_calendars';

    protected $fillable = [
        'company_id',
        'name',
        'fiscal_year',
        'start_date',
        'end_date',
        'is_closed',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_closed' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'company_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class, 'fiscal_calendar_id')->orderBy('period_no');
    }

    public function beginningBalance(): HasOne
    {
        return $this->hasOne(BeginningBalance::class, 'fiscal_calendar_id');
    }

    public function displayLabel(): string
    {
        return trim($this->fiscal_year.' — '.$this->name);
    }
}
