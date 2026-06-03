<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMutationType extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'public.stock_mutation_types';

    protected $fillable = [
        'id',
        'code',
        'name',
        'direction',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'in');
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', 'out');
    }
}
