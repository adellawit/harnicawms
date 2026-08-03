<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingRate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'master_data.shipping_rates';

    protected $fillable = [
        'origin_city_id',
        'destination_city_id',
        'courier_code',
        'service_code',
        'service_name',
        'base_amount',
        'per_kg_amount',
        'etd_min_days',
        'etd_max_days',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'per_kg_amount' => 'decimal:2',
        'etd_min_days' => 'integer',
        'etd_max_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public const COURIERS = [
        'jne' => 'JNE',
        'jnt' => 'J&T',
        'sicepat' => 'SiCepat',
        'pos' => 'POS Indonesia',
        'tiki' => 'TIKI',
    ];

    public function originCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'origin_city_id');
    }

    public function destinationCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'destination_city_id');
    }

    public function estimateForWeightKg(float $weightKg): float
    {
        $kg = max(1, (int) ceil($weightKg));

        return (float) $this->base_amount + ($kg * (float) $this->per_kg_amount);
    }
}
