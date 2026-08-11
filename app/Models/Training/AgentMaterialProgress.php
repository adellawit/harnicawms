<?php

namespace App\Models\Training;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMaterialProgress extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'training.agent_material_progress';

    protected $fillable = [
        'customer_id',
        'material_id',
        'elapsed_seconds',
        'completed_at',
    ];

    protected $casts = [
        'elapsed_seconds' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class, 'material_id', 'id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
