<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPointConfiguration extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'crm.membership_point_configurations';

    protected $fillable = [
        'id',
        'branch_id',
        'name',
        'transaction_amount_step',
        'points_per_step',
        'is_default',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id');
    }
}
