<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AcademySetting extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'training.academy_settings';

    protected $fillable = ['show_progress_percentage', 'updated_by'];

    protected $casts = [
        'show_progress_percentage' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['show_progress_percentage' => true]);
    }
}
