<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MethodPayment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'master_data.method_payments';

    protected $fillable = [
        'id',
        'branch_id',
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
        'uses_payment_gateway',
        'gateway_provider',
        'payment_group_code',
        'gateway_channel_code',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'uses_payment_gateway' => 'boolean',
    ];

    public function isPgChannel(): bool
    {
        return $this->uses_payment_gateway && filled($this->gateway_channel_code);
    }

    public function isPgGroup(): bool
    {
        return $this->uses_payment_gateway && blank($this->gateway_channel_code);
    }

    public function resolvesPgGroupCode(): string
    {
        return strtoupper((string) ($this->payment_group_code ?: $this->code));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id', 'id');
    }
}
