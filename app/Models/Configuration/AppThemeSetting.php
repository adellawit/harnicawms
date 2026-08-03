<?php

namespace App\Models\Configuration;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AppThemeSetting extends Model
{
    use HasUuids;

    public const MODE_LOGO_EXTRACT = 'logo_extract';
    public const MODE_CUSTOM = 'custom';

    protected $connection = 'pgsql';

    protected $table = 'configuration.app_theme_settings';

    protected $fillable = [
        'primary_color',
        'secondary_color',
        'navbar_color',
        'sidebar_color',
        'background_color',
        'logo_path',
        'favicon_path',
        'color_mode',
        'glass_enabled',
        'motion_enabled',
        'updated_by',
    ];

    protected $casts = [
        'glass_enabled' => 'boolean',
        'motion_enabled' => 'boolean',
    ];
}
