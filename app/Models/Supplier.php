<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'master_data.suppliers';

    protected $fillable = [
        'id',
        'code',
        'name',
        'supplier_type_id',
        'company_id',
        'branch_id',
        'contact',
        'phone',
        'email',
        'is_ppn',
        'ppn_rate',
        'address',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_ppn' => 'boolean',
    ];

    public function supplierType(): BelongsTo
    {
        return $this->belongsTo(ParameterDetail::class, 'supplier_type_id', 'id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id', 'id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'company_id', 'id');
    }
}
