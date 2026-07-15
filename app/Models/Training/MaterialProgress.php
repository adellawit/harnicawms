<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MaterialProgress extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'training.material_progress';

    protected $fillable = ['user_id', 'material_id', 'viewed_at', 'completed_at'];

    protected $casts = [
        'viewed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
