<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductUnit extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.product_units';

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'code',
        'symbol',
        'description',
        'created_by',
        'updated_by',
    ];
}
